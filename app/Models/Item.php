<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany; // <--- Tambah ini
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use App\Models\InventoryStock;

class Item extends Model
{
    use HasFactory;
    protected $guarded = [];

    use LogsActivity;

    public function transactionDetails(): HasMany
    {
        // Item ini punya BANYAK Detail Transaksi
        return $this->hasMany(TransactionDetail::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['*']) // Pantau SEMUA kolom
            ->logOnlyDirty() // Cuma catat yang berubah aja (hemat database)
            ->dontSubmitEmptyLogs(); // Kalau gak ada yang berubah, jangan nyampah
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    // Ini yang TADI HILANG. Item harus tau stok dia ada berapa di tabel inventory.
    public function inventoryStocks(): HasMany
    {
        return $this->hasMany(InventoryStock::class);
    }

    // Relasi ke Satuan
    public function unit(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    // 👇 RELASI KE INVENTORY STOCKS 👇
    public function stocks(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(InventoryStock::class);
    }
}
