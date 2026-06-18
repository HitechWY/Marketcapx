{{-- UI Button (rebuild design system R1 foundation).
     Renders the M0 .md-button component (material-components.css). Use as a link
     (href) or a button. Variant: filled|tonal|outlined|text|elevated.
     Presentation only — pass through type/name/value/etc. via $attributes so it
     can wrap existing submit buttons WITHOUT changing their contract. --}}
@props([
    'variant' => 'filled',
    'href' => null,
    'type' => 'button',
    'icon' => null,
])
@php $classes = "md-button md-button--{$variant} md-state"; @endphp
@if ($href !== null)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if ($icon)<span class="material-symbols-outlined md-button__icon">{{ $icon }}</span>@endif
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if ($icon)<span class="material-symbols-outlined md-button__icon">{{ $icon }}</span>@endif
        {{ $slot }}
    </button>
@endif
