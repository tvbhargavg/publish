<?php

namespace App\Http\Controllers\Internal;

use App\Exceptions\PolicyException;
use App\Http\Controllers\Controller;
use App\Models\Tunnel;
use App\Models\User;
use App\Services\TunnelAuthorizer;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

/** Endpoints for the Go tunnel server only (shared-secret protected, never public). */
class InternalController extends Controller
{
    public function validateToken(Request $r)
    {
        $plain = (string) $r->input('token', '');
        $t = $plain !== '' && strlen($plain) < 256 ? PersonalAccessToken::findToken($plain) : null;
        if (! $t || ($t->expires_at && $t->expires_at->isPast())) {
            return response()->json(['error' => 'invalid_token'], 401);
        }
        $user = $t->tokenable;
        if (! $user instanceof User || $user->isDisabled() || ! $user->hasVerifiedEmail()) {
            return response()->json(['error' => 'invalid_token'], 401);
        }
        if (! $t->last_used_at || $t->last_used_at->lt(now()->subMinute())) {
            $t->forceFill(['last_used_at' => now()])->save();
        }

        return ['user_id' => (string) $user->id, 'token_id' => (string) $t->id];
    }

    public function authorizeTunnel(Request $r, TunnelAuthorizer $authorizer)
    {
        $d = $r->validate(['user_id' => 'required', 'token_id' => 'nullable', 'requested_subdomain' => 'nullable|string|max:63', 'local_port' => 'required|integer|min:1|max:65535']);
        $user = User::find($d['user_id']);
        if (! $user) {
            return response()->json(['error' => 'unknown_user'], 401);
        }
        try {
            $t = $authorizer->authorize($user, isset($d['token_id']) ? (int) $d['token_id'] : null, $d['requested_subdomain'] ?? null, (int) $d['local_port']);
        } catch (PolicyException $e) {
            return response()->json(['error' => $e->getMessage()], $e->status);
        }

        return ['tunnel_id' => $t->id, 'subdomain' => $t->subdomain];
    }

    public function closed(Request $r, string $id)
    {
        $d = $r->validate(['requests' => 'integer|min:0', 'bytes_in' => 'integer|min:0', 'bytes_out' => 'integer|min:0', 'reason' => 'nullable|string|max:64']);
        $t = Tunnel::find($id);
        if ($t) {
            $t->update(['status' => 'closed', 'closed_at' => now(), 'close_reason' => $t->close_reason ?? ($d['reason'] ?? null),
                'requests' => $d['requests'] ?? 0, 'bytes_in' => $d['bytes_in'] ?? 0, 'bytes_out' => $d['bytes_out'] ?? 0]);
        }

        return response()->noContent();
    }
}
