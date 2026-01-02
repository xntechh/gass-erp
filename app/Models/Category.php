<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany; // <--- Import ini

class Category extends Model
{
    use HasFactory;

    protected $guarded = [];

    // 👇 TAMBAHKAN INI JIKA BELUM ADA
    public function items(): HasMany
    {
        return $this->hasMany(Item::class);
    }
}
