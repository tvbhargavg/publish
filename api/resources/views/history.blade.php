@extends('layouts.app')
@section('title','History')
@section('content')
<h1>Tunnel history</h1>
<div class="card"><table><tr><th>Subdomain</th><th>Port</th><th>Status</th><th>Started</th><th>Closed</th><th>Reason</th><th>Requests</th><th>In</th><th>Out</th></tr>
@forelse($tunnels as $t)
<tr><td>{{ $t->subdomain }}</td><td>{{ $t->local_port }}</td><td><span class="pill {{ $t->status === 'active' ? 'online' : 'closed' }}">{{ $t->status }}</span></td>
<td>{{ $t->started_at->format('Y-m-d H:i') }}</td><td>{{ $t->closed_at?->format('Y-m-d H:i') ?? '—' }}</td><td>{{ $t->close_reason ?? '—' }}</td>
<td>{{ number_format($t->requests) }}</td><td>{{ \Illuminate\Support\Number::fileSize($t->bytes_in) }}</td><td>{{ \Illuminate\Support\Number::fileSize($t->bytes_out) }}</td></tr>
@empty<tr><td colspan="9" style="color:var(--mute)">No history yet.</td></tr>@endforelse
</table></div>
<div style="margin-top:12px">{{ $tunnels->links() }}</div>
@endsection
