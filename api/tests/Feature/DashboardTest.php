<?php

namespace Tests\Feature;

use App\Models\Tunnel;
use App\Models\User;
use App\Services\TunnelRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeRegistry;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private FakeRegistry $reg;

    protected function setUp(): void
    {
        parent::setUp();
        $this->reg = new FakeRegistry;
        $this->app->instance(TunnelRegistry::class, $this->reg);
    }

    public function test_pages_require_login(): void
    {
        foreach (['/dashboard', '/history', '/tokens', '/reservations', '/admin', '/admin/audit'] as $p) {
            $this->get($p)->assertRedirect('/login');
        }
    }

    public function test_login_logout_flow(): void
    {
        User::factory()->create(['email' => 'u@example.com', 'password' => 'correct-horse-123']);
        $this->post('/login', ['email' => 'u@example.com', 'password' => 'bad'])->assertSessionHasErrors('email');
        $this->post('/login', ['email' => 'u@example.com', 'password' => 'correct-horse-123'])->assertRedirect('/dashboard');
        $this->get('/dashboard')->assertOk()->assertSee('Active tunnels');
        $this->post('/logout')->assertRedirect('/login');
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_dashboard_shows_only_own_tunnels_and_history(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $t = Tunnel::create(['user_id' => $a->id, 'subdomain' => 'mine-app', 'local_port' => 8000, 'started_at' => now()]);
        $this->reg->put('mine-app', $t->id, (string) $a->id);
        Tunnel::create(['user_id' => $b->id, 'subdomain' => 'theirs-app', 'local_port' => 9000, 'started_at' => now(), 'status' => 'closed', 'requests' => 7]);
        $this->actingAs($a)->get('/dashboard')->assertSee('mine-app')->assertDontSee('theirs-app');
        $this->actingAs($a)->get('/history')->assertSee('mine-app')->assertDontSee('theirs-app');
        $this->actingAs($b)->get('/history')->assertSee('theirs-app');
    }

    public function test_token_create_shown_once_and_revoke(): void
    {
        $u = User::factory()->create();
        $r = $this->actingAs($u)->post('/tokens', ['name' => 'laptop']);
        $r->assertRedirect('/tokens');
        $this->actingAs($u)->get('/tokens')->assertSee('Copy your new token');
        $this->actingAs($u)->get('/tokens')->assertDontSee('Copy your new token');
        $id = $u->tokens()->first()->id;
        $other = User::factory()->create();
        $this->actingAs($other)->delete("/tokens/$id")->assertNotFound();
        $this->actingAs($u)->delete("/tokens/$id")->assertRedirect();
        $this->assertSame(0, $u->tokens()->count());
    }

    public function test_disconnect_own_tunnel_only(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $t = Tunnel::create(['user_id' => $a->id, 'subdomain' => 'mine-app', 'local_port' => 1, 'started_at' => now()]);
        $this->actingAs($b)->post("/tunnels/{$t->id}/disconnect")->assertNotFound();
        $this->actingAs($a)->post("/tunnels/{$t->id}/disconnect")->assertRedirect();
        $this->assertSame(['mine-app'], $this->reg->removed);
    }

    public function test_reservation_flow(): void
    {
        $u = User::factory()->create(['max_reservations' => 1]);
        $this->actingAs($u)->post('/reservations', ['subdomain' => 'admin'])->assertSessionHasErrors('subdomain');
        $this->actingAs($u)->post('/reservations', ['subdomain' => 'my-site'])->assertSessionHasNoErrors();
        $this->actingAs($u)->post('/reservations', ['subdomain' => 'other-site'])->assertSessionHasErrors('subdomain');
        $this->actingAs($u)->get('/reservations')->assertSee('my-site.example.test');
    }

    public function test_admin_area_gated_and_works(): void
    {
        $u = User::factory()->create();
        $this->actingAs($u)->get('/admin')->assertForbidden();
        $this->actingAs($u)->get('/admin/audit')->assertForbidden();
        $adm = User::factory()->create();
        $adm->forceFill(['is_admin' => true])->save();
        $this->actingAs($adm)->get('/admin')->assertOk()->assertSee('Create user');
        $this->actingAs($adm)->patch("/admin/users/{$u->id}", ['disabled' => 1])->assertRedirect();
        $this->assertNotNull($u->fresh()->disabled_at);
        $this->actingAs($adm)->get('/admin/audit')->assertOk()->assertSee('user.updated');
    }

    public function test_disabled_user_cannot_use_session(): void
    {
        $u = User::factory()->create();
        $u->forceFill(['disabled_at' => now()])->save();
        $this->actingAs($u)->get('/dashboard')->assertForbidden();
    }

    public function test_web_group_has_csrf_protection(): void
    {
        // Laravel skips CSRF checks while running tests, so assert the middleware is registered.
        $web = $this->app['router']->getMiddlewareGroups()['web'];
        $this->assertNotEmpty(array_filter($web, fn ($m) => is_string($m) && (str_contains($m, 'Forgery') || str_contains($m, 'Csrf'))));
    }
}
