{{-- UI Feature card (rebuild R1b).
     A reusable icon + title + text card built on the M0 .md-card surface, used by
     the homepage service / feature / why-choose-us sections (replacing the legacy
     .service-card / .feaure-card / .choose-card markup). Pass the icon markup
     (raw <i>, <img>, or CMS icon HTML) via the `icon` slot; the body text via the
     default slot. Emits the same .md-card.md-card--elevated > .md-card__body
     structure as x-ui.card (kept inline so $attributes can be forwarded onto a
     plain element). Presentation only — no CMS binding or link is owned here. --}}
@props([
    'title' => null,
    'align' => 'center',
])
<div {{ $attributes->merge(['class' => "md-card md-card--elevated md-feature-card md-feature-card--{$align}"]) }}>
    <div class="md-card__body">
        @isset($icon)
            <div class="md-feature-card__icon">{{ $icon }}</div>
        @endisset
        @if ($title)
            <h3 class="md-feature-card__title">{{ $title }}</h3>
        @endif
        <div class="md-feature-card__text">{{ $slot }}</div>
    </div>
</div>
