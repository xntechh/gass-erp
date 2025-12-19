<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// 👇 Pastikan ada "implements FilamentUser"
class User extends Authenticatable implements FilamentUser
{
    use HasFactory, Notifiable, LogsActivity;

    protected $guarded = [];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    // 👇 INI KUNCI PINTU GERBANGNYA (WAJIB ADA) 👇
    public function canAccessPanel(Panel $panel): bool
    {
        // Izinkan SEMUA user yang punya role (ADMIN/STAFF) untuk login
        // Kalau kolom role kosong, baru ditolak.
        return ! is_null($this->role);

        // ATAU kalau mau lebih ketat:
        // return $this->role === 'ADMIN' || $this->role === 'STAFF';
    }

    // ... (fungsi activity log dll biarin aja di bawah)
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['*']);
    }
}
