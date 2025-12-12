<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany; // <--- JANGAN HILANG

class Warehouse extends Model
{
    use HasFactory;

    protected $guarded = [];

    // Relasi ke Plant (Induk)
    public function plant(): BelongsTo
    {
        return $this->belongsTo(Plant::class);
    }

    // 👇👇👇 INI YANG HILANG DAN BIKIN ERROR DASHBOARD 👇👇👇
    public function inventoryStocks(): HasMany
    {
        return $this->hasMany(InventoryStock::class);
    }
}