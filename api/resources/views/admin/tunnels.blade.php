@extends('layouts.app')
@section('title','Admin · Tunnels')
@section('content')
<h1>Admin</h1>
<p class="sub">Every tunnel that is live right now.</p>
@include('admin._tabs')
<x-panel :page="$page">
<x-slot:toolbar><x-search placeholder="Search by user or subdomain…" label="Search tunnels" /></x-slot:toolbar>
<table class="stack"><tr><th>User</th><th>URL</th><th>Status</th><th>Started</th><th class="act"></th></tr>
@forelse($page as [$t, $live])
<tr><td data-label="User">{{ $t->user?->email }}</td><td data-label="URL">{{ $t->publicUrl() }}</td><td data-label="Status"><span class="pill {{ $live ? 'online' : 'closed' }}">{{ $live ? 'online' : 'connecting' }}</span></td><td data-label="Started">{{ $t->started_at->diffForHumans() }}</td>
<td class="act"><form method="post" action="{{ route('admin.tunnels.disconnect', $t->id) }}">@csrf<button class="ib danger" aria-label="Disconnect tunnel" title="Disconnect"><svg class="ic"><use href="#i-unplug"/></svg></button></form></td></tr>
@empty<x-empty :cols="5" title="No active tunnels" text="Live tunnels from all users appear here." />@endforelse
</table>
</x-panel>
@endsection
