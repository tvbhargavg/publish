@extends('layouts.app')
@section('title','Admin · Users')
@section('content')
<h1>Admin</h1>
<p class="sub">Free users get {{ config('tunnel.free_max_tunnels') }} tunnel and {{ config('tunnel.free_max_reservations') }} reserved subdomain. Premium users get the numbers you set.</p>
@include('admin._tabs')
<x-panel :page="$page" :keys="['q','plan','status','verified']">
<x-slot:actions><button type="button" class="sm" data-open="create-user"><svg class="ic"><use href="#i-plus"/></svg>Create user</button></x-slot:actions>
<x-slot:toolbar>
<x-search placeholder="Search name or email…" label="Search users" />
<select name="plan" aria-label="Plan"><option value="">All plans</option>@foreach(['free'=>'Free','premium'=>'Premium','admin'=>'Admins'] as $k=>$v)<option value="{{ $k }}" @selected(request('plan')===$k)>{{ $v }}</option>@endforeach</select>
<select name="status" aria-label="Status"><option value="">Any status</option><option value="active" @selected(request('status')==='active')>Active</option><option value="disabled" @selected(request('status')==='disabled')>Disabled</option></select>
<select name="verified" aria-label="Email"><option value="">Any email state</option><option value="yes" @selected(request('verified')==='yes')>Verified</option><option value="no" @selected(request('verified')==='no')>Unverified</option></select>
</x-slot:toolbar>
<table class="stack"><tr><th>User</th><th>Plan</th><th>Tunnels</th><th>Subdomains</th><th>Email</th><th>Status</th><th>Joined</th><th class="act">Actions</th></tr>
@forelse($page as $u)
<tr><td data-label="User"><div style="min-width:0;text-align:inherit"><span class="t1">{{ $u->email }}</span>@if($u->name)<span class="t2">{{ $u->name }}</span>@endif</div></td>
<td data-label="Plan">@if($u->is_admin)<span class="pill">admin</span> @endif<span class="pill {{ $u->tunnel_access ? 'premium' : '' }}">{{ $u->tunnel_access ? 'premium' : 'free' }}</span></td>
<td data-label="Tunnels">{{ $u->tunnelLimit() }}</td><td data-label="Subdomains">{{ $u->reservationLimit() }}</td>
<td data-label="Email"><span class="pill {{ $u->hasVerifiedEmail() ? 'online' : 'warnp' }}">{{ $u->hasVerifiedEmail() ? 'verified' : 'unverified' }}</span></td>
<td data-label="Status"><span class="pill {{ $u->isDisabled() ? 'bad' : 'online' }}">{{ $u->isDisabled() ? 'disabled' : 'active' }}</span></td>
<td data-label="Joined">{{ $u->created_at->format('Y-m-d') }}</td>
<td class="act"><button type="button" class="ib" aria-label="Manage {{ $u->email }}" title="Edit user" data-open="manage-user" data-manage
 data-action="{{ route('admin.users.update', $u) }}" data-email="{{ $u->email }}" data-premium="{{ $u->tunnel_access ? 1 : 0 }}" data-tunnels="{{ $u->max_tunnels }}" data-reservations="{{ $u->max_reservations }}" data-disabled="{{ $u->isDisabled() ? 1 : 0 }}" data-self="{{ $u->id === auth()->id() ? 1 : 0 }}"><svg class="ic"><use href="#i-edit"/></svg></button></td></tr>
@empty<x-empty :cols="8" :keys="['q','plan','status','verified']" title="No users" text="Create the first user with the button above." />@endforelse
</table>
</x-panel>

<x-modal id="manage-user" title="Manage user">
<form method="post" action="#">@csrf @method('PATCH')
<div><b data-f="email"></b></div>
<label class="check"><input type="hidden" name="tunnel_access" value="0"><input type="checkbox" name="tunnel_access" value="1"> Premium plan</label>
<div class="grid2">
<label class="f">Premium tunnels<input name="max_tunnels" type="number" min="0" max="1000" required></label>
<label class="f">Premium subdomains<input name="max_reservations" type="number" min="0" max="1000" required></label>
</div>
<p class="hint">Without premium the user gets {{ config('tunnel.free_max_tunnels') }} tunnel and {{ config('tunnel.free_max_reservations') }} subdomain, whatever the numbers above say.</p>
<label class="check"><input type="hidden" name="disabled" value="0"><input type="checkbox" name="disabled" value="1"> Disable account (signs them out and blocks tunnels)</label>
<div class="modal-actions"><button type="button" class="ghost" data-close>Cancel</button><button>Save changes</button></div>
</form>
</x-modal>

<x-modal id="create-user" title="Create user" :open="old('_form') === 'create-user'">
<form method="post" action="{{ route('admin.users.create') }}">@csrf<input type="hidden" name="_form" value="create-user">
<label class="f">Name<input name="name" value="{{ old('name') }}" required></label>
<label class="f">Email<input name="email" type="email" value="{{ old('email') }}" required></label>
<label class="f">Password (min 10 characters)<input name="password" type="password" required minlength="10"></label>
@if(old('_form') === 'create-user')@foreach($errors->all() as $e)<div class="err" role="alert">{{ $e }}</div>@endforeach @endif
<p class="hint">Admin-created users are premium and already verified.</p>
<div class="modal-actions"><button type="button" class="ghost" data-close>Cancel</button><button>Create user</button></div>
</form>
</x-modal>
@endsection
