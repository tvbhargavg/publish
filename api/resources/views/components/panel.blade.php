@props(['page' => null, 'keys' => ['q']])
@php
$filtered = collect($keys)->contains(fn ($k) => request()->filled($k));
$keep = collect(request()->except(['page', 'per_page']))->filter(fn ($v) => is_scalar($v));
$per = $page ? $page->perPage() : 10;
@endphp
<section class="card panel">
<div class="bar">
@isset($toolbar)
<form method="get" action="{{ url()->current() }}" class="toolbar" data-auto role="search">
{{ $toolbar }}
@if($filtered)<a class="reset" href="{{ url()->current() }}">Clear</a>@endif
</form>
@endisset
@isset($actions)<div class="ph-actions">{{ $actions }}</div>@endisset
</div>
<div class="tablewrap">{{ $slot }}</div>
@if($page && $page->total() > 0)
<div class="panel-foot">
<span>Showing <b>{{ $page->firstItem() }}</b> to <b>{{ $page->lastItem() }}</b> of <b>{{ $page->total() }}</b> entries</span>
<div class="pager">
<form method="get" action="{{ url()->current() }}" class="pg" data-auto>@foreach($keep as $k => $v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endforeach
<label for="pp-{{ spl_object_id($page) }}">Rows per page</label>
<select id="pp-{{ spl_object_id($page) }}" name="per_page">@foreach([10, 25, 50] as $n)<option value="{{ $n }}" @selected($per === $n)>{{ $n }}</option>@endforeach</select></form>
@if($page->lastPage() > 1)
<form method="get" action="{{ url()->current() }}" class="pg">@foreach($keep as $k => $v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endforeach<input type="hidden" name="per_page" value="{{ $per }}">
<label for="go-{{ spl_object_id($page) }}">Go to</label><input id="go-{{ spl_object_id($page) }}" type="number" name="page" min="1" max="{{ $page->lastPage() }}" value="{{ $page->currentPage() }}"><button class="ghost sm">Go</button></form>
@endif
<div class="pgnav">
@if($page->onFirstPage())<span class="ib bordered off" aria-hidden="true"><svg class="ic"><use href="#i-left"/></svg></span>@else<a class="ib bordered" href="{{ $page->previousPageUrl() }}" aria-label="Previous page"><svg class="ic"><use href="#i-left"/></svg></a>@endif
@if($page->hasMorePages())<a class="ib bordered" href="{{ $page->nextPageUrl() }}" aria-label="Next page"><svg class="ic"><use href="#i-right"/></svg></a>@else<span class="ib bordered off" aria-hidden="true"><svg class="ic"><use href="#i-right"/></svg></span>@endif
</div>
</div>
</div>
@endif
</section>
