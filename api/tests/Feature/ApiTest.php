<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Tunnel;
use App\Models\User;
use App\Services\TunnelRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeRegistry;
use Tests\TestCase;

class ApiTest extends TestCase
{
    use RefreshDatabase;

    private FakeRegistry $reg;

    protected function setUp(): void
    {
        parent::setUp();
        $this->reg = new FakeRegistry;
        $this->app->instance(TunnelRegistry::class, $this->reg);
    }

    private function user(array $attrs = []): User
    {
        return User::factory()->create($attrs);
    }

    private function bearer(User $u): array
    {
        return ['Authorization' => 'Bearer '.$u->createToken('t')->plainTextToken];
    }

    private function admin(): User
    {
        $u = $this->user();
        $u->forceFill(['is_admin' => true])->save();

        return $u;
    }

    public function test_login_returns_token_and_audits(): void
    {
        $u = $this->user(['email' => 'a@example.com', 'password' => 'correct-horse-123']);
        $r = $this->postJson('/api/v1/auth/login', ['email' => 'a@example.com', 'password' => 'correct-horse-123'])->assertCreated();
        $this->assertStringContainsString('|', $r->json('token'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.login', 'actor_id' => $u->id]);
        $this->assertStringNotContainsString($r->json('token'), json_encode(AuditLog::all()));
    }

    public function test_login_rejects_bad_password_and_disabled_user(): void
    {
        $u = $this->user(['email' => 'b@example.com', 'password' => 'correct-horse-123']);
        $this->postJson('/api/v1/auth/login', ['email' => 'b@example.com', 'password' => 'nope'])->assertStatus(422);
        $u->forceFill(['disabled_at' => now()])->save();
        $this->postJson('/api/v1/auth/login', ['email' => 'b@example.com', 'password' => 'correct-horse-123'])->assertStatus(422);
    }

    public function test_api_requires_auth(): void
    {
        $this->getJson('/api/v1/me')->assertUnauthorized();
        $this->getJson('/api/v1/tunnels')->assertUnauthorized();
        $this->getJson('/api/v1/admin/users')->assertUnauthorized();
    }

    public function test_public_config(): void
    {
        $this->getJson('/api/v1/config')->assertOk()->assertJsonStructure(['base_domain', 'server_host', 'control_port', 'tls'])
            ->assertJsonMissing(['internal_secret']);
    }

    public function test_token_lifecycle_and_isolation(): void
    {
        $u = $this->user();
        $other = $this->user();
        $h = $this->bearer($u);
        $created = $this->postJson('/api/v1/tokens', ['name' => 'laptop'], $h)->assertCreated()->json();
        $this->getJson('/api/v1/tokens', $h)->assertOk()->assertJsonMissing(['token' => $created['token']]);
        // another user's token cannot be revoked by us
        $otherTok = $other->createToken('x')->accessToken;
        $this->deleteJson('/api/v1/tokens/'.$otherTok->id, [], $h)->assertNotFound();
        $this->deleteJson('/api/v1/tokens/'.$created['id'], [], $h)->assertNoContent();
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $created['id']]);
    }

    public function test_internal_requires_secret(): void
    {
        $this->postJson('/internal/v1/auth/validate', ['token' => 'x'])->assertUnauthorized();
        $this->postJson('/internal/v1/auth/validate', ['token' => 'x'], ['X-Internal-Secret' => 'wrong'])->assertUnauthorized();
        $this->getJson('/internal/v1/auth/validate', ['X-Internal-Secret' => 'test-internal-secret-0123456789abcdef'])->assertStatus(405);
    }

    private function internal(): array
    {
        return ['X-Internal-Secret' => 'test-internal-secret-0123456789abcdef'];
    }

    public function test_validate_token_valid_revoked_expired_disabled(): void
    {
        $u = $this->user();
        $t = $u->createToken('cli');
        $this->postJson('/internal/v1/auth/validate', ['token' => $t->plainTextToken], $this->internal())
            ->assertOk()->assertJson(['user_id' => (string) $u->id]);
        $this->postJson('/internal/v1/auth/validate', ['token' => 'garbage'], $this->internal())->assertUnauthorized();

        $exp = $u->createToken('exp', ['*'], now()->subMinute());
        $this->postJson('/internal/v1/auth/validate', ['token' => $exp->plainTextToken], $this->internal())->assertUnauthorized();

        $u->forceFill(['disabled_at' => now()])->save();
        $this->postJson('/internal/v1/auth/validate', ['token' => $t->plainTextToken], $this->internal())->assertUnauthorized();
        $u->forceFill(['disabled_at' => null])->save();

        $t->accessToken->delete(); // revocation
        $this->postJson('/internal/v1/auth/validate', ['token' => $t->plainTextToken], $this->internal())->assertUnauthorized();
    }

