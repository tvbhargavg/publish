@props(['placeholder' => 'Search…', 'label' => 'Search'])
<label class="search"><svg class="ic"><use href="#i-search"/></svg><input type="search" name="q" value="{{ request('q') }}" placeholder="{{ $placeholder }}" aria-label="{{ $label }}"></label>
