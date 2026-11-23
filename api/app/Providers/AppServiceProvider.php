<?php

namespace App\Providers;

use App\Services\RedisTunnelRegistry;
use App\Services\TunnelRegistry;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(TunnelRegistry::class, RedisTunnelRegistry::class);
    }

    public function boot(): void
    {
        VerifyEmail::toMailUsing(function ($notifiable, string $url) {
            $mins = config('auth.verification.expire', 60);
            $m = (new MailMessage)->subject('Confirm your email for '.config('app.name'))
                ->greeting('Welcome to '.config('app.name').'!')
                ->line('Please confirm this email address to start using your account.')
                ->action('Confirm email', $url)
                ->line("This link expires in {$mins} minutes. If you did not create an account, you can ignore this email.");
            if ($support = config('tunnel.support_email')) {
                $m->line("Need help? Contact {$support}.");
            }

            return $m;
        });
        RateLimiter::for('login', fn (Request $r) => Limit::perMinute(5)->by(strtolower(trim((string) $r->input('email'))).'|'.$r->ip()));
        RateLimiter::for('register', fn (Request $r) => [Limit::perHour(5)->by('reg|'.$r->ip()), Limit::perDay(20)->by('regd|'.$r->ip())]);
        RateLimiter::for('verify', fn (Request $r) => Limit::perHour(6)->by('verify|'.($r->user()?->id ?: $r->ip())));
        RateLimiter::for('web', fn (Request $r) => Limit::perMinute(120)->by($r->user()?->id ?: $r->ip()));
        RateLimiter::for('api', fn (Request $r) => Limit::perMinute(120)->by($r->user()?->id ?: $r->ip()));
        RateLimiter::for('internal', fn (Request $r) => Limit::perMinute(6000)->by($r->ip()));
    }
}
