@extends('layouts.app')
@section('title','Sign in')
@section('own-errors','1')
@section('content')
<div class="auth"><div class="card">
<h1>Sign in</h1>
<p class="sub">Welcome back to Publish.</p>
<form method="post" action="/login">
@csrf
<label class="f">Email<input type="email" name="email" value="{{ old('email') }}" autocomplete="email" required autofocus></label>
<label class="f">Password<input type="password" name="password" autocomplete="current-password" required></label>
@error('email')<div class="err" role="alert">{{ $message }}</div>@enderror
<button class="main">Sign in</button>
</form>
@if(config('tunnel.allow_registration'))<p class="sub" style="margin:16px 0 0;text-align:center">No account? <a href="/register">Create one</a></p>@endif
</div></div>
@endsection
