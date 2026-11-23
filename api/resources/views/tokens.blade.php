@extends('layouts.app')
@section('title','Tokens')
@section('content')
<h1>API tokens</h1>
<p class="sub">Tokens let the <code>publish</code> CLI act as you. Treat them like passwords.</p>
<x-panel :page="$page" :keys="['q','status']">
<x-slot:actions><button type="button" class="sm" data-open="new-token"><svg class="ic"><use href="#i-plus"/></svg>New token</button></x-slot:actions>
<x-slot:toolbar>
<x-search placeholder="Search by name…" label="Search tokens" />
<select name="status" aria-label="Status"><option value="">All tokens</option><option value="active" @selected(request('status')==='active')>Active</option><option value="expired" @selected(request('status')==='expired')>Expired</option></select>
</x-slot:toolbar>
<table class="stack"><tr><th>Name</th><th>Created</th><th>Last used</th><th>Expires</th><th class="act"></th></tr>
@forelse($page as $t)
<tr><td data-label="Name">{{ $t->name }}</td><td data-label="Created">{{ $t->created_at->format('Y-m-d') }}</td><td data-label="Last used">{{ $t->last_used_at?->diffForHumans() ?? 'never' }}</td><td data-label="Expires">{{ $t->expires_at?->format('Y-m-d') ?? 'never' }}</td>
<td class="act"><form method="post" action="{{ route('tokens.revoke', $t->id) }}">@csrf @method('DELETE')<button class="ib danger" aria-label="Revoke token" title="Revoke"><svg class="ic"><use href="#i-trash"/></svg></button></form></td></tr>
@empty<x-empty :cols="5" :keys="['q','status']" title="No tokens yet" text="Create a token to log in with the CLI." />@endforelse
</table>
</x-panel>

<x-modal id="new-token" title="Create token" :open="old('_form') === 'new-token'">
<form method="post" action="{{ route('tokens.create') }}">@csrf<input type="hidden" name="_form" value="new-token">
<label class="f">Name<input name="name" value="{{ old('name') }}" placeholder="e.g. laptop" required maxlength="64"></label>
<label class="f">Expires in days (optional)<input name="expires_days" type="number" min="1" max="3650" value="{{ old('expires_days') }}" placeholder="never"></label>
@if(old('_form') === 'new-token')@foreach($errors->all() as $e)<div class="err" role="alert">{{ $e }}</div>@endforeach @endif
<div class="modal-actions"><button type="button" class="ghost" data-close>Cancel</button><button>Create token</button></div>
</form>
</x-modal>

@if($new)
<x-modal id="token-created" title="Your new token" :open="true">
<p style="margin:0"><b>Copy it now.</b> For security it will not be shown again.</p>
<x-copy :text="$new" />
<p class="hint">Then run this on your machine and paste the token when asked:</p>
<x-copy text="publish login" />
<div class="modal-actions"><button type="button" data-close>Done</button></div>
</x-modal>
@endif
@endsection
