@extends('layouts.app')
@section('title','Admin')
@section('content')
<h1>Admin</h1>
<p><a href="{{ route('admin.audit') }}">Audit log →</a></p>
<h2>All active tunnels ({{ $active->count() }})</h2>
<div class="card"><table><tr><th>User</th><th>URL</th><th>Status</th><th>Started</th><th></th></tr>
@forelse($active as [$t, $live])
<tr><td>{{ $t->user?->email }}</td><td>{{ $t->publicUrl() }}</td><td><span class="pill {{ $live ? 'online' : 'closed' }}">{{ $live ? 'online' : 'connecting' }}</span></td><td>{{ $t->started_at->diffForHumans() }}</td>
<td><form method="post" action="{{ route('admin.tunnels.disconnect', $t->id) }}">@csrf<button class="danger">Disconnect</button></form></td></tr>
@empty<tr><td colspan="5" style="color:var(--mute)">None.</td></tr>@endforelse</table></div>
<h2>Users</h2>
<div class="card"><table><tr><th>Email</th><th>Max tunnels</th><th>Max reservations</th><th>Status</th><th></th></tr>
@foreach($users as $u)
<tr><td>{{ $u->email }} @if($u->is_admin)<span class="pill">admin</span>@endif</td>
<td colspan="2"><form method="post" action="{{ route('admin.users.update', $u) }}" class="row">@csrf @method('PATCH')
<input name="max_tunnels" type="number" min="0" value="{{ $u->max_tunnels }}" style="width:80px"><input name="max_reservations" type="number" min="0" value="{{ $u->max_reservations }}" style="width:80px"><button>Save</button></form></td>
<td>{{ $u->isDisabled() ? 'disabled' : 'active' }}</td>
<td>@unless($u->id === auth()->id())<form method="post" action="{{ route('admin.users.update', $u) }}">@csrf @method('PATCH')<input type="hidden" name="disabled" value="{{ $u->isDisabled() ? 0 : 1 }}"><button class="{{ $u->isDisabled() ? '' : 'danger' }}">{{ $u->isDisabled() ? 'Enable' : 'Disable' }}</button></form>@endunless</td></tr>
@endforeach</table></div>
<h2>Create user</h2>
<div class="card"><form method="post" action="{{ route('admin.users.create') }}" class="row">@csrf
<input name="name" placeholder="Name" required><input name="email" type="email" placeholder="Email" required><input name="password" type="password" placeholder="Password (10+ chars)" required minlength="10"><button>Create</button></form>
@if($errors->any())<div class="err">{{ $errors->first() }}</div>@endif</div>
@endsection
