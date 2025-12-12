<?php

namespace App\Imports;

use App\Models\Item;
use App\Models\Category; // Butuh ini buat cari ID kategori
use App\Models\Unit;     // Butuh ini buat cari ID satuan
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow; // Biar bisa baca header baris 1

class ItemImport implements ToModel, WithHeadingRow
{
    public function model(array $row)
    {
        // 0. Bersihkan Spasi (Trim) biar " Pcs " terbaca "Pcs"
        $namaKategori = trim($row['kategori'] ?? '');
        $namaSatuan   = trim($row['satuan'] ?? '');

        // 1. Cari atau Buat Kategori
        // Pastikan Model Category $guarded = [];
        $category = Category::firstOrCreate(['name' => $namaKategori]);

        // 2. Cari atau Buat Satuan
        // Pastikan Model Unit $guarded = [];
        $unit = Unit::firstOrCreate(['name' => $namaSatuan]);

        // 3. LOGIC KODE BARANG
        if (empty($row['kode_barang'])) {
            // -- KASUS BARANG BARU (Auto Generate) --
            $prefix = $category->code ?? 'GEN';
            $urutan = Item::where('category_id', $category->id)->count() + 1;
            $generatedCode = $prefix . str_pad($urutan, 6, '0', STR_PAD_LEFT);
        } else {
            // -- KASUS UPDATE (Pake Kode dari Excel) --
            $generatedCode = trim($row['kode_barang']);
        }

        // 4. EKSEKUSI: UPDATE JIKA ADA, BUAT JIKA TIDAK (Upsert)
        return Item::updateOrCreate(
            ['code' => $generatedCode], // Kunci Pencarian: Cek berdasarkan KODE
            [
                'name'        => $row['nama_barang'],
                'category_id' => $category->id,
                'unit_id'     => $unit->id, // <--- Ini yang bikin satuannya terupdate
                'min_stock'   => $row['min_stock'] ?? 0,
                'avg_cost'    => $row['harga_modal'] ?? 0,
                'is_active'   => true,
            ]
        );
    }
}
