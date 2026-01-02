<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InventoryStockResource\Pages;
use App\Models\InventoryStock;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;

class InventoryStockResource extends Resource
{
    protected static ?string $model = InventoryStock::class;
    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';
    protected static ?string $navigationLabel = 'Stok Real-time';
    protected static ?string $navigationGroup = 'Monitoring Stok';
    protected static ?int $navigationSort = 1;

    // 👇 PENTING: Matikan Create & Delete agar stok murni hasil kalkulasi sistem
    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete(mixed $record): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informasi Stok (Locked)')
                    ->description('Data ini dihitung otomatis oleh sistem dan tidak dapat diubah manual.')
                    ->schema([
                        Forms\Components\Select::make('warehouse_id')
                            ->relationship('warehouse', 'name')
                            ->label('Gudang')
                            ->disabled() // Terkunci
                            ->dehydrated(false), // Gak usah dikirim lagi ke DB saat save

                        Forms\Components\Select::make('item_id')
                            ->relationship('item', 'name')
                            ->label('Barang')
                            ->disabled()
                            ->dehydrated(false),

                        Forms\Components\TextInput::make('quantity')
                            ->label('Sisa Stok Saat Ini')
                            ->numeric()
                            ->disabled()
                            ->dehydrated(false),
                    ])->columns(3),

                Forms\Components\Section::make('Manajemen Lokasi')
                    ->description('Anda hanya diperbolehkan mengubah lokasi penyimpanan.')
                    ->schema([
                        Forms\Components\TextInput::make('rack_location')
                            ->label('Lokasi Rak')
                            ->placeholder('Contoh: RAK-A-01')
                            ->helperText('Update lokasi jika barang dipindahkan.')
                            ->required()
                            ->maxLength(255),
                    ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->striped()
            // 👇 OPTIMASI: Eager Load relasi biar loading cepat (Anti Lemot)
            ->modifyQueryUsing(fn(Builder $query) => $query->with(['item.unit', 'warehouse']))
            ->columns([
                Tables\Columns\TextColumn::make('warehouse.name')
                    ->label('Gudang')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('item.name')
                    ->label('Nama Barang')
                    ->description(fn(InventoryStock $record) => $record->item->code ?? '-')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('quantity')
                    ->label('Sisa Stok')
                    ->numeric() // Biar ada koma/titik ribuan (1,000)
                    ->weight('bold')
                    ->suffix(fn(InventoryStock $record) => " " . ($record->item->unit->name ?? ''))
                    // Logic Warna: Merah jika <= Min Stock
                    ->color(
                        fn(InventoryStock $record) =>
                        $record->quantity <= ($record->item->min_stock ?? 0) ? 'danger' : 'success'
                    )
                    ->sortable(),

                Tables\Columns\TextColumn::make('rack_location')
                    ->label('Lokasi Rak')
                    ->badge()
                    ->color('gray')
                    ->icon('heroicon-o-map-pin')
                    ->searchable()
                    ->placeholder('Belum set'),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Update Terakhir')
                    ->dateTime('d M Y H:i')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('warehouse_id')
                    ->label('Filter Gudang')
                    ->relationship('warehouse', 'name'),

                // Filter Stok Kritis (SQL Raw)
                Filter::make('low_stock')
                    ->label('Hanya Stok Menipis')
                    ->toggle()
                    ->query(
                        fn(Builder $query) =>
                        $query->whereRaw('quantity <= (SELECT min_stock FROM items WHERE items.id = inventory_stocks.item_id)')
                    ),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('Pindah Rak')
                    ->icon('heroicon-o-pencil')
                    ->modalWidth('lg'),
            ])
            ->bulkActions([
                // Kosongkan Bulk Actions demi keamanan
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInventoryStocks::route('/'),
            'edit'  => Pages\EditInventoryStock::route('/{record}/edit'),
        ];
    }
}
