@extends('layouts.app')
@section('title','Audit log')
@section('content')
<h1>Audit log</h1>
<div class="card"><table><tr><th>Time</th><th>Actor</th><th>Action</th><th>Target</th><th>IP</th></tr>
@foreach($logs as $l)<tr><td>{{ $l->created_at->format('Y-m-d H:i:s') }}</td><td>{{ $l->actor?->email ?? '—' }}</td><td>{{ $l->action }}</td><td>{{ $l->target_type }} {{ $l->target_id }}</td><td>{{ $l->ip }}</td></tr>@endforeach
</table></div><div style="margin-top:12px">{{ $logs->links() }}</div>
@endsection
