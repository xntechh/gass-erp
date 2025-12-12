<?php

namespace App\Filament\Widgets;

use App\Models\Warehouse;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class WarehouseSummary extends BaseWidget
{
    // Judul Widget
    protected static ?string $heading = 'Ringkasan Aset Gudang';
    
    // Biar widgetnya lebar (Full Width)
    protected int | string | array $columnSpan = 'full';

    // Urutan Widget (Makin besar makin bawah)
    protected static ?int $sort = 2; 

    public function table(Table $table): Table
    {
        return $table
            ->query(
                // Ambil data Gudang, sekalian bawa data Plant & Stok
                Warehouse::query()->with(['plant', 'inventoryStocks.item'])
            )
            // FITUR 1: JUMLAH GUDANG PER PLANT
            // Kita kelompokkan baris berdasarkan Nama Plant
            ->defaultGroup('plant.name') 
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nama Gudang')
                    ->searchable(),

                // FITUR 2: TOTAL JENIS BARANG (BUKAN QTY)
                // Menghitung ada berapa baris record di tabel stok (Item types)
                Tables\Columns\TextColumn::make('inventory_stocks_count')
                    ->counts('inventoryStocks') // Fungsi sakti Laravel
                    ->label('Jml Jenis Item')
                    ->alignCenter(),

                // FITUR 3: VALUATION (Nilai Rupiah)
                // Rumus: Sum (Quantity * Avg Cost)
                Tables\Columns\TextColumn::make('valuation')
                    ->label('Total Aset (Valuation)')
                    ->getStateUsing(function (Warehouse $record) {
                        // Kita looping stok di gudang ini
                        return $record->inventoryStocks->sum(function ($stock) {
                            // Harga Modal ambil dari Item, dikali Qty Stok
                            return $stock->quantity * $stock->item->avg_cost;
                        });
                    })
                    ->money('IDR') // Format Rupiah otomatis
                    ->sortable(false) // Gak bisa disort krn ini angka hitungan
                    ->weight('bold'),
            ]);
    }
}