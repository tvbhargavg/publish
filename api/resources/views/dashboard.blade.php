@extends('layouts.app')
@section('content')
<h1>Active tunnels</h1>
<div class="stats" style="margin-bottom:20px">
<div class="card stat"><b>{{ $active->count() }} / {{ $user->max_tunnels }}</b><span>active tunnels</span></div>
<div class="card stat"><b>{{ number_format($usage['requests']) }}</b><span>requests this month</span></div>
<div class="card stat"><b>{{ \Illuminate\Support\Number::fileSize($usage['bytes_in'] + $usage['bytes_out']) }}</b><span>traffic this month{{ $user->monthly_bytes_limit ? ' of '.\Illuminate\Support\Number::fileSize($user->monthly_bytes_limit) : '' }}</span></div>
</div>
<div class="card">
@if($active->isEmpty())<p style="color:var(--mute)">No active tunnels. Run <code>mytunnel http 8000</code> on your machine.</p>@else
<table><tr><th>URL</th><th>Local port</th><th>Status</th><th>Last heartbeat</th><th>Started</th><th></th></tr>
@foreach($active as [$t, $live])
<tr><td><a href="{{ $t->publicUrl() }}" target="_blank" rel="noopener noreferrer">{{ $t->publicUrl() }}</a></td><td>{{ $t->local_port }}</td>
<td><span class="pill {{ $live ? 'online' : 'closed' }}">{{ $live ? 'online' : 'connecting' }}</span></td>
<td>{{ $live && $live['last_heartbeat'] ? \Carbon\Carbon::createFromTimestamp($live['last_heartbeat'])->diffForHumans() : '—' }}</td>
<td>{{ $t->started_at->diffForHumans() }}</td>
<td><form method="post" action="{{ route('tunnels.disconnect', $t->id) }}">@csrf<button class="danger">Disconnect</button></form></td></tr>
@endforeach</table>@endif
</div>
@endsection
