<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\InventoryStock;
use App\Models\Department;
use App\Models\Warehouse; // Jangan lupa import Warehouse
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Transaction extends Model
{
    use HasFactory;
    use LogsActivity;

    protected $guarded = [];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['*'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function details(): HasMany
    {
        return $this->hasMany(TransactionDetail::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    // 👇👇👇 LOGIC PENTING (DIPERBAIKI) 👇👇👇
    protected static function booted(): void
    {
        static::updated(function (Transaction $transaction) {

            // Cek 1: Apakah status BERUBAH jadi APPROVED?
            // Kita cek 'getOriginal' biar gak jalan berkali-kali kalau di-save ulang
            if ($transaction->status === 'APPROVED' && $transaction->getOriginal('status') !== 'APPROVED') {

                // ---------------------------------------------------------
                // 1. UPDATE STOK FISIK (Jalankan Fungsi Mutasi)
                // ---------------------------------------------------------
                $transaction->applyStockMutation();
                // ^^^ INI PENTING! Stok fisik harus nambah dulu.

                // ---------------------------------------------------------
                // 2. UPDATE HARGA RATA-RATA (MOVING AVERAGE) - Khusus IN
                // ---------------------------------------------------------
                if ($transaction->type === 'IN') {

                    foreach ($transaction->details as $detail) {
                        $item = $detail->item;

                        // A. Data Transaksi Baru
                        $qtyMasuk   = $detail->quantity;
                        $hargaMasuk = $detail->price; // Harga beli baru

                        // B. Data Stok Saat Ini (Setelah ditambah poin 1 di atas)
                        $stokSekarang = $item->stocks()->sum('quantity');

                        // C. Hitung Mundur Stok Lama (Sebelum barang ini masuk)
                        // Rumus: Stok Sekarang - Qty Masuk
                        $stokLama = $stokSekarang - $qtyMasuk;

                        // Safety: Jangan sampai minus
                        if ($stokLama < 0) $stokLama = 0;

                        $avgLama = $item->avg_cost;

                        // D. Rumus Moving Average
                        // (Total Nilai Lama + Total Nilai Baru) / Total Stok Baru
                        $totalNilaiLama = $stokLama * $avgLama;
                        $totalNilaiBaru = $qtyMasuk * $hargaMasuk;

                        // Pembagi adalah Stok Sekarang (Total Gabungan)
                        $totalStok = $stokSekarang;

                        if ($totalStok > 0) {
                            $newAvg = ($totalNilaiLama + $totalNilaiBaru) / $totalStok;

                            // Update Item (Pakai Quietly biar gak memicu event lain/looping)
                            $item->updateQuietly(['avg_cost' => $newAvg]);
                        }
                    }
                }
            }
        });
    }

    // 👇 Fungsi ini dipanggil otomatis di dalam booted() di atas
    public function applyStockMutation(): void
    {
        foreach ($this->details as $detail) {
            $stock = InventoryStock::firstOrCreate(
                [
                    'warehouse_id' => $this->warehouse_id,
                    'item_id' => $detail->item_id,
                ],
                ['quantity' => 0]
            );

            if ($this->type === 'IN') {
                $stock->increment('quantity', $detail->quantity);
            } else {
                $stock->decrement('quantity', $detail->quantity);
            }
        }
    }
}
