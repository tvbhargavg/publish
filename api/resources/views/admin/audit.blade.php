@extends('layouts.app')
@section('title','Admin · Audit log')
@section('content')
<h1>Admin</h1>
<p class="sub">Who did what, newest first.</p>
@include('admin._tabs')
<x-panel :page="$page" :keys="['q','action']">
<x-slot:toolbar>
<x-search placeholder="Search actor, action or IP…" label="Search audit log" />
<select name="action" aria-label="Action"><option value="">All actions</option>@foreach($actions as $a)<option value="{{ $a }}" @selected(request('action')===$a)>{{ $a }}</option>@endforeach</select>
</x-slot:toolbar>
<table class="stack"><tr><th>Time</th><th>Actor</th><th>Action</th><th>Target</th><th>IP</th></tr>
@forelse($page as $l)
<tr><td data-label="Time">{{ $l->created_at->format('Y-m-d H:i:s') }}</td><td data-label="Actor">{{ $l->actor?->email ?? '—' }}</td><td data-label="Action">{{ $l->action }}</td><td data-label="Target">{{ $l->target_type }} {{ $l->target_id }}</td><td data-label="IP">{{ $l->ip }}</td></tr>
@empty<x-empty :cols="5" :keys="['q','action']" title="No audit entries" text="Actions will be recorded here." />@endforelse
</table>
</x-panel>
@endsection
