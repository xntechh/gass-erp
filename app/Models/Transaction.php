<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use Illuminate\Support\Facades\DB;

class Transaction extends Model
{
    use HasFactory, LogsActivity;

    protected $guarded = [];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'type', 'category', 'warehouse_id'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    // --- RELASI ---
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

    // --- LOGIC BOOTED ---
    protected static function booted(): void
    {
        // 1. GENERATE NOMOR TRANSAKSI (Solusi Error lo tadi)
        static::creating(function (Transaction $transaction) {
            $type = $transaction->type; // IN atau OUT
            $date = now();
            $yearMonth = $date->format('Y/m');

            // Cari urutan terakhir di bulan/tahun yang sama
            $lastTrx = static::where('type', $type)
                ->whereYear('trx_date', $date->year)
                ->whereMonth('trx_date', $date->month)
                ->latest()
                ->first();

            $lastNumber = $lastTrx ? (int) substr($lastTrx->code, -4) : 0;
            $newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);

            $transaction->code = "TRX/{$type}/{$yearMonth}/{$newNumber}";
        });

        // 2. EKSEKUSI STOK & HARGA SAAT APPROVED
        static::updated(function (Transaction $transaction) {
            // Cek apakah baru saja di-approve
            if ($transaction->status === 'APPROVED' && $transaction->getOriginal('status') !== 'APPROVED') {

                DB::transaction(function () use ($transaction) {
                    // A. Jalankan Mutasi Stok Fisik
                    $transaction->applyStockMutation();

                    // B. Jalankan Update Harga Rata-rata (Khusus IN)
                    if ($transaction->type === 'IN') {
                        $transaction->updateMovingAverage();
                    }
                });
            }
        });
    }

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

    public function updateMovingAverage(): void
    {
        foreach ($this->details as $detail) {
            $item = $detail->item;

            $qtyMasuk = $detail->quantity;
            $hargaMasuk = $detail->price;

            // Stok sekarang (sudah ditambah di applyStockMutation)
            $stokBaru = $item->stocks()->sum('quantity');
            $stokLama = $stokBaru - $qtyMasuk;
            if ($stokLama < 0) $stokLama = 0;

            $avgLama = $item->avg_cost;

            // Rumus Moving Average: 
            // ((StokLama * HargaLama) + (StokBaru * HargaBaru)) / TotalStok
            $totalNilaiLama = $stokLama * $avgLama;
            $totalNilaiMasuk = $qtyMasuk * $hargaMasuk;

            if ($stokBaru > 0) {
                $newAvg = ($totalNilaiLama + $totalNilaiMasuk) / $stokBaru;
                $item->updateQuietly(['avg_cost' => $newAvg]);
            }
        }
    }
}
