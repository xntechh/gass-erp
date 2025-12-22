<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ItemResource\Pages;
use App\Filament\Resources\ItemResource\RelationManagers;
use App\Exports\ItemTemplateExport;
use App\Models\Item;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Get;
use Filament\Forms\Set;
use App\Models\Category;
use Illuminate\Database\Eloquent\Model;
use App\Imports\ItemImport;
use Maatwebsite\Excel\Facades\Excel;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Storage;
use App\Exports\ItemsExport;

class ItemResource extends Resource
{
    protected static ?string $model = Item::class;

    protected static ?string $navigationIcon = 'heroicon-o-cube';

    protected static ?string $navigationGroup = 'Master Data';
    protected static ?int $navigationSort = 3; // Muncul di bawah

    // 👇 LOGIC ANGKA MERAH DI SIDEBAR 👇
    public static function getNavigationBadge(): ?string
    {
        // LOGIC BARU:
        // "Hitung item di mana (Total Quantity di InventoryStocks) <= (Min Stock di Item)"

        $lowStockCount = static::getModel()::whereRaw(
            '(SELECT COALESCE(SUM(quantity), 0) FROM inventory_stocks WHERE inventory_stocks.item_id = items.id) <= min_stock'
        )->count();

        return $lowStockCount > 0 ? (string) $lowStockCount : null;
    }

    // Kasih warna Merah biar panik (Warning)
    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    // Kasih Tooltip pas di-hover (Opsional)
    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Barang stok menipis!';
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'code']; // Cari berdasarkan Nama atau Kode Barang
    }

    // (Opsional) Biar pas hasil search muncul, ada info tambahannya
    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            'Kategori' => $record->category->name,
            'Stok' => $record->stocks()->sum('quantity') . ' ' . $record->unit->name,
        ];
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('category_id')
                    ->relationship('category', 'name')
                    ->required()
                    ->searchable()
                    ->preload()
                    // 👇 MULAI DARI SINI LOGIC AJAIBNYA 👇
                    ->live() // Wajib live biar bereaksi real-time
                    ->afterStateUpdated(function ($state, Set $set) {
                        // 1. Cek apakah user sudah memilih kategori?
                        if ($state) {
                            // 2. Cari data kategori di database berdasarkan ID yang dipilih
                            $category = Category::find($state);

                            // 3. Kalau kategori ketemu dan punya kode (misal: AKB)
                            if ($category && $category->code) {
                                // 4. Hitung ada berapa barang dengan kategori ini sebelumnya
                                // Kita tambah +1 buat barang yang baru ini
                                $urutan = Item::where('category_id', $state)->count() + 1;

                                // 5. Format angkanya biar jadi 6 digit (000001)
                                // str_pad adalah fungsi PHP buat nambahin nol di depan
                                $nomorUrut = str_pad($urutan, 6, '0', STR_PAD_LEFT);

                                // 6. Gabungkan Kode + Nomor (AKB + 000001)
                                $generatedCode = $category->code . $nomorUrut;

                                // 7. Tempel hasilnya ke kolom 'code'
                                $set('code', $generatedCode);
                            }
                        }
                    }),
                // 👆 SELESAI LOGIC 👆
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('code')
                    ->label('Kode Barang (Otomatis)')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->disabled() // Kunci mati (abu-abu)
                    ->dehydrated() // Wajib ada biar tetep kesimpen ke database
                    ->readOnly(false) // Ganti true kalau mau user GAK BISA edit samsek
                    ->helperText('Kode ini digenerate otomatis berdasarkan Kategori.'),
                Forms\Components\Select::make('unit_id') // Perhatikan pake _id
                    ->label('Satuan')
                    ->relationship('unit', 'name') // Ambil dari tabel Units
                    ->searchable()
                    ->preload()
                    ->required()
                    // 👇 FITUR MAGIC: TAMBAH SATUAN LANGSUNG DARI SINI 👇
                    ->createOptionForm([
                        Forms\Components\TextInput::make('name')
                            ->label('Nama Satuan')
                            ->required(),
                        Forms\Components\TextInput::make('code')
                            ->label('Singkatan (Opsional)'),
                    ]),
                Forms\Components\TextInput::make('min_stock')
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
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->striped()
            ->columns([
                Tables\Columns\TextColumn::make('category.name')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('code')
                    ->searchable(),
                Tables\Columns\TextColumn::make('unit.code') // <--- Pake titik (.name)
                    ->label('Satuan')
                    ->sortable(),
                Tables\Columns\TextColumn::make('min_stock')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('avg_cost')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('total_valuation')
                    ->label('Total Aset')
                    ->money('IDR') // Format Rp otomatis
                    ->state(function (Item $record): float {
                        // 1. Ambil Total Stok dari semua gudang
                        $totalQty = $record->stocks()->sum('quantity');

                        // 2. Kalikan dengan Harga Rata-rata (Avg Cost)
                        return $totalQty * $record->avg_cost;
                    })
                    ->sortable(false), // Gak bisa disort karena ini kolom hitungan
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filtersLayout(Tables\Enums\FiltersLayout::AboveContent)
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->headerActions([
                Tables\Actions\Action::make('exportData')
                    ->label('Download Data Stok')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('info') // Warna Biru
                    ->action(function () {
                        return Excel::download(new ItemsExport, 'data_stok_gass_' . now()->format('Y-m-d') . '.xlsx');
                    }),

                // 1. Tombol Download Template (Baru)
                Tables\Actions\Action::make('downloadTemplate')
                    ->label('Template Excel')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('warning') // Warna Kuning biar beda
                    ->action(function () {
                        return Excel::download(new ItemTemplateExport, 'template_import_barang.xlsx');
                    }),

                // 2. Tombol Import Excel (Yang Tadi)
                Tables\Actions\Action::make('importExcel')
                    ->label('Import Excel')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->color('success')
                    ->form([
                        Forms\Components\FileUpload::make('attachment')
                            ->label('Upload File Excel (.xlsx)')
                            ->acceptedFileTypes(['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/vnd.ms-excel'])
                            ->disk('public') // <--- Paksa simpan di disk Public
                            ->directory('imports') // <--- Masukin folder khusus biar rapi
                            ->required(),
                    ])
                    ->action(function (array $data) {
                        // CARA LAMA (ERROR):
                        // $file = public_path('storage/' . $data['attachment']);

                        // CARA BARU (ANTI GALAU):
                        // Kita tanya sistem: "Eh, file ini aslinya lu simpen di mana sih?"
                        $filePath = Storage::disk('public')->path($data['attachment']);

                        Excel::import(new ItemImport, $filePath);

                        Notification::make()
                            ->title('Sukses!')
                            ->body('Data barang berhasil diimport.')
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
            RelationManagers\TransactionDetailsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListItems::route('/'),
            'create' => Pages\CreateItem::route('/create'),
            'edit' => Pages\EditItem::route('/{record}/edit'),
        ];
    }
}
