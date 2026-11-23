<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tunnel;
use App\Services\Audit;
use App\Services\TunnelAuthorizer;
use App\Services\TunnelRegistry;
use Illuminate\Http\Request;

class TunnelController extends Controller
{
    public function __construct(private TunnelRegistry $registry, private TunnelAuthorizer $authorizer) {}

    public static function present(Tunnel $t, ?array $live = null): array
    {
        return [
            'id' => $t->id, 'subdomain' => $t->subdomain, 'url' => $t->publicUrl(), 'local_port' => $t->local_port,
            'status' => $t->status, 'started_at' => $t->started_at, 'closed_at' => $t->closed_at, 'close_reason' => $t->close_reason,
            'requests' => $t->requests, 'bytes_in' => $t->bytes_in, 'bytes_out' => $t->bytes_out,
            'last_heartbeat' => $live ? $live['last_heartbeat'] : null,
        ];
    }

    public function index(Request $r)
    {
        $this->authorizer->reapStale($r->user());

        return $r->user()->tunnels()->active()->latest('started_at')->get()
            ->map(fn (Tunnel $t) => self::present($t, $this->registry->get($t->subdomain)));
    }

    public function history(Request $r)
    {
        return $r->user()->tunnels()->latest('started_at')->paginate(25)->through(fn (Tunnel $t) => self::present($t));
    }

    public function destroy(Request $r, string $id)
    {
        $t = Tunnel::active()->whereKey($id)->where('user_id', $r->user()->id)->firstOrFail();
        $this->registry->remove($t->subdomain);
        Audit::log($r->user(), 'tunnel.disconnected', 'tunnel', $t->id, ['subdomain' => $t->subdomain]);

        return response()->noContent();
    }

    public function usage(Request $r)
    {
        return ['period_start' => now()->startOfMonth()->toDateString(), ...$r->user()->monthlyUsage(), 'monthly_bytes_limit' => $r->user()->monthly_bytes_limit];
    }
}
