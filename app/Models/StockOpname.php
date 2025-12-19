<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class StockOpname extends Model
{
    use HasFactory, LogsActivity;

    // Biar gak ribet, kita bebaskan fieldnya diisi apa aja
    protected $guarded = [];

    /**
     * LOGIC AUDIT: Catat siapa yang utak-atik data opname.
     * Penting buat transparansi di departemen HRGA lo.
     */

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'warehouse_id', 'opname_date'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    // --- RELASI DATA ---

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function details(): HasMany
    {
        return $this->hasMany(StockOpnameDetail::class);
    }

    // --- LOGIC OTOMATIS (BOOTED) ---

    protected static function booted(): void
    {
        /**
         * 1. AUTO-NUMBERING
         * Bikin nomor dokumen rapi: SO/20251219/001
         */
        static::creating(function (StockOpname $opname) {
            $date = now()->format('Ymd');

            // Cari nomor urut terakhir hari ini
            $lastTrx = static::whereDate('created_at', now()->toDateString())
                ->latest()
                ->first();

            $lastNumber = 0;
            if ($lastTrx && $lastTrx->code) {
                $lastNumber = (int) substr($lastTrx->code, -3);
            }

            $newNumber = str_pad($lastNumber + 1, 3, '0', STR_PAD_LEFT);
            $opname->code = "SO/{$date}/{$newNumber}";
        });

        /**
         * 2. UPDATE STOK REAL-TIME
         * Jalan otomatis cuma kalau status berubah ke PROCESSED.
         */
        static::updated(function (StockOpname $opname) {
            // Pengaman: Cek perubahan status dari DRAFT ke PROCESSED
            if ($opname->status === 'PROCESSED' && $opname->getOriginal('status') !== 'PROCESSED') {

                DB::transaction(function () use ($opname) {
                    foreach ($opname->details as $detail) {
                        // Update stok di gudang sesuai hasil hitung fisik auditor
                        InventoryStock::updateOrCreate(
                            [
                                'warehouse_id' => $opname->warehouse_id,
                                'item_id' => $detail->item_id,
                            ],
                            [
                                'quantity' => $detail->physical_qty,
                                'updated_at' => now(),
                            ]
                        );
                    }
                });
            }
        });
    }

    protected $appends = [
        'accuracy',
        'total_valuation',
        'variance',
    ];

    // 1. Accessor Akurasi (Sudah ada, pastikan logic-nya bener)
    public function getAccuracyAttribute(): float
    {
        $totalItems = $this->details->count();
        if ($totalItems === 0) return 0;

        // Hitung barang yang Qty Sistem == Qty Fisik
        $matchingItems = $this->details->filter(function ($detail) {
            return (int)$detail->system_qty === (int)$detail->physical_qty;
        })->count();

        return round(($matchingItems / $totalItems) * 100, 2);
    }

    // 2. Accessor Total Aset Fisik (Cek harga barang!)
    public function getTotalValuationAttribute(): float
    {
        return $this->details->sum(function ($detail) {
            // Kalau avg_cost di tabel items kosong, ini bakal 0
            return $detail->physical_qty * ($detail->item->avg_cost ?? 0);
        });
    }

    // 3. Accessor Selisih Nilai (Rp) - INI YANG TADI KETINGGALAN
    public function getVarianceAttribute(): float
    {
        return $this->details->sum(function ($detail) {
            $selisihQty = $detail->physical_qty - $detail->system_qty;
            $harga = $detail->item->avg_cost ?? 0;
            return $selisihQty * $harga;
        });
    }
}
