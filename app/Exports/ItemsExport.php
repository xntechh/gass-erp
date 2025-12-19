<?php

namespace App\Exports;

use App\Models\Item;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings; // Untuk nama kolom
use Maatwebsite\Excel\Concerns\WithMapping;  // Untuk milih data (biar gak muncul ID)
use Maatwebsite\Excel\Concerns\WithEvents;   // Untuk nambahin Judul di atas
use Maatwebsite\Excel\Events\AfterSheet;

class ItemsExport implements FromCollection, WithHeadings, WithMapping, WithEvents
{
    public function collection()
    {
        // PENTING: Pake 'with' biar gak lemot (N+1 Problem)
        return Item::with(['category', 'unit'])->get();
    }

    // 1. HEADER KOLOM (Baris pertama tabel)
    public function headings(): array
    {
        return [
            'Kategori',
            'Nama Barang',
            'Kode Barang',
            'Satuan',
            'Min Stock',
            'Harga Modal (Rp)',
            'Total Aset (Rp)',
        ];
    }

    // 2. MAPPING DATA (Biar muncul Teks, bukan ID angka)
    public function map($item): array
    {
        return [
            $item->category->name, // Muncul "Seragam", bukan "1"
            $item->name,
            $item->code,
            $item->unit->name ?? '-',
            $item->min_stock,
            $item->avg_cost,
            $item->stocks()->sum('quantity') * $item->avg_cost, // Hitung Total Aset
        ];
    }

    // 3. JUDUL LAPORAN (Paling Atas)
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                // Tambahin baris baru di paling atas (Baris 1)
                $event->sheet->insertNewRowBefore(1, 2);

                // Isi Judul
                $event->sheet->setCellValue('A1', 'LAPORAN DATA STOK BARANG GUDANG SENTUL');
                $event->sheet->setCellValue('A2', 'Tanggal Cetak: ' . now()->format('d-m-Y H:i'));

                // Style Judul (Bold & Gede)
                $event->sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

                // Merge baris judul biar ke tengah (Opsional)
                $event->sheet->mergeCells('A1:G1');
            },
        ];
    }
}
