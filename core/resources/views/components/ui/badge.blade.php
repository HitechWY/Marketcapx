{{-- UI Badge / status chip (rebuild design system R1 foundation).
     Renders the M0 .md-chip component. Optional tone maps to a Material role
     for status colour (success|warning|danger|info|primary). Presentation only. --}}
@props([
    'tone' => null,
])
<span {{ $attributes->merge(['class' => 'md-chip' . ($tone ? " md-chip--tone-{$tone}" : '')]) }}>{{ $slot }}</span>
