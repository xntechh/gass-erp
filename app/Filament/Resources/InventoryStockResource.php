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
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;


class InventoryStockResource extends Resource
{
    protected static ?string $model = InventoryStock::class;
    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';
    protected static ?string $navigationLabel = 'Stok Real-time';
    protected static ?string $navigationGroup = 'Monitoring Stok';
    protected static ?int $navigationSort = 1;


    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Informasi Stok (Locked)')
                    ->description('Data ini dihitung otomatis. Tidak bisa diubah manual.')
                    ->schema([
                        Select::make('warehouse_id')
                            ->relationship('warehouse', 'name')
                            ->label('Gudang')
                            ->disabled(),

                        Select::make('item_id')
                            ->relationship('item', 'name')
                            ->label('Barang')
                            ->disabled(),

                        TextInput::make('quantity')
                            ->label('Sisa Stok')
                            ->disabled(),
                    ])->columns(3),

                Section::make('Manajemen Lokasi')
                    ->schema([
                        TextInput::make('rack_location')
                            ->label('Lokasi Rak')
                            ->placeholder('Contoh: RAK-A1')
                            ->required(),
                    ])
            ]);
    }

    // 👇 LOGIC PENTING: Matikan tombol "New" agar tidak ada stok ghaib
    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->striped()
            ->columns([
                Tables\Columns\TextColumn::make('warehouse.name')
                    ->label('Gudang')
                    ->sortable(),

                Tables\Columns\TextColumn::make('item.name')
                    ->label('Nama Barang')
                    ->description(fn(InventoryStock $record) => $record->item->code)
                    ->searchable(),

                // 👇 INFO STOK: Berwarna MERAH kalau di bawah Min Stock
                Tables\Columns\TextColumn::make('quantity')
                    ->label('Stok')
                    ->weight('bold')
                    ->suffix(fn(InventoryStock $record) => " " . $record->item->unit->name)
                    ->color(
                        fn(InventoryStock $record) =>
                        $record->quantity <= $record->item->min_stock ? 'danger' : 'success'
                    )
                    ->sortable(),

                Tables\Columns\TextColumn::make('rack_location')
                    ->label('Lokasi Rak')
                    ->badge()
                    ->color('gray')
                    ->searchable(),
            ])
            ->filters([
                // 👇 FILTER: Biar lo gampang liat stok per Gudang
                SelectFilter::make('warehouse_id')
                    ->label('Filter Gudang')
                    ->relationship('warehouse', 'name'),

                // 👇 FILTER: Biar lo gampang liat barang yang kritis aja
                Tables\Filters\Filter::make('low_stock')
                    ->label('Stok Kritis')
                    ->query(fn($query) => $query->whereRaw('quantity <= (SELECT min_stock FROM items WHERE items.id = inventory_stocks.item_id)')),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->label('Update Rak'),
            ])
            ->bulkActions([
                // Delete Bulk dihapus demi keamanan data audit
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInventoryStocks::route('/'),
            'edit' => Pages\EditInventoryStock::route('/{record}/edit'),
        ];
    }
}
