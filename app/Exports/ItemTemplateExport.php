<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize; // Biar lebar kolom otomatis rapi
use Illuminate\Support\Collection;

class ItemTemplateExport implements FromCollection, WithHeadings, ShouldAutoSize
{
    // 1. Isinya apa? (Kita kasih 1 baris contoh)
    public function collection()
    {
        return new Collection([
            [
                'Pulpen Standard', // nama_barang
                '',                // kode_barang (KOSONGIN BIAR AUTO)
                'Alat Tulis',      // kategori
                'Pices',           // satuan
                '10',              // min_stock
                '2500'             // harga_modal
            ],
        ]);
    }

    // 2. Judul Kolomnya (Header)
    public function headings(): array
    {
        return [
            'nama_barang',
            'kode_barang',
            'kategori',
            'satuan',
            'min_stock',
            'harga_modal',
        ];
    }
}
