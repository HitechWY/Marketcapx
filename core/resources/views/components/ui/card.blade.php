{{-- UI Card (rebuild design system R1 foundation).
     Renders the M0 .md-card surface (material-components.css). Variant:
     elevated|filled|outlined. Set :flush to drop the inner padding wrapper.
     Presentation only. --}}
@props([
    'variant' => 'elevated',
    'flush' => false,
])
<div {{ $attributes->merge(['class' => "md-card md-card--{$variant}"]) }}>
    @if ($flush)
        {{ $slot }}
    @else
        <div class="md-card__body">{{ $slot }}</div>
    @endif
</div>
