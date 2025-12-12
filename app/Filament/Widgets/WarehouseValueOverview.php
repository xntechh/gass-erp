<?php

namespace App\Filament\Widgets;

use App\Models\Item;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class WarehouseValueOverview extends BaseWidget
{
    protected static ?int $sort = 0; // Paling Atas

    protected function getStats(): array
    {
        // Hitung: (Stok Total per Item * Harga Rata-rata Item tersebut)
        // Kita butuh sum(quantity) dari inventory_stocks dulu, dikali avg_cost items

        // Query Agak Advanced dikit (Gabungin tabel Items & Stocks)
        $totalAssetValue = Item::join('inventory_stocks', 'items.id', '=', 'inventory_stocks.item_id')
            ->select(DB::raw('SUM(inventory_stocks.quantity * items.avg_cost) as total_value'))
            ->value('total_value');

        // Set Batas Limit (Misal: 1 Milyar)
        $limitValue = 1000000000;

        // Cek Bahaya
        $isDanger = $totalAssetValue > $limitValue;

        return [
            Stat::make('Total Nilai Aset Gudang', 'Rp ' . number_format($totalAssetValue, 0, ',', '.'))
                ->description($isDanger ? 'MELEBIHI LIMIT BUDGET!' : 'Aman (Dibawah Ketentuan Valuation)')
                ->descriptionIcon($isDanger ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check-circle')
                ->color($isDanger ? 'danger' : 'success')
                ->chart($isDanger ? [1, 5, 10, 20] : [20, 10, 5, 1]), // Grafik hiasan
        ];
    }
}
