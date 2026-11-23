@extends('layouts.app')
@section('title','Profile')
@section('own-errors','1')
@section('content')
<div class="stackcol">
<div class="card me">
<span class="avatar lg" aria-hidden="true">{{ mb_substr($user->name ?: $user->email, 0, 1) }}</span>
<div class="who2"><b>{{ $user->name }}</b><span class="m">{{ $user->email }}</span></div>
<div class="row" style="gap:6px"><span class="pill {{ $user->tunnel_access ? 'premium' : '' }}">{{ $user->tunnel_access ? 'premium' : 'free' }}</span><span class="pill {{ $user->hasVerifiedEmail() ? 'online' : 'warnp' }}">{{ $user->hasVerifiedEmail() ? 'verified' : 'unverified' }}</span></div>
</div>

<div class="card sec">
<h3>Account</h3><p class="hint">Your name and the email we use to reach you.</p>
<form method="post" action="{{ route('profile.update') }}">@csrf @method('PATCH')
<label class="f">Name<input name="name" value="{{ old('name', $user->name) }}" required maxlength="100" autocomplete="name"></label>
@error('name')<div class="err" role="alert">{{ $message }}</div>@enderror
<label class="f">Email<input type="email" name="email" value="{{ old('email', $user->email) }}" required autocomplete="email"></label>
@error('email')<div class="err" role="alert">{{ $message }}</div>@enderror
<p class="hint">If you change your email we will ask you to confirm the new address.</p>
<div><button class="sm">Save changes</button></div>
</form>
</div>

<div class="card sec">
<h3>Password</h3><p class="hint">Use at least 10 characters.</p>
<form method="post" action="{{ route('profile.password') }}">@csrf @method('PUT')
<label class="f">Current password<input type="password" name="current_password" required autocomplete="current-password"></label>
@if($errors->password->has('current_password'))<div class="err" role="alert">{{ $errors->password->first('current_password') }}</div>@endif
<label class="f">New password<input type="password" name="password" required minlength="10" autocomplete="new-password"></label>
<label class="f">Repeat new password<input type="password" name="password_confirmation" required autocomplete="new-password"></label>
@error('password')<div class="err" role="alert">{{ $message }}</div>@enderror
<div><button class="sm">Update password</button></div>
</form>
</div>

<div class="card sec">
<h3>Appearance</h3><p class="hint">Saved to your account, so it follows you on every device.</p>
<div class="seg" role="group" aria-label="Theme">
<button type="button" data-theme-set="light" aria-pressed="false">Light</button>
<button type="button" data-theme-set="dark" aria-pressed="false">Dark</button>
<button type="button" data-theme-set="system" aria-pressed="false">System</button>
</div>
</div>

<div class="card sec">
<h3>Your plan</h3>
<div class="kv">
<span class="muted">Tunnels at once</span><b class="right">{{ $user->tunnelLimit() }}</b>
<span class="muted">Reserved subdomains</span><b class="right">{{ $user->reservationLimit() }}</b>
<span class="muted">Member since</span><b class="right">{{ $user->created_at->format('Y-m-d') }}</b>
</div>
@unless($user->tunnel_access)<p class="hint" style="margin-top:12px">Need more tunnels? Ask an admin to enable premium.@if(config('tunnel.support_email')) Contact <a href="mailto:{{ config('tunnel.support_email') }}">{{ config('tunnel.support_email') }}</a>.@endif</p>@endunless
</div>
</div>
@endsection
