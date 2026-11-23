<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'max_tunnels', 'max_reservations', 'monthly_bytes_limit'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
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

    /** Concurrent tunnels allowed: the free quota, or the admin-set limit for premium users. */
    public function tunnelLimit(): int
    {
        return $this->tunnel_access ? (int) $this->max_tunnels : (int) config('tunnel.free_max_tunnels');
    }

    /** Send the verification mail; a mail outage must never break sign-up (the user can resend). */
    public function trySendVerification(): bool
    {
        try {
            $this->sendEmailVerificationNotification();

            return true;
        } catch (\Throwable $e) {
            report($e);

            return false;
        }
    }

    /** Emails are stored lowercase so one mailbox = one account. */
    public function setEmailAttribute($value): void
    {
        $this->attributes['email'] = strtolower(trim((string) $value));
    }

    /** Reservation quota: free users hold none, premium users get the admin-set number. */
    public function reservationLimit(): int
    {
        return $this->tunnel_access ? (int) $this->max_reservations : (int) config('tunnel.free_max_reservations');
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
