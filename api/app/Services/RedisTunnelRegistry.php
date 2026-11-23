<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;

class RedisTunnelRegistry implements TunnelRegistry
{
    public function get(string $subdomain): ?array
    {
        $h = Redis::connection('registry')->hgetall('tunnel:'.$subdomain);
        if (! $h) {
            return null;
        }

        return [
            'tunnel_id' => $h['tunnel_id'] ?? '',
            'user_id' => $h['user_id'] ?? '',
            'local_port' => (int) ($h['local_port'] ?? 0),
            'status' => $h['status'] ?? 'unknown',
            'created_at' => (int) ($h['created_at'] ?? 0),
            'last_heartbeat' => (int) ($h['last_heartbeat'] ?? 0),
        ];
    }

    public function remove(string $subdomain): void
    {
        Redis::connection('registry')->del('tunnel:'.$subdomain);
    }
}
