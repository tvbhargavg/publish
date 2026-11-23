<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
    public function show(Request $r)
    {
        return view('profile', ['user' => $r->user()]);
    }

    public function update(Request $r)
    {
        $r->merge(['email' => strtolower(trim((string) $r->input('email')))]);
        $u = $r->user();
        $d = $r->validate([
            'name' => 'required|string|max:100',
            'email' => ['required', 'email:rfc', 'max:190', Rule::unique('users', 'email')->ignore($u->id)],
        ]);
        $emailChanged = $d['email'] !== $u->email;
        $u->fill($d);
        if ($emailChanged) {
            $u->email_verified_at = null; // must confirm the new address
        }
        $u->save();
        Audit::log($u, 'profile.updated', 'user', $u->id, ['email_changed' => $emailChanged]);
        if ($emailChanged) {
            $u->trySendVerification();

            return redirect()->route('verification.notice')->with('ok', 'Profile saved. Confirm your new email address to keep using Publish.');
        }

        return back()->with('ok', 'Profile updated.');
    }

    public function theme(Request $r)
    {
        $d = $r->validate(['theme' => 'required|in:light,dark,system']);
        $r->user()->forceFill(['theme' => $d['theme']])->save();

        return $r->expectsJson() ? response()->noContent() : back()->with('ok', 'Appearance saved.');
    }

    public function password(Request $r)
    {
        $d = $r->validate([
            'current_password' => 'required|string',
            'password' => ['required', 'string', 'confirmed', Password::min(10)->max(200)],
        ]);
        $u = $r->user();
        if (! Hash::check($d['current_password'], $u->password)) {
            return back()->withErrors(['current_password' => 'Current password is incorrect.'], 'password');
        }
        $u->password = $d['password'];
        $u->save();
        Audit::log($u, 'profile.password_changed', 'user', $u->id);

        return back()->with('ok', 'Password changed.');
    }
}
