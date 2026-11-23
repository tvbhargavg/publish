<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;

class Audit
{
    /** Record an administrative/security-relevant action. Never pass secrets in $meta. */
    public static function log(?User $actor, string $action, ?string $targetType = null, string|int|null $targetId = null, array $meta = []): void
    {
        AuditLog::create([
            'actor_id' => $actor?->id,
            'action' => $action,
            'target_type' => $targetType,
            'target_id' => $targetId !== null ? (string) $targetId : null,
            'metadata' => $meta ?: null,
            'ip' => request()?->ip(),
        ]);
    }
}
