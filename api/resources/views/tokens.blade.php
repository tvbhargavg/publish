@extends('layouts.app')
@section('title','Tokens')
@section('content')
<h1>API tokens</h1>
@if($new)<div class="secret"><b>Copy your new token now. It will not be shown again.</b><br><code>{{ $new }}</code><br><small>Use it with <code>mytunnel login</code>.</small></div>@endif
<div class="card"><form method="post" action="{{ route('tokens.create') }}" class="row">@csrf
<input name="name" placeholder="Token name (e.g. laptop)" required maxlength="64">
<input name="expires_days" type="number" min="1" max="3650" placeholder="Expires in days (optional)">
<button>Create token</button></form>
@error('name')<div class="err">{{ $message }}</div>@enderror</div>
<h2>Your tokens</h2>
<div class="card"><table><tr><th>Name</th><th>Created</th><th>Last used</th><th>Expires</th><th></th></tr>
@forelse($tokens as $t)
<tr><td>{{ $t->name }}</td><td>{{ $t->created_at->format('Y-m-d') }}</td><td>{{ $t->last_used_at?->diffForHumans() ?? 'never' }}</td><td>{{ $t->expires_at?->format('Y-m-d') ?? 'never' }}</td>
<td><form method="post" action="{{ route('tokens.revoke', $t->id) }}">@csrf @method('DELETE')<button class="danger">Revoke</button></form></td></tr>
@empty<tr><td colspan="5" style="color:var(--mute)">No tokens.</td></tr>@endforelse</table></div>
@endsection
