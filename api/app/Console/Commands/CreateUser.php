<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class CreateUser extends Command
{
    protected $signature = 'mytunnel:create-user {email} {--name=} {--admin} {--password=}';

    protected $description = 'Create a user (bootstrap the first admin with --admin)';

    public function handle(): int
    {
        $email = $this->argument('email');
        if (User::where('email', $email)->exists()) {
            $this->error('User already exists');

            return self::FAILURE;
        }
        $password = $this->option('password') ?: Str::password(20, symbols: false);
        $u = User::create(['name' => $this->option('name') ?: Str::before($email, '@'), 'email' => $email, 'password' => $password]);
        if ($this->option('admin')) {
            $u->forceFill(['is_admin' => true])->save();
        }
        $this->info("Created user #{$u->id} <{$email}>".($this->option('admin') ? ' (admin)' : ''));
        if (! $this->option('password')) {
            $this->line("Password: {$password}");
        }

        return self::SUCCESS;
    }
}
