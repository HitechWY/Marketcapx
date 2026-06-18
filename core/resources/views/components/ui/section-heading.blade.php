{{-- UI Section heading (rebuild R3).
     DRY wrapper for the repeated centered `.section-header` block used by the
     public homepage/listing sections. Emits the EXACT existing theme classes
     (.section-header.text-center, .section-subtitle.border-left, .section-title,
     .mt-3) so main.css + the overlay CSS layers style it identically — this is a
     markup de-duplication, visually inert. wow classes / data-wow-* pass through
     via $attributes onto the wrapper. `light` adds the theme's text-white. --}}
@props([
    'subtitle' => null,
    'heading' => null,
    'subheading' => null,
    'light' => false,
])
@php $tw = $light ? ' text-white' : ''; @endphp
<div {{ $attributes->merge(['class' => 'section-header text-center']) }}>
    @if ($subtitle)<span class="section-subtitle border-left{{ $tw }}">{{ $subtitle }}</span>@endif
    <h2 class="section-title{{ $tw }}">{{ $heading }}</h2>
    @if ($subheading)<p class="mt-3{{ $tw }}">{{ $subheading }}</p>@endif
</div>