    public function test_authorize_allocates_random_and_custom_subdomains(): void
    {
        $u = $this->user();
        $r = $this->postJson('/internal/v1/tunnels/authorize', ['user_id' => $u->id, 'local_port' => 8000], $this->internal())->assertOk();
        $this->assertMatchesRegularExpression('/^[a-z0-9]{8}$/', $r->json('subdomain'));
        $this->assertDatabaseHas('tunnels', ['id' => $r->json('tunnel_id'), 'user_id' => $u->id, 'status' => 'active']);

        $this->postJson('/internal/v1/tunnels/authorize', ['user_id' => $u->id, 'local_port' => 1, 'requested_subdomain' => 'My-App'], $this->internal())
            ->assertOk()->assertJson(['subdomain' => 'my-app']);
    }

    public function test_authorize_rejects_reserved_invalid_and_foreign_names(): void
    {
        $a = $this->user();
        $b = $this->user();
        $a->reservations()->create(['subdomain' => 'alice-app']);
        foreach (['admin', 'x', 'bad_name', '-nope-'] as $bad) {
            $this->postJson('/internal/v1/tunnels/authorize', ['user_id' => $b->id, 'local_port' => 1, 'requested_subdomain' => $bad], $this->internal())->assertStatus(422);
        }
        $this->postJson('/internal/v1/tunnels/authorize', ['user_id' => $b->id, 'local_port' => 1, 'requested_subdomain' => 'alice-app'], $this->internal())->assertStatus(409);
        $this->postJson('/internal/v1/tunnels/authorize', ['user_id' => $a->id, 'local_port' => 1, 'requested_subdomain' => 'alice-app'], $this->internal())->assertOk();
        // active subdomain of another user cannot be taken
        $this->postJson('/internal/v1/tunnels/authorize', ['user_id' => $b->id, 'local_port' => 1, 'requested_subdomain' => 'alice-app'], $this->internal())->assertStatus(409);
    }

    public function test_unreserved_custom_disabled_by_policy(): void
    {
        config(['tunnel.allow_unreserved_custom' => false]);
        $u = $this->user();
        $this->postJson('/internal/v1/tunnels/authorize', ['user_id' => $u->id, 'local_port' => 1, 'requested_subdomain' => 'freeform'], $this->internal())->assertStatus(409);
        $u->reservations()->create(['subdomain' => 'freeform']);
        $this->postJson('/internal/v1/tunnels/authorize', ['user_id' => $u->id, 'local_port' => 1, 'requested_subdomain' => 'freeform'], $this->internal())->assertOk();
    }

    public function test_tunnel_limit_and_reconnect_replacement(): void
    {
        $u = $this->user(['max_tunnels' => 2]);
        $post = fn (array $d) => $this->postJson('/internal/v1/tunnels/authorize', ['user_id' => $u->id, 'local_port' => 1] + $d, $this->internal());
        $post(['requested_subdomain' => 'one-app'])->assertOk();
        $first = $post(['requested_subdomain' => 'two-app'])->assertOk()->json();
        $post([])->assertStatus(429);
        // reconnecting an existing subdomain is not blocked by the limit and replaces the old row
        $re = $post(['requested_subdomain' => 'two-app'])->assertOk()->json();
        $this->assertNotSame($first['tunnel_id'], $re['tunnel_id']);
        $this->assertDatabaseHas('tunnels', ['id' => $first['tunnel_id'], 'status' => 'closed', 'close_reason' => 'replaced']);
        $this->assertSame(2, Tunnel::active()->where('user_id', $u->id)->count());
    }

    public function test_disabled_user_and_bandwidth_limit_blocked(): void
    {
        $u = $this->user(['monthly_bytes_limit' => 100]);
        Tunnel::create(['user_id' => $u->id, 'subdomain' => 'old-one', 'local_port' => 1, 'status' => 'closed', 'started_at' => now(), 'bytes_in' => 60, 'bytes_out' => 60]);
        $this->postJson('/internal/v1/tunnels/authorize', ['user_id' => $u->id, 'local_port' => 1], $this->internal())->assertStatus(429);
        $u->forceFill(['monthly_bytes_limit' => null, 'disabled_at' => now()])->save();
        $this->postJson('/internal/v1/tunnels/authorize', ['user_id' => $u->id, 'local_port' => 1], $this->internal())->assertStatus(403);
    }

    public function test_closed_records_usage_and_history(): void
    {
        $u = $this->user();
        $id = $this->postJson('/internal/v1/tunnels/authorize', ['user_id' => $u->id, 'local_port' => 1], $this->internal())->json('tunnel_id');
        $this->postJson("/internal/v1/tunnels/$id/closed", ['requests' => 5, 'bytes_in' => 10, 'bytes_out' => 20, 'reason' => 'disconnected'], $this->internal())->assertNoContent();
        $this->assertDatabaseHas('tunnels', ['id' => $id, 'status' => 'closed', 'requests' => 5, 'bytes_out' => 20]);
        $this->getJson('/api/v1/tunnels/history', $this->bearer($u))->assertOk()->assertJsonPath('data.0.requests', 5);
        $this->getJson('/api/v1/usage', $this->bearer($u))->assertOk()->assertJson(['requests' => 5, 'bytes_out' => 20]);
    }

