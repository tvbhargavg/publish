<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /** Seeds the default admin from ADMIN_EMAIL / ADMIN_PASSWORD. Never promotes an existing non-admin. */
    public function run(): void
    {
        $email = strtolower(trim((string) config('tunnel.admin_email')));
        if (app()->isProduction() && $email === 'admin@example.com') {
            $this->command?->error('Set ADMIN_EMAIL to a real address before seeding in production.');

            return;
        }

        $user = User::where('email', $email)->first();
        if ($user) {
            if (! $user->is_admin) {
                // Someone else already holds this address (e.g. self-registered): do not hand them admin.
                $this->command?->error("{$email} exists and is not an admin; refusing to promote it. Use another ADMIN_EMAIL.");

                return;
            }
            $this->command?->info("Admin already present: {$email}");

            return;
        }

        $password = config('tunnel.admin_password') ?: Str::password(20, symbols: false);
        $user = User::create(['name' => 'Admin', 'email' => $email, 'password' => $password,
            'max_tunnels' => config('tunnel.premium_default_max_tunnels')]);
        // The flags are not mass-assignable on purpose; only the seeder/admins set them.
        $user->forceFill(['is_admin' => true, 'tunnel_access' => true, 'email_verified_at' => now()])->save();
        if (! config('tunnel.admin_password')) {
            $this->command?->warn("Generated admin password for {$email}: {$password}");
        }
        $this->command?->info("Admin created: {$email}");
    }
}
