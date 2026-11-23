@extends('layouts.app')
@section('title','Register')
@section('own-errors','1')
@section('content')
<div class="auth"><div class="card">
<h1>Create account</h1>
<p class="sub">Free accounts get {{ config('tunnel.free_max_tunnels') }} tunnel and {{ config('tunnel.free_max_reservations') }} reserved subdomain. We will email you a link to confirm your address.</p>
<form method="post" action="/register">
@csrf
<label class="f">Name<input name="name" value="{{ old('name') }}" autocomplete="name" required autofocus></label>
<label class="f">Email<input type="email" name="email" value="{{ old('email') }}" autocomplete="email" required></label>
<label class="f">Password (min 10 characters)<input type="password" name="password" autocomplete="new-password" required minlength="10"></label>
<label class="f">Repeat password<input type="password" name="password_confirmation" autocomplete="new-password" required></label>
@foreach($errors->all() as $e)<div class="err" role="alert">{{ $e }}</div>@endforeach
<button class="main">Create account</button>
</form>
<p class="sub" style="margin:16px 0 0;text-align:center">Already registered? <a href="/login">Sign in</a></p>
</div></div>
@endsection
