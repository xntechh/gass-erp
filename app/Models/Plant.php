<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plant extends Model
{
    use HasFactory;

    protected $guarded = [];

    // Relasi: Satu Plant punya Banyak Warehouse
    public function warehouses(): HasMany
    {
        return $this->hasMany(Warehouse::class);
    }
}
