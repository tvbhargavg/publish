@props(['id', 'title', 'open' => false])
<dialog id="{{ $id }}" class="modal" aria-labelledby="{{ $id }}-t" @if($open) data-autoopen @endif>
<div class="modal-card">
<div class="modal-head"><h3 id="{{ $id }}-t">{{ $title }}</h3><button type="button" class="ib" data-close aria-label="Close" title="Close"><svg class="ic"><use href="#i-x"/></svg></button></div>
<div class="modal-body">{{ $slot }}</div>
</div></dialog>
