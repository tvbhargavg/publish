<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Audit;
use Illuminate\Http\Request;

class TokenController extends Controller
{
    public function index(Request $r)
    {
        return $r->user()->tokens()->get(['id', 'name', 'last_used_at', 'expires_at', 'created_at']);
    }

    public function store(Request $r)
    {
        $d = $r->validate(['name' => 'required|string|max:64', 'expires_days' => 'nullable|integer|min:1|max:3650']);
        abort_if($r->user()->tokens()->count() >= 25, 422, 'Too many tokens');
        $token = $r->user()->createToken($d['name'], ['*'], isset($d['expires_days']) ? now()->addDays($d['expires_days']) : null);
        Audit::log($r->user(), 'token.created', 'token', $token->accessToken->id, ['name' => $d['name']]);

        // The plain-text token is shown exactly once.
        return response()->json(['id' => $token->accessToken->id, 'token' => $token->plainTextToken], 201);
    }

    public function destroy(Request $r, int $id)
    {
        $t = $r->user()->tokens()->whereKey($id)->firstOrFail(); // own tokens only
        $t->delete();
        Audit::log($r->user(), 'token.revoked', 'token', $id);

        return response()->noContent();
    }
}
