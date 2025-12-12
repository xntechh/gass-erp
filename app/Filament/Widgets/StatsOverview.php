<?php

namespace App\Filament\Widgets;

use App\Models\Transaction;
use App\Models\TransactionDetail;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        // 1. Hitung Total Transaksi (Bon)
        $totalTrx = Transaction::count();

        // 2. Hitung Total Qty Barang Masuk (IN) yang sudah APPROVED
        $barangMasuk = TransactionDetail::whereHas('transaction', function ($query) {
            $query->where('type', 'IN')->where('status', 'APPROVED');
        })->sum('quantity');

        // 3. Hitung Total Qty Barang Keluar (OUT) yang sudah APPROVED
        $barangKeluar = TransactionDetail::whereHas('transaction', function ($query) {
            $query->where('type', 'OUT')->where('status', 'APPROVED');
        })->sum('quantity');

        return [
            Stat::make('Total Transaksi', $totalTrx)
                ->description('Semua bon masuk/keluar')
                ->descriptionIcon('heroicon-m-clipboard-document-list')
                ->color('primary'),

            Stat::make('Barang Masuk (Approved)', $barangMasuk . ' Items')
                ->description('Total stok bertambah')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success')
                ->chart([7, 2, 10, 3, 15, 4, 17]), // Grafik hiasan dummy

            Stat::make('Barang Keluar (Approved)', $barangKeluar . ' Items')
                ->description('Total stok berkurang')
                ->descriptionIcon('heroicon-m-arrow-trending-down')
                ->color('danger')
                ->chart([17, 10, 3, 15, 4, 2, 1]), // Grafik hiasan dummy
        ];
    }
}
