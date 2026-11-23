<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Tunnel extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'closed_at' => 'datetime'];
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('status', 'active');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function publicUrl(): string
    {
        $scheme = config('tunnel.tls') ? 'https' : 'http';
        $port = config('tunnel.public_port');
        $host = $this->subdomain.'.'.config('tunnel.base_domain');
        $default = ($scheme === 'https' && $port === 443) || ($scheme === 'http' && $port === 80);

        return $scheme.'://'.$host.($default ? '' : ':'.$port);
    }
}
