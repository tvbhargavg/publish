@extends('layouts.app')
@section('content')
<h1>Tunnels</h1>
<p class="sub">Expose a local port to the internet. New here? <a href="{{ route('install') }}">Install the CLI</a>.</p>
<div class="stats">
<div class="card stat"><b>{{ $activeCount }} / {{ $user->tunnelLimit() }}</b><span>active tunnels · <span class="pill {{ $user->tunnel_access ? 'premium' : '' }}">{{ $user->tunnel_access ? 'premium' : 'free' }}</span></span></div>
<div class="card stat"><b>{{ number_format($usage['requests']) }}</b><span>requests this month</span></div>
<div class="card stat"><b>{{ \Illuminate\Support\Number::fileSize($usage['bytes_in'] + $usage['bytes_out']) }}</b><span>traffic this month{{ $user->monthly_bytes_limit ? ' of '.\Illuminate\Support\Number::fileSize($user->monthly_bytes_limit) : '' }}</span></div>
</div>
<x-panel :page="$page">
<x-slot:toolbar><x-search placeholder="Search by subdomain…" label="Search tunnels" /></x-slot:toolbar>
<table class="stack"><tr><th>URL</th><th>Local port</th><th>Status</th><th>Last heartbeat</th><th>Started</th><th class="act"></th></tr>
@forelse($page as [$t, $live])
<tr><td data-label="URL"><a href="{{ $t->publicUrl() }}" target="_blank" rel="noopener noreferrer">{{ $t->publicUrl() }}</a></td><td data-label="Port">{{ $t->local_port }}</td>
<td data-label="Status"><span class="pill {{ $live ? 'online' : 'closed' }}">{{ $live ? 'online' : 'connecting' }}</span></td>
<td data-label="Heartbeat">{{ $live && $live['last_heartbeat'] ? \Carbon\Carbon::createFromTimestamp($live['last_heartbeat'])->diffForHumans() : '—' }}</td>
<td data-label="Started">{{ $t->started_at->diffForHumans() }}</td>
<td class="act"><form method="post" action="{{ route('tunnels.disconnect', $t->id) }}">@csrf<button class="ib danger" aria-label="Disconnect tunnel" title="Disconnect"><svg class="ic"><use href="#i-unplug"/></svg></button></form></td></tr>
@empty<x-empty :cols="6" title="No active tunnels" text="Start one from your terminal with the commands below." />@endforelse
</table>
</x-panel>
@if($activeCount === 0 && ! request()->filled('q'))
@php $base = rtrim(config('app.url'), '/'); $port = config('tunnel.example_port'); @endphp
<h2>Quick start</h2>
<div class="card" style="display:grid;gap:14px">
<div><b>1. Install the CLI</b> on your computer (<a href="{{ route('install') }}">more options</a>)</div>
<div class="muted" style="font-size:13px;margin-bottom:-8px">Linux / macOS</div>
<x-copy :text="'curl -fsSL '.$base.'/install.sh | sh'" />
<div class="muted" style="font-size:13px;margin-bottom:-8px">Windows (PowerShell)</div>
<x-copy :text="'irm '.$base.'/install.ps1 | iex'" />
<div><b>2. Log in</b> with a token from the <a href="{{ route('tokens') }}">Tokens</a> page</div>
<x-copy text="publish login" />
<div><b>3. Start a tunnel</b> to the app on your computer (replace {{ $port }} with its port)</div>
<x-copy :text="'publish http '.$port" />
</div>
@endif
@endsection
