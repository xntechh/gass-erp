<?php

namespace App\Filament\Widgets;

use App\Models\Transaction;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class TopDepartmentChart extends ChartWidget
{
    public static function canView(): bool
    {
        return false; // Paksa return false biar gak muncul
    }
    protected static ?string $heading = 'Top 5 Departemen Paling Boros (Frekuensi)';
    protected static ?int $sort = 3;

    protected function getData(): array
    {
        // Logic: Hitung transaksi OUT, group by Department, ambil 5 terbanyak
        $data = Transaction::query()
            ->join('departments', 'transactions.department_id', '=', 'departments.id')
            ->select('departments.name', DB::raw('count(*) as total'))
            ->where('transactions.type', 'OUT')
            ->groupBy('departments.name')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        return [
            'datasets' => [
                [
                    'label' => 'Jumlah Permintaan',
                    'data' => $data->pluck('total'),
                    'backgroundColor' => [
                        '#3b82f6',
                        '#8b5cf6',
                        '#ec4899',
                        '#f97316',
                        '#eab308'
                    ], // Warna-warni biar cantik
                ],
            ],
            'labels' => $data->pluck('name'),
        ];
    }

    protected function getType(): string
    {
        return 'bar'; // Grafik Batang
    }
}
