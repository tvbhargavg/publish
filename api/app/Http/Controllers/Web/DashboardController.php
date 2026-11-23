<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Reservation;
use App\Models\Tunnel;
use App\Models\User;
use App\Services\Audit;
use App\Services\TunnelAuthorizer;
use App\Services\TunnelRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function __construct(private TunnelRegistry $registry, private TunnelAuthorizer $authorizer) {}

    public function loginForm()
    {
        return view('login');
    }

    public function login(Request $r)
    {
        $d = $r->validate(['email' => 'required|email', 'password' => 'required|string']);
        if (! Auth::attempt($d + ['disabled_at' => null], false)) {
            return back()->withErrors(['email' => 'Invalid credentials.'])->onlyInput('email');
        }
        $r->session()->regenerate();
        Audit::log($r->user(), 'auth.web_login', 'user', $r->user()->id);

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $r)
    {
        Auth::logout();
        $r->session()->invalidate();
        $r->session()->regenerateToken();

        return redirect()->route('login');
    }

    public function dashboard(Request $r)
    {
        $u = $r->user();
        $this->authorizer->reapStale($u);
        $active = $u->tunnels()->active()->latest('started_at')->get()->map(fn ($t) => [$t, $this->registry->get($t->subdomain)]);

        return view('dashboard', ['active' => $active, 'usage' => $u->monthlyUsage(), 'user' => $u]);
    }

    public function history(Request $r)
    {
        return view('history', ['tunnels' => $r->user()->tunnels()->latest('started_at')->paginate(25)]);
    }

    public function disconnect(Request $r, string $id)
    {
        $t = Tunnel::active()->whereKey($id)->where('user_id', $r->user()->id)->firstOrFail();
        $this->registry->remove($t->subdomain);
        Audit::log($r->user(), 'tunnel.disconnected', 'tunnel', $t->id, ['subdomain' => $t->subdomain]);

        return back()->with('ok', 'Tunnel will disconnect within seconds.');
    }

    public function tokens(Request $r)
    {
        return view('tokens', ['tokens' => $r->user()->tokens()->latest()->get(), 'new' => session('new_token')]);
    }

    public function createToken(Request $r)
    {
        $d = $r->validate(['name' => 'required|string|max:64', 'expires_days' => 'nullable|integer|min:1|max:3650']);
        abort_if($r->user()->tokens()->count() >= 25, 422, 'Too many tokens');
        $t = $r->user()->createToken($d['name'], ['*'], ! empty($d['expires_days']) ? now()->addDays((int) $d['expires_days']) : null);
        Audit::log($r->user(), 'token.created', 'token', $t->accessToken->id, ['name' => $d['name']]);

        return redirect()->route('tokens')->with('new_token', $t->plainTextToken);
    }

    public function revokeToken(Request $r, int $id)
    {
        $r->user()->tokens()->whereKey($id)->firstOrFail()->delete();
        Audit::log($r->user(), 'token.revoked', 'token', $id);

        return back()->with('ok', 'Token revoked. Any tunnel using it will be disconnected shortly.');
    }

    public function reservations(Request $r)
    {
        return view('reservations', ['items' => $r->user()->reservations()->get(), 'user' => $r->user()]);
    }

    public function createReservation(Request $r)
    {
        $sub = strtolower((string) $r->input('subdomain'));
        if (! TunnelAuthorizer::validSubdomain($sub) || in_array($sub, config('tunnel.reserved'), true)) {
            return back()->withErrors(['subdomain' => 'Invalid or reserved name (3-63 chars: a-z, 0-9, hyphen).']);
        }
        if ($r->user()->reservations()->count() >= $r->user()->max_reservations) {
            return back()->withErrors(['subdomain' => 'Reservation limit reached.']);
        }
        if (Reservation::where('subdomain', $sub)->exists()) {
            return back()->withErrors(['subdomain' => 'Already reserved.']);
        }
        $res = $r->user()->reservations()->create(['subdomain' => $sub]);
        Audit::log($r->user(), 'reservation.created', 'reservation', $res->id, ['subdomain' => $sub]);

        return back()->with('ok', 'Reserved.');
    }

    public function deleteReservation(Request $r, int $id)
    {
        $res = $r->user()->reservations()->whereKey($id)->firstOrFail();
        $res->delete();
        Audit::log($r->user(), 'reservation.deleted', 'reservation', $id, ['subdomain' => $res->subdomain]);

        return back()->with('ok', 'Released.');
    }

    // ---- admin ----
    public function adminOverview()
    {
        $this->authorizer->reapStale();
        $active = Tunnel::active()->with('user:id,email')->latest('started_at')->get()->map(fn ($t) => [$t, $this->registry->get($t->subdomain)]);

        return view('admin.overview', ['users' => User::orderBy('id')->get(), 'active' => $active]);
    }

    public function adminCreateUser(Request $r)
    {
        $d = $r->validate(['name' => 'required|string|max:100', 'email' => 'required|email|max:190|unique:users', 'password' => 'required|string|min:10|max:200']);
        $u = User::create($d);
        Audit::log($r->user(), 'user.created', 'user', $u->id);

        return back()->with('ok', 'User created.');
    }

    public function adminUpdateUser(Request $r, User $user)
    {
        $d = $r->validate(['max_tunnels' => 'sometimes|integer|min:0|max:1000', 'max_reservations' => 'sometimes|integer|min:0|max:1000', 'disabled' => 'sometimes|boolean']);
        if (array_key_exists('disabled', $d)) {
            abort_if($user->id === $r->user()->id, 422, 'You cannot disable yourself');
            $user->disabled_at = $d['disabled'] ? now() : null;
            if ($d['disabled']) {
                $user->tokens()->delete();
            }
            unset($d['disabled']);
        }
        $user->fill($d)->save();
        Audit::log($r->user(), 'user.updated', 'user', $user->id, $r->only(['max_tunnels', 'max_reservations', 'disabled']));

        return back()->with('ok', 'User updated.');
    }

    public function adminDisconnect(Request $r, string $id)
    {
        $t = Tunnel::active()->findOrFail($id);
        $this->registry->remove($t->subdomain);
        Audit::log($r->user(), 'tunnel.disconnected', 'tunnel', $t->id, ['subdomain' => $t->subdomain, 'admin' => true]);

        return back()->with('ok', 'Tunnel will disconnect within seconds.');
    }

    public function adminAudit()
    {
        return view('admin.audit', ['logs' => AuditLog::with('actor:id,email')->latest('id')->paginate(50)]);
    }
}
