<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\TunnelRegistry;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\Support\FakeRegistry;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->instance(TunnelRegistry::class, new FakeRegistry);
        config(['tunnel.allow_registration' => true]);
    }

    private function internal(string $path, array $body)
    {
        return $this->postJson('/internal/v1'.$path, $body, ['X-Internal-Secret' => config('tunnel.internal_secret')]);
    }

    private function register(string $email = 'New@Example.com')
    {
        return $this->post('/register', ['name' => 'N', 'email' => $email, 'password' => 'long-enough-pw', 'password_confirmation' => 'long-enough-pw']);
    }

    public function test_web_registration_creates_user_without_tunnel_access(): void
    {
        $this->register()->assertRedirect('/email/verify');
        $u = User::where('email', 'new@example.com')->firstOrFail();
        $this->assertFalse((bool) $u->tunnel_access);
        $this->assertFalse((bool) $u->is_admin);
        $this->assertSame(4, $u->max_tunnels);
        $this->get('/dashboard')->assertRedirect('/email/verify');
    }

    public function test_one_account_per_email_case_insensitive(): void
    {
        $this->register('a@example.com')->assertRedirect();
        auth()->logout();
        $this->flushSession();
        $this->register('A@EXAMPLE.com')->assertSessionHasErrors('email');
        $this->assertSame(1, User::count());
    }

    public function test_cannot_self_grant_flags_via_api_register(): void
    {
        $this->postJson('/api/v1/auth/register', ['name' => 'x', 'email' => 'x@example.com', 'password' => 'long-enough-pw',
            'is_admin' => true, 'tunnel_access' => true, 'max_tunnels' => 999])->assertCreated();
        $u = User::firstWhere('email', 'x@example.com');
        $this->assertFalse((bool) $u->is_admin);
        $this->assertFalse((bool) $u->tunnel_access);
        $this->assertSame(4, $u->max_tunnels);
    }

    public function test_registration_is_rate_limited(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/register', ['name' => 'x', 'email' => "u$i@example.com", 'password' => 'long-enough-pw'])->assertCreated();
        }
        $this->postJson('/api/v1/auth/register', ['name' => 'x', 'email' => 'u9@example.com', 'password' => 'long-enough-pw'])->assertStatus(429);
    }

    public function test_registration_can_be_switched_off(): void
    {
        config(['tunnel.allow_registration' => false]);
        $this->get('/register')->assertNotFound();
        $this->postJson('/api/v1/auth/register', [])->assertNotFound();
    }

    public function test_free_user_gets_one_tunnel_premium_gets_admin_limit(): void
    {
        $u = User::factory()->create(['tunnel_access' => false, 'max_tunnels' => 4]);
        $tok = $u->createToken('t')->accessToken;
        $body = ['user_id' => $u->id, 'token_id' => $tok->id, 'local_port' => 80];
        $this->internal('/tunnels/authorize', $body)->assertOk();
        $this->internal('/tunnels/authorize', $body)->assertStatus(429);

        $u->forceFill(['tunnel_access' => true])->save();
        for ($i = 0; $i < 3; $i++) {
            $this->internal('/tunnels/authorize', $body)->assertOk();
        }
        $this->internal('/tunnels/authorize', $body)->assertStatus(429);

        $u->forceFill(['max_tunnels' => 6])->save();
        $this->internal('/tunnels/authorize', $body)->assertOk();
    }

    public function test_only_admin_can_toggle_flag(): void
    {
        $target = User::factory()->create(['tunnel_access' => false]);
        $plain = User::factory()->create();
        $this->actingAs($plain)->patch("/admin/users/{$target->id}", ['tunnel_access' => 1])->assertForbidden();
        $this->assertFalse((bool) $target->fresh()->tunnel_access);

        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        $this->actingAs($admin)->patch("/admin/users/{$target->id}", ['tunnel_access' => 1])->assertRedirect();
        $this->assertTrue((bool) $target->fresh()->tunnel_access);
    }

    public function test_seeder_creates_admin_idempotently(): void
    {
        config(['tunnel.admin_email' => 'Boss@Example.com', 'tunnel.admin_password' => 'seed-password-123']);
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);
        $this->assertSame(1, User::where('email', 'boss@example.com')->count());
        $u = User::firstWhere('email', 'boss@example.com');
        $this->assertTrue((bool) $u->is_admin);
        $this->assertTrue((bool) $u->tunnel_access);
    }

    public function test_login_is_case_insensitive_for_email(): void
    {
        $this->register('Jane@Example.com')->assertRedirect('/email/verify');
        User::firstWhere('email', 'jane@example.com')->forceFill(['email_verified_at' => now()])->save();
        $this->post('/logout');
        $this->post('/login', ['email' => 'JANE@example.com', 'password' => 'long-enough-pw'])->assertRedirect('/dashboard');
        $this->postJson('/api/v1/auth/login', ['email' => 'Jane@EXAMPLE.com', 'password' => 'long-enough-pw'])->assertCreated();
    }

    public function test_admin_created_mixed_case_email_blocks_self_registration(): void
    {
        User::factory()->create(['email' => 'Bob@Corp.com']);
        $this->assertSame('bob@corp.com', User::first()->email);
        $this->register('bob@corp.com')->assertSessionHasErrors('email');
        $this->assertSame(1, User::count());
    }

    public function test_seeder_never_promotes_existing_non_admin(): void
    {
        $victim = User::factory()->create(['email' => 'boss@example.com', 'tunnel_access' => false]);
        config(['tunnel.admin_email' => 'boss@example.com']);
        $this->seed(DatabaseSeeder::class);
        $this->assertFalse((bool) $victim->fresh()->is_admin);
    }

    public function test_free_users_get_exactly_one_reservation(): void
    {
        $u = User::factory()->create(['tunnel_access' => false, 'max_reservations' => 3]);
        $h = ['Authorization' => 'Bearer '.$u->createToken('t')->plainTextToken];
        $this->postJson('/api/v1/reservations', ['subdomain' => 'my-app'], $h)->assertCreated();
        $this->postJson('/api/v1/reservations', ['subdomain' => 'second-app'], $h)->assertStatus(429);
    }

    public function test_free_reservation_quota_is_one(): void
    {
        $this->assertSame(1, config('tunnel.free_max_reservations'));
    }

    public function test_registration_sends_verification_email_and_link_verifies(): void
    {
        Notification::fake();
        $this->register('Verify@Example.com')->assertRedirect('/email/verify');
        $u = User::firstWhere('email', 'verify@example.com');
        Notification::assertSentTo($u, VerifyEmail::class);
        $this->assertFalse($u->hasVerifiedEmail());
        $this->get('/email/verify')->assertOk()->assertSee('Check your inbox');
        $this->get('/tokens')->assertRedirect('/email/verify');

        $url = URL::temporarySignedRoute('verification.verify', now()->addHour(), ['id' => $u->id, 'hash' => sha1($u->email)]);
        $this->get($url)->assertRedirect('/dashboard');
        $this->assertTrue($u->fresh()->hasVerifiedEmail());
        $this->get('/dashboard')->assertOk()->assertSee('free');
    }

    public function test_verification_link_needs_valid_signature(): void
    {
        $u = User::factory()->unverified()->create();
        $this->actingAs($u)->get("/email/verify/{$u->id}/".sha1($u->email))->assertForbidden();
        $this->assertFalse($u->fresh()->hasVerifiedEmail());
    }

    public function test_unverified_cannot_use_api_or_tunnels(): void
    {
        $u = User::factory()->unverified()->create(['password' => 'long-enough-pw']);
        $this->postJson('/api/v1/auth/login', ['email' => $u->email, 'password' => 'long-enough-pw'])->assertForbidden();
        $tok = $u->createToken('t')->plainTextToken;
        $this->postJson('/internal/v1/auth/validate', ['token' => $tok], ['X-Internal-Secret' => config('tunnel.internal_secret')])->assertUnauthorized();
        $this->getJson('/api/v1/me', ['Authorization' => 'Bearer '.$tok])->assertStatus(403);
    }

    public function test_resend_is_rate_limited(): void
    {
        $u = User::factory()->unverified()->create();
        for ($i = 0; $i < 6; $i++) {
            $this->actingAs($u)->post('/email/verification-notification')->assertRedirect();
        }
        $this->actingAs($u)->post('/email/verification-notification')->assertStatus(429);
    }

    public function test_free_user_tunnel_always_uses_reserved_subdomain(): void
    {
        $u = User::factory()->create(['tunnel_access' => false]);
        $u->reservations()->create(['subdomain' => 'mine']);
        $tok = $u->createToken('t')->accessToken;
        $r = $this->internal('/tunnels/authorize', ['user_id' => $u->id, 'token_id' => $tok->id, 'local_port' => 80])->assertOk();
        $this->assertSame('mine', $r->json('subdomain'));
        $r = $this->internal('/tunnels/authorize', ['user_id' => $u->id, 'token_id' => $tok->id, 'requested_subdomain' => 'other', 'local_port' => 80])->assertOk();
        $this->assertSame('mine', $r->json('subdomain'));
    }

    public function test_profile_update_and_email_change_requires_reverification(): void
    {
        Notification::fake();
        $u = User::factory()->create(['password' => 'long-enough-pw']);
        $this->actingAs($u)->patch('/profile', ['name' => 'New Name', 'email' => $u->email])->assertRedirect();
        $this->assertSame('New Name', $u->fresh()->name);
        $this->assertTrue($u->fresh()->hasVerifiedEmail());

        $this->actingAs($u)->patch('/profile', ['name' => 'New Name', 'email' => 'Changed@Example.com'])->assertRedirect('/email/verify');
        $this->assertSame('changed@example.com', $u->fresh()->email);
        $this->assertFalse($u->fresh()->hasVerifiedEmail());
    }

    public function test_profile_password_change_needs_current_password(): void
    {
        $u = User::factory()->create(['password' => 'long-enough-pw']);
        $this->actingAs($u)->put('/profile/password', ['current_password' => 'wrong', 'password' => 'brand-new-pass-1', 'password_confirmation' => 'brand-new-pass-1'])->assertSessionHasErrors('current_password', null, 'password');
        $this->actingAs($u)->put('/profile/password', ['current_password' => 'long-enough-pw', 'password' => 'brand-new-pass-1', 'password_confirmation' => 'brand-new-pass-1'])->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('brand-new-pass-1', $u->fresh()->password));
    }

    public function test_admin_user_search_and_filters_work_including_special_chars(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        User::factory()->create(['email' => 'free_one@example.com', 'tunnel_access' => false]);
        User::factory()->create(['email' => 'premium@example.com', 'tunnel_access' => true]);
        $this->actingAs($admin)->get('/admin?q=free_one&plan=free')->assertOk()->assertSee('free_one@example.com')->assertDontSee('premium@example.com');
        $this->actingAs($admin)->get('/admin?q=%25&plan=bogus&status=bogus')->assertOk()->assertSee('No results');
        $this->actingAs($admin)->get('/admin?plan=premium')->assertOk()->assertSee('premium@example.com')->assertDontSee('free_one@example.com');
        $this->actingAs($admin)->get('/admin?verified=no')->assertOk()->assertSee('No results');
        $this->actingAs($admin)->get('/admin/audit?q=x&action=nope')->assertOk();
        $this->actingAs($admin)->get('/admin/tunnels?q=abc')->assertOk();
    }

    public function test_user_lists_paginate_search_and_filter(): void
    {
        $u = User::factory()->create();
        for ($i = 0; $i < 12; $i++) {
            $u->createToken("tok-$i");
        }
        $this->actingAs($u)->get('/tokens')->assertOk()->assertSeeText('Showing 1 to 10 of 12 entries');
        $this->actingAs($u)->get('/tokens?page=2')->assertOk()->assertSeeText('Showing 11 to 12 of 12 entries');
        $this->actingAs($u)->get('/tokens?q=tok-11')->assertOk()->assertSeeText('Showing 1 to 1 of 1 entries');
        $this->actingAs($u)->get('/tokens?status=expired')->assertOk()->assertSee('No results');
        $this->actingAs($u)->get('/history?status=closed&period=7&q=x')->assertOk();
        $this->actingAs($u)->get('/reservations?q=x')->assertOk();
    }

    public function test_theme_is_saved_per_user_and_applied(): void
    {
        $u = User::factory()->create();
        $this->actingAs($u)->putJson('/profile/theme', ['theme' => 'dark'])->assertNoContent();
        $this->assertSame('dark', $u->fresh()->theme);
        $this->actingAs($u)->get('/dashboard')->assertSee("var pref='dark'", false);
        $this->actingAs($u)->putJson('/profile/theme', ['theme' => 'neon'])->assertStatus(422);
        $this->assertSame('dark', $u->fresh()->theme);
    }

    public function test_rows_per_page_is_whitelisted(): void
    {
        $u = User::factory()->create();
        for ($i = 0; $i < 12; $i++) {
            $u->createToken("t$i");
        }
        $this->actingAs($u)->get('/tokens?per_page=25')->assertSeeText('Showing 1 to 12 of 12 entries');
        $this->actingAs($u)->get('/tokens?per_page=999999')->assertSeeText('Showing 1 to 10 of 12 entries');
    }

    public function test_cli_downloads_are_whitelisted_and_install_page_renders(): void
    {
        $dir = sys_get_temp_dir().'/cli-'.uniqid();
        mkdir($dir);
        file_put_contents($dir.'/publish-linux-amd64', 'BIN');
        config(['tunnel.cli_dist_dir' => $dir]);
        $this->get('/downloads/publish-linux-amd64')->assertOk();
        $this->get('/downloads/publish-darwin-arm64')->assertNotFound();   // allowed name, not built
        $this->get('/downloads/..%2F..%2F.env')->assertNotFound();          // traversal
        $this->get('/downloads/.env')->assertNotFound();                    // not whitelisted
        $this->actingAs(User::factory()->create())->get('/install')->assertOk()->assertSee('Install the CLI')->assertSee('/install.sh', false)->assertSee('publish config server', false);
    }

    public function test_installers_are_generated_from_env_config(): void
    {
        config(['app.url' => 'https://app.mydomain.test', 'tunnel.server_host' => 'tunnel.mydomain.test', 'tunnel.control_port' => 4443, 'tunnel.tls' => true]);
        $sh = $this->get('/install.sh')->assertOk()->getContent();
        $this->assertStringContainsString("BASE='https://app.mydomain.test'", $sh);
        $this->assertStringContainsString("SERVER='tunnel.mydomain.test:4443'", $sh);
        $this->assertStringContainsString('config tls on', $sh);
        $ps = $this->get('/install.ps1')->assertOk()->getContent();
        $this->assertStringContainsString("\$base = 'https://app.mydomain.test'", $ps);
        config(['tunnel.tls' => false, 'tunnel.server_host' => "ev'il"]);
        $this->assertStringContainsString("SERVER='ev'\\''il:4443'", $this->get('/install.sh')->getContent()); // quoting-safe
        $this->assertStringContainsString('config tls off', $this->get('/install.sh')->getContent());
    }

    public function test_basic_start_command_is_shown(): void
    {
        $u = User::factory()->create(['tunnel_access' => false]);
        $u->reservations()->create(['subdomain' => 'mine']);
        $this->actingAs($u)->get('/reservations')->assertOk()->assertSee('publish http 5003', false)->assertDontSee('--subdomain mine', false);
        $this->actingAs(User::factory()->create())->get('/dashboard')->assertSee('/install.sh', false)->assertSee('publish http 5003', false);
    }
}
