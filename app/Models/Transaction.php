<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Illuminate\Database\QueryException;

class Transaction extends Model
{
    use HasFactory, LogsActivity;

    protected $guarded = [];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'type', 'category', 'warehouse_id'])
            ->logOnlyDirty();
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
        // 1. GENERATE NOMOR TRANSAKSI (pakai trx_date, bukan now())
        static::creating(function (Transaction $transaction) {
            $type = $transaction->type; // IN atau OUT
            $date = $transaction->trx_date ? Carbon::parse($transaction->trx_date) : now();
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

        // 1b. Proteksi: STAFF tidak boleh set status APPROVED (anti-bypass)
        static::saving(function (Transaction $transaction) {
            $user = auth()->user();
            if ($user && ($user->role ?? null) !== 'ADMIN' && $transaction->status === 'APPROVED') {
                $transaction->status = 'DRAFT';
            }
        });

        // 1c. Proteksi: Transaksi APPROVED tidak boleh dihapus (stok sudah berubah)
        static::deleting(function (Transaction $transaction) {
            if ($transaction->status === 'APPROVED') {
                throw new \RuntimeException('Transaksi APPROVED tidak boleh dihapus.');
            }
        });

        // 2. EKSEKUSI STOK & HARGA SAAT APPROVED (transisi DRAFT -> APPROVED)
        static::updated(function (Transaction $transaction) {
            // Cek apakah baru saja di-approve
            if ($transaction->status === 'APPROVED' && $transaction->getOriginal('status') !== 'APPROVED') {
                DB::transaction(function () use ($transaction) {
                    // A. Mutasi stok fisik
                    $transaction->applyStockMutation();

                    // B. Update Moving Average (khusus IN)
                    if ($transaction->type === 'IN') {
                        $transaction->updateMovingAverage();
                    }
                });
            }
        });
    }

    public function applyStockMutation(): void
    {
        // IMPORTANT: Mutasi stok harus aman dari race-condition + stok minus.
        $this->loadMissing(['details.item']);

        DB::transaction(function () {
            foreach ($this->details as $detail) {
                // Lock baris stok (kalau ada) supaya tidak tabrakan antar request
                $stock = InventoryStock::where('warehouse_id', $this->warehouse_id)
                    ->where('item_id', $detail->item_id)
                    ->lockForUpdate()
                    ->first();

                // Kalau belum ada stok row-nya, bikin dulu (dan handle kemungkinan race karena unique index)
                if (! $stock) {
                    try {
                        $stock = InventoryStock::create([
                            'warehouse_id' => $this->warehouse_id,
                            'item_id'      => $detail->item_id,
                            'quantity'     => 0,
                        ]);
                    } catch (QueryException $e) {
                        // Kemungkinan besar karena ada request lain yang duluan create.
                        $stock = InventoryStock::where('warehouse_id', $this->warehouse_id)
                            ->where('item_id', $detail->item_id)
                            ->lockForUpdate()
                            ->first();
                    }
                }

                if ($this->type === 'IN') {
                    $stock->increment('quantity', $detail->quantity);
                    continue;
                }

                // OUT: blok stok minus
                if ($stock->quantity < $detail->quantity) {
                    $name = $detail->item?->name ?? ("Item ID " . $detail->item_id);
                    throw new \RuntimeException("Stok tidak cukup untuk: {$name}. Tersedia {$stock->quantity}, minta {$detail->quantity}.");
                }

                $stock->decrement('quantity', $detail->quantity);
            }
        });
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

            // ((StokLama * HargaLama) + (StokMasuk * HargaMasuk)) / TotalStok
            $totalNilaiLama = $stokLama * $avgLama;
            $totalNilaiMasuk = $qtyMasuk * $hargaMasuk;

            if ($stokBaru > 0) {
                $newAvg = ($totalNilaiLama + $totalNilaiMasuk) / $stokBaru;
                $item->updateQuietly(['avg_cost' => $newAvg]);
            }
        }
    }
}
