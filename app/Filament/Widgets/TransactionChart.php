<?php

namespace App\Filament\Widgets;

use App\Models\Transaction;
use Filament\Widgets\ChartWidget;
use Flowframe\Trend\Trend;
use Flowframe\Trend\TrendValue;
use Illuminate\Database\Eloquent\Builder;

class TransactionChart extends ChartWidget
{

    protected static ?string $heading = 'Tren Keluar Masuk Barang (30 Hari Terakhir)';
    protected static ?int $sort = 4; // Taruh di bawah kartu statistik
    protected int | string | array $columnSpan = 'full'; // Lebar penuh biar ganteng

    protected function getData(): array
    {
        // 1. Data Barang Masuk (IN)
        // CARA BENAR: Masukin Query 'where' langsung di dalam kurung query()
        $dataIn = Trend::query(Transaction::where('type', 'IN'))
            ->between(now()->subDays(30), now())
            ->perDay()
            ->count();

        // 2. Data Barang Keluar (OUT)
        $dataOut = Trend::query(Transaction::where('type', 'OUT'))
            ->between(now()->subDays(30), now())
            ->perDay()
            ->count();

        return [
            'datasets' => [
                [
                    'label' => 'Barang Masuk',
                    'data' => $dataIn->map(fn(TrendValue $value) => $value->aggregate),
                    'borderColor' => '#10b981',
                ],
                [
                    'label' => 'Barang Keluar',
                    'data' => $dataOut->map(fn(TrendValue $value) => $value->aggregate),
                    'borderColor' => '#f43f5e',
                ],
            ],
            'labels' => $dataIn->map(fn(TrendValue $value) => $value->date),
        ];
    }

    protected function getType(): string
    {
        return 'line'; // Grafik Garis
    }
}
