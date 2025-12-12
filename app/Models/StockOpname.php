<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class StockOpname extends Model
{
    use LogsActivity;
    use HasFactory;
    protected $guarded = [];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['*']) // Pantau SEMUA kolom
            ->logOnlyDirty() // Cuma catat yang berubah aja (hemat database)
            ->dontSubmitEmptyLogs(); // Kalau gak ada yang berubah, jangan nyampah
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function details(): HasMany
    {
        return $this->hasMany(StockOpnameDetail::class);
    }

    // Hitung Total Selisih (Rupiah)
    // Rumus: (Fisik - Sistem) * Harga Modal
    public function getVarianceAttribute()
    {
        return $this->details->sum(function ($detail) {
            $selisihQty = $detail->physical_qty - $detail->system_qty;
            // Pastikan item punya avg_cost, kalau gak ada anggap 0
            $harga = $detail->item->avg_cost ?? 0;
            return $selisihQty * $harga;
        });
    }

    // Hitung Akurasi (%)
    // Rumus: Jumlah Item yang MATCH / Total Item * 100
    public function getAccuracyAttribute()
    {
        $totalItems = $this->details->count();
        if ($totalItems == 0) return 100; // Kalau kosong dianggap sempurna

        $matchItems = $this->details->filter(function ($detail) {
            return $detail->physical_qty == $detail->system_qty;
        })->count();

        return round(($matchItems / $totalItems) * 100, 2);
    }

    // Hitung Total Nilai Aset Fisik (Yang beneran ada)
    // Rumus: Sum (Fisik * Harga Modal)
    public function getTotalValuationAttribute()
    {
        return $this->details->sum(function ($detail) {
            $qtyFisik = $detail->physical_qty;
            // Ambil harga modal (kalau gak ada anggap 0)
            $harga = $detail->item->avg_cost ?? 0;

            return $qtyFisik * $harga;
        });
    }

    // --- LOGIC PENYESUAIAN STOK OTOMATIS (MAGIC) ---
    protected static function booted(): void
    {
        static::updated(function (StockOpname $opname) {
            // Jika status berubah jadi PROCESSED, paksa stok sesuai fisik
            if ($opname->isDirty('status') && $opname->status === 'PROCESSED') {

                foreach ($opname->details as $detail) {
                    // Update tabel InventoryStock
                    // Kita cari record stok yang sesuai, lalu update angkanya
                    InventoryStock::updateOrCreate(
                        [
                            'warehouse_id' => $opname->warehouse_id,
                            'item_id' => $detail->item_id,
                        ],
                        [
                            'quantity' => $detail->physical_qty // TIMPA JADI STOK FISIK
                        ]
                    );
                }
            }
        });
    }
}
