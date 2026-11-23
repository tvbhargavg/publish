@props(['cols', 'title', 'text' => null, 'keys' => ['q']])
@php $filtered = collect($keys)->contains(fn ($k) => request()->filled($k)); @endphp
<tr><td colspan="{{ $cols }}" class="emptycell">
@if($filtered)<b>No results</b><span>Nothing matches your search or filters. <a href="{{ url()->current() }}">Clear filters</a></span>
@else<b>{{ $title }}</b>@if($text)<span>{{ $text }}</span>@endif @endif
</td></tr>
