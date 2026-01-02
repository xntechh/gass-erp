<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CategoryResource\Pages;
use App\Models\Category;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;

class CategoryResource extends Resource
{
    protected static ?string $model = Category::class;
    protected static ?string $navigationIcon = 'heroicon-o-tag';
    protected static ?string $navigationGroup = 'Master Data';

    // Urutan 1: Biar muncul paling atas di kelompok Master Data
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informasi Kategori')
                    ->description('Pastikan kode kategori unik dan mudah diingat.')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nama Kategori')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255)
                            ->placeholder('Misal: Alat Kebersihan'),

                        Forms\Components\TextInput::make('code')
                            ->label('Kode Kategori')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(5)
                            ->placeholder('Misal: AKB')
                            // Validasi: Hanya Huruf Kapital & Angka, tanpa spasi
                            ->regex('/^[A-Z0-9]+$/')
                            ->validationMessages([
                                'regex' => 'Kode hanya boleh Huruf Kapital dan Angka (tanpa spasi).',
                            ])
                            // UX: Visual jadi huruf besar
                            ->extraInputAttributes(['style' => 'text-transform:uppercase'])
                            // Backend: Simpan sebagai huruf besar
                            ->dehydrateStateUsing(fn(string $state): string => strtoupper($state))
                            ->helperText('Maksimal 5 karakter. Digunakan sebagai prefix Kode Barang.'),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Status Aktif')
                            ->default(true)
                            ->required(),
                    ])->columns(2)
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->striped()
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('Kode')
                    ->weight('bold')
                    ->color('primary')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Nama Kategori')
                    ->searchable()
                    ->sortable(),

                // Kolom Jumlah Item
                Tables\Columns\TextColumn::make('items_count')
                    ->label('Jumlah Item')
                    ->counts('items') // Pastikan relasi 'items' ada di Model Category
                    ->badge()
                    ->color(fn($state) => $state > 0 ? 'info' : 'gray')
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Status')
                    ->boolean(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Terakhir Update')
                    ->dateTime('d M Y')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Status Aktif')
                    ->placeholder('Semua Status')
                    ->trueLabel('Hanya Aktif')
                    ->falseLabel('Tidak Aktif'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),

                // PROTEKSI DELETE: Jangan hapus jika masih ada barang
                Tables\Actions\DeleteAction::make()
                    ->before(function (Tables\Actions\DeleteAction $action, Category $record) {
                        if ($record->items()->count() > 0) {
                            Notification::make()
                                ->danger()
                                ->title('Gagal Menghapus')
                                ->body('Kategori ini masih digunakan oleh barang lain. Hapus/pindahkan barangnya terlebih dahulu.')
                                ->send();

                            // Batalkan proses hapus
                            $action->cancel();
                        }
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListCategories::route('/'),
            'create' => Pages\CreateCategory::route('/create'),
            'edit'   => Pages\EditCategory::route('/{record}/edit'),
        ];
    }
}
