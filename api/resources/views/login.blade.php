@extends('layouts.app')
@section('title','Sign in')
@section('content')
<div class="card" style="max-width:380px;margin:40px auto">
<h1>Sign in</h1>
<form method="post" action="/login" class="row" style="flex-direction:column;align-items:stretch">
@csrf
<input type="email" name="email" value="{{ old('email') }}" placeholder="Email" required autofocus>
<input type="password" name="password" placeholder="Password" required>
@error('email')<div class="err">{{ $message }}</div>@enderror
<button>Sign in</button>
</form></div>
@endsection
