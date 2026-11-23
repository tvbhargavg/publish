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
use App\Support\Listing;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function __construct(private TunnelRegistry $registry, private TunnelAuthorizer $authorizer) {}

    public function loginForm()
    {
        return view('login');
    }

    public function registerForm()
    {
        abort_unless(config('tunnel.allow_registration'), 404);

        return view('register');
    }

    public function register(Request $r)
    {
        abort_unless(config('tunnel.allow_registration'), 404);
        $r->merge(['email' => strtolower((string) $r->input('email'))]);
        $d = $r->validate(['name' => 'required|string|max:100', 'email' => 'required|email:rfc|max:190|unique:users,email', 'password' => 'required|string|min:10|max:200|confirmed']);
        $u = User::create(['name' => $d['name'], 'email' => $d['email'], 'password' => $d['password'], 'max_tunnels' => config('tunnel.premium_default_max_tunnels')]);
        Audit::log($u, 'user.registered', 'user', $u->id);
        $u->trySendVerification();
        Auth::login($u);
        $r->session()->regenerate();

        return redirect()->route('verification.notice');
    }

    public function login(Request $r)
    {
        $r->merge(['email' => strtolower(trim((string) $r->input('email')))]);
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

    public function verifyNotice(Request $r)
    {
        return $r->user()->hasVerifiedEmail() ? redirect()->route('dashboard') : view('auth.verify');
    }

    public function verifyEmail(EmailVerificationRequest $r)
    {
        $r->fulfill();
        Audit::log($r->user(), 'user.email_verified', 'user', $r->user()->id);

        return redirect()->route('dashboard')->with('ok', 'Email verified. Welcome to Publish!');
    }

    public function resendVerification(Request $r)
    {
        if ($r->user()->hasVerifiedEmail()) {
            return redirect()->route('dashboard');
        }
        $sent = $r->user()->trySendVerification();

        return back()->with($sent ? 'ok' : 'warn', $sent ? 'Verification email sent. Check your inbox (and spam).' : 'We could not send the email right now. Please try again in a few minutes.');
    }

    public function dashboard(Request $r)
    {
        $u = $r->user();
        $this->authorizer->reapStale($u);
        $q = $u->tunnels()->active()->latest('started_at');
        Listing::search($q, $r->query('q'), ['subdomain']);
        $page = $q->paginate(Listing::perPage())->withQueryString();
        $page->getCollection()->transform(fn ($t) => [$t, $this->registry->get($t->subdomain)]);

        return view('dashboard', ['page' => $page, 'activeCount' => $u->tunnels()->active()->count(), 'usage' => $u->monthlyUsage(), 'user' => $u]);
    }

    public function history(Request $r)
    {
        $q = $r->user()->tunnels()->latest('started_at');
        Listing::search($q, $r->query('q'), ['subdomain']);
        if ($st = Listing::pick($r->query('status'), ['active', 'closed'])) {
            $q->where('status', $st);
        }
        if ($days = Listing::pick($r->query('period'), ['7', '30', '90'])) {
            $q->where('started_at', '>=', now()->subDays((int) $days));
        }

        return view('history', ['page' => $q->paginate(Listing::perPage())->withQueryString()]);
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
        $q = $r->user()->tokens()->latest();
        Listing::search($q, $r->query('q'), ['name']);
        $st = Listing::pick($r->query('status'), ['active', 'expired']);
        if ($st === 'active') {
            $q->where(fn ($w) => $w->whereNull('expires_at')->orWhere('expires_at', '>', now()));
        } elseif ($st === 'expired') {
            $q->where('expires_at', '<=', now());
        }

        return view('tokens', ['page' => $q->paginate(Listing::perPage())->withQueryString(), 'new' => session('new_token')]);
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
        $q = $r->user()->reservations()->latest('id');
        Listing::search($q, $r->query('q'), ['subdomain']);

        return view('reservations', ['page' => $q->paginate(Listing::perPage())->withQueryString(), 'user' => $r->user(), 'used' => $r->user()->reservations()->count()]);
    }

    public function createReservation(Request $r)
    {
        $sub = strtolower((string) $r->input('subdomain'));
        if (! TunnelAuthorizer::validSubdomain($sub) || in_array($sub, config('tunnel.reserved'), true)) {
            return back()->withErrors(['subdomain' => 'Invalid or reserved name (3-63 chars: a-z, 0-9, hyphen).'])->withInput();
        }
        if ($r->user()->reservations()->count() >= $r->user()->reservationLimit()) {
            return back()->withErrors(['subdomain' => 'Reservation limit reached.'])->withInput();
        }
        if (Reservation::where('subdomain', $sub)->exists()) {
            return back()->withErrors(['subdomain' => 'Already reserved.'])->withInput();
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
    public function adminOverview(Request $r)
    {
        $q = User::query()->orderBy('id');
        Listing::search($q, $r->query('q'), ['email', 'name']);
        match (Listing::pick($r->query('plan'), ['free', 'premium', 'admin'])) {
            'free' => $q->where('tunnel_access', false),
            'premium' => $q->where('tunnel_access', true),
            'admin' => $q->where('is_admin', true),
            default => null,
        };
        match (Listing::pick($r->query('status'), ['active', 'disabled'])) {
            'active' => $q->whereNull('disabled_at'),
            'disabled' => $q->whereNotNull('disabled_at'),
            default => null,
        };
        match (Listing::pick($r->query('verified'), ['yes', 'no'])) {
            'yes' => $q->whereNotNull('email_verified_at'),
            'no' => $q->whereNull('email_verified_at'),
            default => null,
        };

        return view('admin.overview', ['page' => $q->paginate(Listing::perPage())->withQueryString(), 'total' => User::count()]);
    }

    public function adminTunnels(Request $r)
    {
        $this->authorizer->reapStale();
        $q = Tunnel::active()->with('user:id,email')->latest('started_at');
        if ($term = trim((string) $r->query('q'))) {
            $q->where(function ($w) use ($term) {
                Listing::search($w, $term, ['subdomain']);
                $w->orWhereHas('user', fn ($u) => Listing::search($u, $term, ['email']));
            });
        }
        $page = $q->paginate(Listing::perPage())->withQueryString();
        $page->getCollection()->transform(fn ($t) => [$t, $this->registry->get($t->subdomain)]);

        return view('admin.tunnels', ['page' => $page]);
    }

    public function adminCreateUser(Request $r)
    {
        $r->merge(['email' => strtolower(trim((string) $r->input('email')))]);
        $d = $r->validate(['name' => 'required|string|max:100', 'email' => 'required|email|max:190|unique:users', 'password' => 'required|string|min:10|max:200']);
        $u = User::create($d + ['max_tunnels' => config('tunnel.premium_default_max_tunnels')]);
        $u->forceFill(['tunnel_access' => true, 'email_verified_at' => now()])->save();
        Audit::log($r->user(), 'user.created', 'user', $u->id);

        return redirect()->route('admin')->with('ok', 'User created.');
    }

    public function adminUpdateUser(Request $r, User $user)
    {
        $d = $r->validate(['max_tunnels' => 'sometimes|integer|min:0|max:1000', 'max_reservations' => 'sometimes|integer|min:0|max:1000', 'disabled' => 'sometimes|boolean', 'tunnel_access' => 'sometimes|boolean']);
        if (array_key_exists('tunnel_access', $d)) {
            $user->forceFill(['tunnel_access' => $d['tunnel_access']]);
            unset($d['tunnel_access']);
        }
        if (array_key_exists('disabled', $d)) {
            abort_if($d['disabled'] && $user->id === $r->user()->id, 422, 'You cannot disable yourself');
            $user->disabled_at = $d['disabled'] ? now() : null;
            if ($d['disabled']) {
                $user->tokens()->delete();
            }
            unset($d['disabled']);
        }
        $user->fill($d)->save();
        Audit::log($r->user(), 'user.updated', 'user', $user->id, $r->only(['max_tunnels', 'max_reservations', 'disabled', 'tunnel_access']));

        return back()->with('ok', 'User updated.');
    }

    public function adminDisconnect(Request $r, string $id)
    {
        $t = Tunnel::active()->findOrFail($id);
        $this->registry->remove($t->subdomain);
        Audit::log($r->user(), 'tunnel.disconnected', 'tunnel', $t->id, ['subdomain' => $t->subdomain, 'admin' => true]);

        return back()->with('ok', 'Tunnel will disconnect within seconds.');
    }

    public function adminAudit(Request $r)
    {
        $q = AuditLog::with('actor:id,email')->latest('id');
        if ($term = trim((string) $r->query('q'))) {
            $q->where(function ($w) use ($term) {
                Listing::search($w, $term, ['action', 'ip', 'target_id']);
                $w->orWhereHas('actor', fn ($a) => Listing::search($a, $term, ['email']));
            });
        }
        $actions = AuditLog::query()->distinct()->orderBy('action')->pluck('action');
        if ($a = Listing::pick($r->query('action'), $actions->all())) {
            $q->where('action', $a);
        }

        return view('admin.audit', ['page' => $q->paginate(Listing::perPage())->withQueryString(), 'actions' => $actions]);
    }
}
