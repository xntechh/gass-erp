<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;
use App\Models\Item;
use Illuminate\Support\Facades\Auth;

class DashboardHeader extends Widget
{
    // Alamat tampilan blade lo
    protected static string $view = 'filament.widgets.dashboard-header';

    // Biar lebarnya full satu layar
    protected int | string | array $columnSpan = 'full';

    public function getData(): array
    {
        $hour = now()->format('H');
        $salam = match (true) {
            $hour >= 5 && $hour < 11 => 'Selamat Pagi',
            $hour >= 11 && $hour < 15 => 'Selamat Siang',
            $hour >= 15 && $hour < 18 => 'Selamat Sore',
            default => 'Selamat Malam',
        };

        $totalItems = Item::count(); //
        $lowStock = Item::whereRaw(
            '(SELECT COALESCE(SUM(quantity), 0) FROM inventory_stocks WHERE inventory_stocks.item_id = items.id) <= min_stock'
        )->count();

        return [
            'salam' => $salam,
            'nama' => Auth::user()->name,
            'lowStockCount' => $lowStock,
            'totalItems' => $totalItems,
            'accuracy' => $totalItems > 0 ? round((($totalItems - $lowStock) / $totalItems) * 100, 1) : 0,
        ];
    }
}
