<?php

namespace App\Models;

// Pastikan baris ini ada!
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Plant extends Model
{
    use HasFactory;
    protected $guarded = [];

    public function warehouses()
    {

        return $this->hasMany(Warehouse::class);
    }
}
