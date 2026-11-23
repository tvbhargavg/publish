<?php

namespace Tests\Support;

use App\Services\TunnelRegistry;

class FakeRegistry implements TunnelRegistry
{
    public array $entries = [];

    public array $removed = [];

    public function get(string $subdomain): ?array
    {
        return $this->entries[$subdomain] ?? null;
    }

    public function remove(string $subdomain): void
    {
        $this->removed[] = $subdomain;
        unset($this->entries[$subdomain]);
    }

    public function put(string $sub, string $tunnelId, string $userId = '1'): void
    {
        $this->entries[$sub] = ['tunnel_id' => $tunnelId, 'user_id' => $userId, 'local_port' => 8000, 'status' => 'online', 'created_at' => time(), 'last_heartbeat' => time()];
    }
}
