@extends('layouts.app')
@section('title','Verify your email')
@section('content')
<div class="auth"><div class="card">
<h1>Check your inbox</h1>
<p class="sub">We sent a confirmation link to <b>{{ auth()->user()->email }}</b>. Open it to activate your account. The link expires in {{ config('auth.verification.expire', 60) }} minutes.</p>
<form method="post" action="{{ route('verification.send') }}">@csrf<button class="main">Resend email</button></form>
<p class="hint" style="margin-top:14px">Not there? Check spam. Wrong address? <a href="{{ route('profile') }}">Change it in your profile</a>.</p>
</div></div>
@endsection
