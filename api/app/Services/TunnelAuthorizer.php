<?php

namespace App\Services;

use App\Exceptions\PolicyException;
use App\Models\Reservation;
use App\Models\Tunnel;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TunnelAuthorizer
{
    public function __construct(private TunnelRegistry $registry) {}

    public static function validSubdomain(string $s): bool
    {
        return (bool) preg_match('/^[a-z0-9][a-z0-9-]{1,61}[a-z0-9]$/', $s);
    }

    /** Close DB rows that claim to be active but are gone from the registry. */
    public function reapStale(?User $user = null): int
    {
        $grace = now()->subSeconds(config('tunnel.stale_grace_seconds'));
        $q = Tunnel::active()->where('started_at', '<', $grace);
        if ($user) {
            $q->where('user_id', $user->id);
        }
        $n = 0;
        foreach ($q->get() as $t) {
            $e = $this->registry->get($t->subdomain);
            if (! $e || $e['tunnel_id'] !== $t->id) {
                $t->update(['status' => 'closed', 'closed_at' => now(), 'close_reason' => 'expired']);
                $n++;
            }
        }

        return $n;
    }

    /** @throws PolicyException */
    public function authorize(User $user, ?int $tokenId, ?string $requested, int $port): Tunnel
    {
        if ($user->isDisabled()) {
            throw new PolicyException(403, 'account disabled');
        }
        $limit = $user->monthly_bytes_limit;
        if ($limit !== null) {
            $u = $user->monthlyUsage();
            if ($u['bytes_in'] + $u['bytes_out'] >= $limit) {
                throw new PolicyException(429, 'monthly bandwidth limit reached');
            }
        }
        $this->reapStale($user);

        return DB::transaction(function () use ($user, $tokenId, $requested, $port) {
            User::whereKey($user->id)->lockForUpdate()->first(); // serialize per-user allocation

            if ($requested !== null && $requested !== '') {
                $sub = strtolower($requested);
                if (! self::validSubdomain($sub) || in_array($sub, config('tunnel.reserved'), true)) {
                    throw new PolicyException(422, 'invalid or reserved subdomain');
                }
                $owner = Reservation::where('subdomain', $sub)->value('user_id');
                if ($owner !== null && (int) $owner !== $user->id) {
                    throw new PolicyException(409, 'subdomain reserved by another user');
                }
                if ($owner === null && ! config('tunnel.allow_unreserved_custom')) {
                    throw new PolicyException(409, 'custom subdomains must be reserved first');
                }
                if (Tunnel::active()->where('subdomain', $sub)->where('user_id', '!=', $user->id)->exists()) {
                    throw new PolicyException(409, 'subdomain in use');
                }
            } else {
                do {
                    $sub = Str::lower(Str::random(8));
                } while (
                    ! self::validSubdomain($sub)
                    || Reservation::where('subdomain', $sub)->exists()
                    || Tunnel::active()->where('subdomain', $sub)->exists()
                );
            }

            $count = Tunnel::active()->where('user_id', $user->id)->where('subdomain', '!=', $sub)->count();
            if ($count >= $user->max_tunnels) {
                throw new PolicyException(429, 'tunnel limit reached');
            }

            // Reconnect of the same subdomain replaces the previous session's row.
            Tunnel::active()->where('user_id', $user->id)->where('subdomain', $sub)
                ->update(['status' => 'closed', 'closed_at' => now(), 'close_reason' => 'replaced']);

            return Tunnel::create([
                'user_id' => $user->id, 'token_id' => $tokenId, 'subdomain' => $sub,
                'local_port' => $port, 'status' => 'active', 'started_at' => now(),
            ]);
        });
    }
}
