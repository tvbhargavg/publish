@extends('layouts.app')
@section('title','Subdomains')
@section('content')
<h1>Reserved subdomains</h1>
<p class="sub">@if($user->tunnel_access)Reserve names so your URL stays the same. Your tunnels use your reserved names automatically.@else Free plan: {{ $user->reservationLimit() }} reserved subdomain. Just run publish http {{ config('tunnel.example_port') }}; your tunnel always uses it, so your URL never changes.@endif</p>
@php $full = $used >= $user->reservationLimit(); @endphp
<x-panel :page="$page">
<x-slot:actions><span class="muted" style="font-size:13px">{{ $used }} / {{ $user->reservationLimit() }} used</span><button type="button" class="sm" data-open="reserve" @disabled($full) title="{{ $full ? 'Reservation limit reached' : 'Reserve a subdomain' }}"><svg class="ic"><use href="#i-plus"/></svg>Reserve</button></x-slot:actions>
<x-slot:toolbar><x-search placeholder="Search subdomains…" label="Search subdomains" /></x-slot:toolbar>
<table class="stack"><tr><th>Domain</th><th>Start command</th><th class="act"></th></tr>
@forelse($page as $i)
<tr><td data-label="Domain">{{ $i->subdomain }}.{{ config('tunnel.base_domain') }}</td><td data-label="Command"><x-copy :text="$user->tunnel_access ? 'publish http '.config('tunnel.example_port').' --subdomain '.$i->subdomain : 'publish http '.config('tunnel.example_port')" /></td>
<td class="act"><form method="post" action="{{ route('reservations.delete', $i->id) }}">@csrf @method('DELETE')<button class="ib danger" aria-label="Release subdomain" title="Release"><svg class="ic"><use href="#i-trash"/></svg></button></form></td></tr>
@empty<x-empty :cols="3" title="Nothing reserved yet" text="Reserve a subdomain to keep the same URL every time." />@endforelse
</table>
</x-panel>
<x-modal id="reserve" title="Reserve a subdomain" :open="old('_form') === 'reserve'">
<form method="post" action="{{ route('reservations.create') }}">@csrf<input type="hidden" name="_form" value="reserve">
<label class="f">Subdomain<span class="row" style="align-items:center;flex-wrap:nowrap"><input name="subdomain" value="{{ old('subdomain') }}" placeholder="my-app" required maxlength="63" style="min-width:0;flex:1"><span class="muted">.{{ config('tunnel.base_domain') }}</span></span></label>
@error('subdomain')<div class="err" role="alert">{{ $message }}</div>@enderror
<p class="hint">3–63 characters: lowercase letters, numbers and hyphens.</p>
<div class="modal-actions"><button type="button" class="ghost" data-close>Cancel</button><button>Reserve</button></div>
</form>
</x-modal>
@endsection
