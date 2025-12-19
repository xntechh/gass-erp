<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Unit extends Model
{
    use HasFactory;
    protected $guarded = [];

    public function items()
    {
        // Karena di tabel items ada kolom unit_id
        return $this->hasMany(Item::class);
    }
}
