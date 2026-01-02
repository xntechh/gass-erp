<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany; // Import ini

class Department extends Model
{
    use HasFactory;

    protected $guarded = [];

    // Relasi: Satu Departemen punya Banyak User
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
