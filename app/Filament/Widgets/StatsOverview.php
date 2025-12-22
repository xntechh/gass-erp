<?php

namespace App\Filament\Widgets;

use App\Models\Item;
use App\Models\StockOpname;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends BaseWidget
{

    protected int | string | array $columnSpan = 'full';

    protected function getStats(): array
    {
        // 1. Hitung Total Nilai Aset (PENTING!)
        // Kita hitung dari semua item * avg_cost
        $totalValuation = \App\Models\InventoryStock::join('items', 'inventory_stocks.item_id', '=', 'items.id')
            ->sum(\Illuminate\Support\Facades\DB::raw('inventory_stocks.quantity * items.avg_cost'));

        // 2. Hitung Stok Kritis
        $lowStockCount = Item::whereRaw(
            '(SELECT COALESCE(SUM(quantity), 0) FROM inventory_stocks WHERE inventory_stocks.item_id = items.id) <= min_stock'
        )->count();

        // 3. Hitung Opname yang belum diproses
        $pendingOpname = StockOpname::where('status', 'DRAFT')->count();

        return [
            Stat::make('Total Nilai Aset', 'IDR ' . number_format($totalValuation, 0, ',', '.'))
                ->description('Total valuasi stok fisik saat ini')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),

            Stat::make('Stok Kritis', $lowStockCount . ' Item')
                ->description('Barang di bawah batas minimum')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($lowStockCount > 0 ? 'danger' : 'success'),

            Stat::make('Opname Pending', $pendingOpname . ' Dokumen')
                ->description('Audit yang belum difinalisasi')
                ->descriptionIcon('heroicon-m-clipboard-document-check')
                ->color('warning'),
        ];
    }
}