    public function test_tunnel_listing_isolation_and_disconnect(): void
    {
        $a = $this->user();
        $b = $this->user();
        $t = Tunnel::create(['user_id' => $a->id, 'subdomain' => 'alpha-app', 'local_port' => 1, 'started_at' => now()]);
        $this->reg->put('alpha-app', $t->id, (string) $a->id);
        $this->getJson('/api/v1/tunnels', $this->bearer($a))->assertOk()->assertJsonCount(1)->assertJsonPath('0.subdomain', 'alpha-app');
        $this->getJson('/api/v1/tunnels', $this->bearer($b))->assertOk()->assertJsonCount(0);
        $this->deleteJson('/api/v1/tunnels/'.$t->id, [], $this->bearer($b))->assertNotFound(); // cannot touch others'
        $this->assertSame([], $this->reg->removed);
        $this->deleteJson('/api/v1/tunnels/'.$t->id, [], $this->bearer($a))->assertNoContent();
        $this->assertSame(['alpha-app'], $this->reg->removed);
    }

    public function test_reaper_closes_stale_rows(): void
    {
        $u = $this->user();
        $stale = Tunnel::create(['user_id' => $u->id, 'subdomain' => 'stale-one', 'local_port' => 1, 'started_at' => now()->subMinutes(10)]);
        $live = Tunnel::create(['user_id' => $u->id, 'subdomain' => 'live-one', 'local_port' => 1, 'started_at' => now()->subMinutes(10)]);
        $fresh = Tunnel::create(['user_id' => $u->id, 'subdomain' => 'fresh-one', 'local_port' => 1, 'started_at' => now()]);
        $this->reg->put('live-one', $live->id);
        $this->artisan('tunnels:reap')->assertSuccessful();
        $this->assertSame('closed', $stale->fresh()->status);
        $this->assertSame('active', $live->fresh()->status);
        $this->assertSame('active', $fresh->fresh()->status);
    }

    public function test_reservations_limits_and_ownership(): void
    {
        $a = $this->user(['max_reservations' => 1]);
        $b = $this->user();
        $h = $this->bearer($a);
        $this->postJson('/api/v1/reservations', ['subdomain' => 'admin'], $h)->assertStatus(422);
        $id = $this->postJson('/api/v1/reservations', ['subdomain' => 'my-name'], $h)->assertCreated()->json('id');
        $this->postJson('/api/v1/reservations', ['subdomain' => 'second-name'], $h)->assertStatus(429);
        $this->postJson('/api/v1/reservations', ['subdomain' => 'my-name'], $this->bearer($b))->assertStatus(409);
        $this->deleteJson("/api/v1/reservations/$id", [], $this->bearer($b))->assertNotFound();
        $this->deleteJson("/api/v1/reservations/$id", [], $h)->assertNoContent();
    }

    public function test_admin_endpoints_require_admin(): void
    {
        $u = $this->user();
        $this->getJson('/api/v1/admin/users', $this->bearer($u))->assertForbidden();
        $this->getJson('/api/v1/admin/audit-logs', $this->bearer($u))->assertForbidden();
        $this->getJson('/api/v1/admin/tunnels', $this->bearer($u))->assertForbidden();
        $adm = $this->admin();
        $this->getJson('/api/v1/admin/users', $this->bearer($adm))->assertOk();
    }

    public function test_admin_can_disable_user_which_kills_tokens_and_is_audited(): void
    {
        $adm = $this->admin();
        $u = $this->user();
        $tok = $u->createToken('x');
        $this->patchJson('/api/v1/admin/users/'.$u->id, ['disabled' => true, 'max_tunnels' => 1], $this->bearer($adm))->assertNoContent();
        $this->assertNotNull($u->fresh()->disabled_at);
        $this->assertSame(0, $u->tokens()->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.updated', 'actor_id' => $adm->id, 'target_id' => (string) $u->id]);
        $this->patchJson('/api/v1/admin/users/'.$adm->id, ['disabled' => true], $this->bearer($adm))->assertStatus(422);
    }

    public function test_admin_disconnect_any_tunnel(): void
    {
        $adm = $this->admin();
        $u = $this->user();
        $t = Tunnel::create(['user_id' => $u->id, 'subdomain' => 'target-app', 'local_port' => 1, 'started_at' => now()]);
        $this->reg->put('target-app', $t->id);
        $this->deleteJson('/api/v1/admin/tunnels/'.$t->id, [], $this->bearer($adm))->assertNoContent();
        $this->assertSame(['target-app'], $this->reg->removed);
        $this->assertDatabaseHas('audit_logs', ['action' => 'tunnel.disconnected']);
    }

    public function test_disabled_user_api_token_rejected(): void
    {
        $u = $this->user();
        $h = $this->bearer($u);
        $u->forceFill(['disabled_at' => now()])->save();
        $this->getJson('/api/v1/me', $h)->assertForbidden();
    }
}
