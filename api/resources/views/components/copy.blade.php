@props(['text'])
<div class="copybox"><code>{{ $text }}</code><button type="button" class="ib bordered" data-copy="{{ $text }}" aria-label="Copy" title="Copy"><svg class="ic"><use href="#i-copy"/></svg></button></div>
