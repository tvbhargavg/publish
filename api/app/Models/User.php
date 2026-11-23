<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'max_tunnels', 'max_reservations', 'monthly_bytes_limit'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'disabled_at' => 'datetime',
            'is_admin' => 'boolean',
            'password' => 'hashed',
        ];
    }

    public function isDisabled(): bool
    {
        return $this->disabled_at !== null;
    }

    public function tunnels(): HasMany
    {
        return $this->hasMany(Tunnel::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    /** Bytes (in+out) and requests this calendar month. */
    public function monthlyUsage(): array
    {
        $q = $this->tunnels()->where('started_at', '>=', now()->startOfMonth());

        return [
            'requests' => (int) $q->sum('requests'),
            'bytes_in' => (int) (clone $q)->sum('bytes_in'),
            'bytes_out' => (int) (clone $q)->sum('bytes_out'),
        ];
    }
}
