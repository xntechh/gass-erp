<?php

namespace App\Filament\Widgets;

use App\Models\InventoryStock;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class LowStockAlert extends BaseWidget
{
    // Judul Widget
    protected static ?string $heading = '⚠️ Peringatan Stok Menipis (Low Stock)';

    // Urutan widget (taruh paling atas biar eye-catching)
    protected static ?int $sort = 2;
    protected int | string | array $columnSpan = 'full'; // Paksa lebar penuh

    public function table(Table $table): Table
    {
        return $table
            ->query(
                InventoryStock::query()
                    // Kita gabungin tabel stok sama tabel item
                    ->join('items', 'inventory_stocks.item_id', '=', 'items.id')
                    // Logic Sakti: Ambil yang quantity <= min_stock
                    ->whereColumn('inventory_stocks.quantity', '<=', 'items.min_stock')
                    // Pastikan cuma ambil data stok
                    ->select('inventory_stocks.*')
                    ->with(['item', 'warehouse']) // Load relasi biar enteng
            )
            ->defaultSort('quantity', 'asc') // Urutkan dari yang paling sekarat
            ->columns([
                Tables\Columns\TextColumn::make('warehouse.name')
                    ->label('Gudang')
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('item.name')
                    ->label('Nama Barang')
                    ->searchable()
                    ->weight('bold'),

                // Stok Saat Ini (Merah kalau 0)
                Tables\Columns\TextColumn::make('quantity')
                    ->label('Sisa Stok')
                    ->alignCenter()
                    ->badge()
                    ->color('danger'), // Merah menyala

                // Batas Minimal (Buat pembanding)
                Tables\Columns\TextColumn::make('item.min_stock')
                    ->label('Batas Min.')
                    ->alignCenter()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('item.unit.name')
                    ->label('Satuan'),
            ])
            ->paginated(false); // Gak usah dipaging, tampilin semua yang kritis
    }
}
