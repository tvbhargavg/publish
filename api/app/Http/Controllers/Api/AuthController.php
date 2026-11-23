<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $r)
    {
        abort_unless(config('tunnel.allow_registration'), 404);
        $r->merge(['email' => strtolower(trim((string) $r->input('email')))]);
        $d = $r->validate(['name' => 'required|string|max:100', 'email' => 'required|email:rfc|max:190|unique:users,email', 'password' => 'required|string|min:10|max:200']);
        $d['email'] = strtolower($d['email']);
        abort_if(User::where('email', $d['email'])->exists(), 422, 'Email already registered');
        $user = User::create($d + ['max_tunnels' => config('tunnel.premium_default_max_tunnels')]);
        $user->trySendVerification();
        Audit::log($user, 'user.registered', 'user', $user->id);

        return response()->json(['id' => $user->id], 201);
    }

    /** Exchange credentials for an API token (used by dashboards/CLIs). */
    public function login(Request $r)
    {
        $r->merge(['email' => strtolower(trim((string) $r->input('email')))]);
        $d = $r->validate(['email' => 'required|email', 'password' => 'required|string', 'token_name' => 'sometimes|string|max:64']);
        $user = User::where('email', $d['email'])->first();
        if (! $user || ! Hash::check($d['password'], $user->password) || $user->isDisabled()) {
            throw ValidationException::withMessages(['email' => 'Invalid credentials.']);
        }
        if (! $user->hasVerifiedEmail()) {
            return response()->json(['message' => 'Verify your email first: check your inbox for the confirmation link.'], 403);
        }
        $token = $user->createToken($d['token_name'] ?? 'cli');
        Audit::log($user, 'auth.login', 'token', $token->accessToken->id);

        return response()->json(['token' => $token->plainTextToken, 'token_id' => $token->accessToken->id], 201);
    }

    public function logout(Request $r)
    {
        $t = $r->user()->currentAccessToken();
        Audit::log($r->user(), 'token.revoked', 'token', $t->id, ['via' => 'logout']);
        $t->delete();

        return response()->noContent();
    }

    public function me(Request $r)
    {
        $u = $r->user();

        return [
            'id' => $u->id, 'name' => $u->name, 'email' => $u->email, 'is_admin' => $u->is_admin, 'premium' => $u->tunnel_access,
            'limits' => ['max_tunnels' => $u->tunnelLimit(), 'max_reservations' => $u->max_reservations, 'monthly_bytes_limit' => $u->monthly_bytes_limit],
        ];
    }
}
