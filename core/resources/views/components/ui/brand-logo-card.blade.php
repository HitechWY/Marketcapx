{{-- UI Brand-logo card (rebuild R2).
     A neutral outlined surface that holds a client/partner logo, used by the
     homepage business section (replacing the legacy .coutomer-single markup).
     Pass the <img> via the default slot. Emits the M0 .md-card surface inline
     so $attributes forwards onto a plain element. Presentation only. --}}
@props([])
<div {{ $attributes->merge(['class' => 'md-card md-card--outlined md-brand-logo']) }}>
    <div class="md-brand-logo__inner">{{ $slot }}</div>
</div>
