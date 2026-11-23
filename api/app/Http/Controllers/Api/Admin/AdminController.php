<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\TunnelController;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Tunnel;
use App\Models\User;
use App\Services\Audit;
use App\Services\TunnelAuthorizer;
use App\Services\TunnelRegistry;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function users()
    {
        return User::orderBy('id')->paginate(50, ['id', 'name', 'email', 'is_admin', 'max_tunnels', 'max_reservations', 'monthly_bytes_limit', 'disabled_at', 'created_at']);
    }

    public function createUser(Request $r)
    {
        $d = $r->validate(['name' => 'required|string|max:100', 'email' => 'required|email|max:190|unique:users', 'password' => 'required|string|min:10|max:200',
            'max_tunnels' => 'sometimes|integer|min:0|max:1000', 'max_reservations' => 'sometimes|integer|min:0|max:1000', 'monthly_bytes_limit' => 'sometimes|nullable|integer|min:0']);
        $u = User::create($d);
        Audit::log($r->user(), 'user.created', 'user', $u->id);

        return response()->json(['id' => $u->id], 201);
    }

    public function updateUser(Request $r, User $user)
    {
        $d = $r->validate(['max_tunnels' => 'sometimes|integer|min:0|max:1000', 'max_reservations' => 'sometimes|integer|min:0|max:1000',
            'monthly_bytes_limit' => 'sometimes|nullable|integer|min:0', 'disabled' => 'sometimes|boolean']);
        if (array_key_exists('disabled', $d)) {
            abort_if($user->id === $r->user()->id, 422, 'You cannot disable yourself');
            $user->disabled_at = $d['disabled'] ? now() : null;
            if ($d['disabled']) {
                $user->tokens()->delete(); // kill API sessions; live tunnels drop at next revalidation
            }
            unset($d['disabled']);
        }
        $user->fill($d)->save();
        Audit::log($r->user(), 'user.updated', 'user', $user->id, $r->only(['max_tunnels', 'max_reservations', 'monthly_bytes_limit', 'disabled']));

        return response()->noContent();
    }

    public function tunnels(TunnelAuthorizer $a, TunnelRegistry $reg)
    {
        $a->reapStale();

        return Tunnel::active()->with('user:id,email')->latest('started_at')->get()
            ->map(fn (Tunnel $t) => [...TunnelController::present($t, $reg->get($t->subdomain)), 'user' => $t->user?->email]);
    }

    public function disconnect(Request $r, TunnelRegistry $reg, string $id)
    {
        $t = Tunnel::active()->findOrFail($id);
        $reg->remove($t->subdomain);
        Audit::log($r->user(), 'tunnel.disconnected', 'tunnel', $t->id, ['subdomain' => $t->subdomain, 'admin' => true]);

        return response()->noContent();
    }

    public function audit()
    {
        return AuditLog::with('actor:id,email')->latest('id')->paginate(50);
    }
}
