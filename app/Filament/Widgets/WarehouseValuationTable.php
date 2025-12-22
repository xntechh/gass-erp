<?php

namespace App\Filament\Widgets;

use App\Models\Warehouse;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class WarehouseValuationTable extends BaseWidget
{
    protected static ?string $heading = 'Rincian Aset Per Lokasi Gudang';
    protected int | string | array $columnSpan = 1; // Biar bisa bagi dua lapak di bawah

    public function table(Table $table): Table
    {
        return $table
            ->query(Warehouse::query())
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nama Gudang')
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('stocks_sum_quantity')
                    ->label('Total Item')
                    ->sum('stocks', 'quantity')
                    ->badge(),

                // Logic Hitung Valuasi per baris gudang
                Tables\Columns\TextColumn::make('valuation')
                    ->label('Total Valuasi')
                    ->money('IDR')
                    ->state(function (Warehouse $record) {
                        return $record->stocks->sum(fn($stock) => $stock->quantity * ($stock->item->avg_cost ?? 0));
                    }),
            ])
            ->paginated(false);
    }
}
