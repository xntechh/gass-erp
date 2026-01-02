<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ItemResource\Pages;
use App\Filament\Resources\ItemResource\RelationManagers;
use App\Models\Item;
use App\Models\Category;
use App\Exports\ItemTemplateExport;
use App\Exports\ItemsExport;
use App\Imports\ItemImport;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class ItemResource extends Resource
{
    protected static ?string $model = Item::class;
    protected static ?string $navigationIcon = 'heroicon-o-cube';
    protected static ?string $navigationGroup = 'Master Data';
    protected static ?int $navigationSort = 3;

    /**
     * Menampilkan angka merah di sidebar jika ada stok di bawah minimum
     */
    public static function getNavigationBadge(): ?string
    {
        $lowStockCount = static::getModel()::whereRaw(
            '(SELECT COALESCE(SUM(quantity), 0) FROM inventory_stocks WHERE inventory_stocks.item_id = items.id) <= min_stock'
        )->count();

        return $lowStockCount > 0 ? (string) $lowStockCount : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Jumlah barang dengan stok menipis';
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'code'];
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            'Kategori' => $record->category->name ?? '-',
            'Stok'     => $record->stocks()->sum('quantity') . ' ' . ($record->unit->name ?? 'Pcs'),
        ];
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('category_id')
                    ->relationship('category', 'name')
                    ->label('Kategori')
                    ->required()
                    ->searchable()
                    ->preload()
                    ->live()
                    ->afterStateUpdated(function ($state, Set $set) {
                        // Logic Generate Kode Otomatis
                        if ($state) {
                            $category = Category::find($state);

                            if ($category && $category->code) {
                                // PERBAIKAN: Gunakan withTrashed() agar nomor urut tidak bentrok dengan item yg pernah dihapus
                                $urutan = Item::where('category_id', $state)->withTrashed()->count() + 1;

                                // Format: AKB000001
                                $nomorUrut = str_pad($urutan, 6, '0', STR_PAD_LEFT);
                                $generatedCode = $category->code . $nomorUrut;

                                $set('code', $generatedCode);
                            }
                        }
                    }),

                Forms\Components\TextInput::make('name')
                    ->label('Nama Barang')
                    ->required()
                    ->maxLength(255),

                Forms\Components\TextInput::make('code')
                    ->label('Kode Barang (Auto)')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->disabled()
                    ->dehydrated() // Wajib agar tersimpan ke DB
                    ->helperText('Kode digenerate otomatis berdasarkan Kategori.'),

                Forms\Components\Select::make('unit_id')
                    ->label('Satuan')
                    ->relationship('unit', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->createOptionForm([
                        Forms\Components\TextInput::make('name')
                            ->label('Nama Satuan')
                            ->required(),
                        Forms\Components\TextInput::make('code')
                            ->label('Singkatan'),
                    ]),

                Forms\Components\TextInput::make('min_stock')
                    ->label('Minimum Stok (Alert)')
                    ->required()
                    ->numeric()
                    ->default(10),

                Forms\Components\TextInput::make('avg_cost')
                    ->label('Harga Modal (HPP)')
                    ->numeric()
                    ->prefix('Rp')
                    ->default(0)
                    ->required(),

                Forms\Components\Toggle::make('is_active')
                    ->label('Status Aktif')
                    ->default(true)
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->striped()
            ->columns([
                Tables\Columns\TextColumn::make('category.name')
                    ->label('Kategori')
                    ->sortable(),

                Tables\Columns\TextColumn::make('code')
                    ->label('Kode')
                    ->searchable()
                    ->copyable(), // Biar user bisa copy kode

                Tables\Columns\TextColumn::make('name')
                    ->label('Nama Barang')
                    ->searchable()
                    ->wrap(), // Wrap text kalau kepanjangan

                Tables\Columns\TextColumn::make('unit.name')
                    ->label('Satuan')
                    ->sortable(),

                Tables\Columns\TextColumn::make('min_stock')
                    ->label('Min. Stok')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('avg_cost')
                    ->label('HPP')
                    ->money('IDR')
                    ->sortable(),

                Tables\Columns\TextColumn::make('total_valuation')
                    ->label('Total Aset')
                    ->money('IDR')
                    ->state(function (Item $record): float {
                        $totalQty = $record->stocks()->sum('quantity');
                        return $totalQty * ($record->avg_cost ?? 0);
                    })
                    ->color('success')
                    ->sortable(false),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category')
                    ->relationship('category', 'name')
                    ->label('Filter Kategori'),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Status Aktif'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->headerActions([
                // Grouping tombol Excel agar rapi
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('downloadTemplate')
                        ->label('Download Template')
                        ->icon('heroicon-o-document-arrow-down')
                        ->action(fn() => Excel::download(new ItemTemplateExport, 'template_import_barang.xlsx')),

                    Tables\Actions\Action::make('exportData')
                        ->label('Export Data Stok')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->action(fn() => Excel::download(new ItemsExport, 'data_stok_' . date('Y-m-d') . '.xlsx')),
                ])
                    ->label('Menu Excel')
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->color('info'),

                // Tombol Import
                Tables\Actions\Action::make('importExcel')
                    ->label('Import Barang')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->color('success')
                    ->form([
                        Forms\Components\FileUpload::make('attachment')
                            ->label('Upload File Excel (.xlsx)')
                            ->acceptedFileTypes(['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/vnd.ms-excel'])
                            ->disk('public')
                            ->directory('imports')
                            ->required(),
                    ])
                    ->action(function (array $data) {
                        $filePath = Storage::disk('public')->path($data['attachment']);
                        Excel::import(new ItemImport, $filePath);

                        Notification::make()
                            ->title('Sukses Import')
                            ->body('Data barang berhasil ditambahkan ke database.')
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            // Pastikan kamu punya RelationManager ini, kalau error hapus baris ini
            RelationManagers\TransactionDetailsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListItems::route('/'),
            'create' => Pages\CreateItem::route('/create'),
            'edit'   => Pages\EditItem::route('/{record}/edit'),
        ];
    }
}
