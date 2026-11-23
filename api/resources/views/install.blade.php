@extends('layouts.app')
@section('title','Install CLI')
@section('content')
@php
$base = rtrim(config('app.url'), '/');
$port = config('tunnel.example_port');
@endphp
<h1>Install the CLI</h1>
<p class="sub">One small program, nothing else to install. Works on any computer, on any network.</p>
<div class="stackcol" style="max-width:none">

<div class="card sec" data-os="unix">
<h3>Linux &amp; macOS <span class="pill premium" data-rec hidden>Recommended for your system</span></h3>
<p class="hint">Open a terminal and run this one line. It downloads, verifies and installs <code>publish</code> and points it at this server.</p>
<x-copy :text="'curl -fsSL '.$base.'/install.sh | sh'" />
</div>

<div class="card sec" data-os="windows">
<h3>Windows <span class="pill premium" data-rec hidden>Recommended for your system</span></h3>
<p class="hint">Open PowerShell and run this one line.</p>
<x-copy :text="'irm '.$base.'/install.ps1 | iex'" />
</div>

<div class="card sec">
<h3>Manual download</h3>
<p class="hint">Prefer to install it yourself? Download the file for your system, make it executable and put it on your PATH.</p>
<div style="display:grid;gap:8px;margin-top:12px">
@foreach($items as $i)
<div class="row" style="justify-content:space-between;align-items:center;border:1px solid var(--line);border-radius:10px;padding:8px 12px">
<div><b>{{ $i['label'] }}</b> <span class="muted" style="font-size:13px">{{ $i['size'] ?? 'not built yet' }}</span></div>
@if($i['ready'])<a class="btn sm" style="min-height:34px;font-size:13px;padding:0 12px" href="{{ route('downloads', $i['file']) }}" download>Download</a>@else<span class="pill">unavailable</span>@endif
</div>
@endforeach
</div>
<p class="hint" style="margin-top:12px">Then tell it where this server is:</p>
<x-copy :text="'publish config server '.$server" />
@unless(config('tunnel.tls'))<x-copy text="publish config tls off" />@endunless
</div>

<div class="card sec">
<h3>Then: log in and start your first tunnel</h3>
<p class="hint">1. Create a token on the <a href="{{ route('tokens') }}">Tokens</a> page, then paste it when asked:</p>
<x-copy text="publish login" />
<p class="hint" style="margin-top:12px">2. Share the app running on your computer (replace {{ $port }} with its port):</p>
<x-copy :text="'publish http '.$port" />
<p class="hint" style="margin-top:8px">It prints your public URL. Press Ctrl+C to stop.</p>
</div>
</div>
<script>
(function(){var p=(navigator.userAgentData&&navigator.userAgentData.platform)||navigator.platform||'';var ua=navigator.userAgent||'';
var win=/win/i.test(p)||/Windows/i.test(ua);var key=win?'windows':'unix';
var c=document.querySelector('[data-os="'+key+'"]');if(!c)return;
var r=c.querySelector('[data-rec]');if(r)r.hidden=false;c.parentNode.insertBefore(c,c.parentNode.firstChild);})();
</script>
@endsection
