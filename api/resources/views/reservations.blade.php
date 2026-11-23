@extends('layouts.app')
@section('title','Subdomains')
@section('content')
<h1>Reserved subdomains</h1>
<div class="card"><form method="post" action="{{ route('reservations.create') }}" class="row">@csrf
<input name="subdomain" placeholder="my-app" required maxlength="63"> <span style="color:var(--mute)">.{{ config('tunnel.base_domain') }}</span>
<button>Reserve ({{ $items->count() }}/{{ $user->max_reservations }})</button></form>
@error('subdomain')<div class="err">{{ $message }}</div>@enderror</div>
<h2>Yours</h2>
<div class="card"><table><tr><th>Subdomain</th><th>Use</th><th></th></tr>
@forelse($items as $i)
<tr><td>{{ $i->subdomain }}.{{ config('tunnel.base_domain') }}</td><td><code>mytunnel http 8000 --subdomain {{ $i->subdomain }}</code></td>
<td><form method="post" action="{{ route('reservations.delete', $i->id) }}">@csrf @method('DELETE')<button class="danger">Release</button></form></td></tr>
@empty<tr><td colspan="3" style="color:var(--mute)">Nothing reserved.</td></tr>@endforelse</table></div>
@endsection
