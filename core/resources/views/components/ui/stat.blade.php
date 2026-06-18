{{-- UI Stat (rebuild R2).
     Number + label stat tile, used by the homepage overview section (replacing
     the legacy .overview-single markup). Presentation only — the value/label are
     passed in by the caller (CMS-bound there). Colours inherit so the tile adapts
     to whichever section context (e.g. the dark overview overlay) it sits in. --}}
@props([
    'value' => null,
    'label' => null,
    'align' => 'left',
])
<div {{ $attributes->merge(['class' => "md-stat md-stat--{$align}"]) }}>
    <div class="md-stat__value">{{ $value ?? $slot }}</div>
    @if ($label)
        <div class="md-stat__label">{{ $label }}</div>
    @endif
</div>
