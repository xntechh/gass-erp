<?php

namespace App\Filament\Widgets;

use App\Models\Item;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class LatestLowStockItems extends BaseWidget
{
    // Biar tabelnya cukup lebar dan enak dibaca
    protected int | string | array $columnSpan = 1;

    // Beri judul yang tegas
    protected static ?string $heading = 'DAFTAR STOK KRITIS (ORDER SEGERA!)';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                // Ambil barang yang (Stok Fisik) <= (Min Stock)
                Item::query()
                    ->whereRaw(
                        '(SELECT COALESCE(SUM(quantity), 0) FROM inventory_stocks WHERE inventory_stocks.item_id = items.id) <= min_stock'
                    )
                    ->orderBy('name', 'asc')
                    ->limit(5) // Cukup 5 teratas biar gak menuhin layar
            )
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nama Barang')
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('code')
                    ->label('Kode'),

                Tables\Columns\TextColumn::make('stocks_sum_quantity')
                    ->label('Stok Saat Ini')
                    ->sum('stocks', 'quantity') // Otomatis hitung total qty
                    ->badge()
                    ->color('danger'),

                Tables\Columns\TextColumn::make('min_stock')
                    ->label('Batas Aman')
                    ->numeric(),

                Tables\Columns\TextColumn::make('unit.name')
                    ->label('Satuan'),
            ])
            ->actions([
                // Tombol cepat buat edit atau tambah stok
                Tables\Actions\Action::make('view')
                    ->label('Detail')
                    ->url(fn(Item $record): string => "/admin/items/{$record->id}/edit")
                    ->icon('heroicon-m-eye'),
            ]);
    }
}
