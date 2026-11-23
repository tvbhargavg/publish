@extends('layouts.app')
@section('title','History')
@section('content')
<h1>Tunnel history</h1>
<p class="sub">Every tunnel you have opened.</p>
<x-panel :page="$page" :keys="['q','status','period']">
<x-slot:toolbar>
<x-search placeholder="Search by subdomain…" label="Search history" />
<select name="status" aria-label="Status"><option value="">All statuses</option><option value="active" @selected(request('status')==='active')>Active</option><option value="closed" @selected(request('status')==='closed')>Closed</option></select>
<select name="period" aria-label="Period"><option value="">All time</option>@foreach(['7'=>'Last 7 days','30'=>'Last 30 days','90'=>'Last 90 days'] as $k=>$v)<option value="{{ $k }}" @selected(request('period')===(string)$k)>{{ $v }}</option>@endforeach</select>
</x-slot:toolbar>
<table class="stack"><tr><th>Subdomain</th><th>Port</th><th>Status</th><th>Started</th><th>Closed</th><th>Reason</th><th>Requests</th><th>In</th><th>Out</th></tr>
@forelse($page as $t)
<tr><td data-label="Subdomain">{{ $t->subdomain }}</td><td data-label="Port">{{ $t->local_port }}</td><td data-label="Status"><span class="pill {{ $t->status === 'active' ? 'online' : 'closed' }}">{{ $t->status }}</span></td>
<td data-label="Started">{{ $t->started_at->format('Y-m-d H:i') }}</td><td data-label="Closed">{{ $t->closed_at?->format('Y-m-d H:i') ?? '—' }}</td><td data-label="Reason">{{ $t->close_reason ?? '—' }}</td>
<td data-label="Requests">{{ number_format($t->requests) }}</td><td data-label="In">{{ \Illuminate\Support\Number::fileSize($t->bytes_in) }}</td><td data-label="Out">{{ \Illuminate\Support\Number::fileSize($t->bytes_out) }}</td></tr>
@empty<x-empty :cols="9" :keys="['q','status','period']" title="No history yet" text="Tunnels you open will be listed here." />@endforelse
</table>
</x-panel>
@endsection
