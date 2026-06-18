{{-- UI Widget (rebuild R5).
     Read-only dashboard stat/wallet card. Emits the EXACT existing markup
     (.d-widget.curve--shape > .d-widget__content > i + h2.d-widget__amount) that
     dashboard.css + material-dashboard.css style, so it is visually inert. The
     amount/label goes in the default slot; an optional `footer` slot holds the
     existing nav link block. Presentation only — owns no form, route, or balance
     logic (callers pass route links via the footer slot). --}}
@props([
    'icon' => 'las la-wallet',
])
<div {{ $attributes->merge(['class' => 'd-widget curve--shape']) }}>
    <div class="d-widget__content">
        <i class="{{ $icon }}"></i>
        <h2 class="d-widget__amount fw-normal amount__responsive">{{ $slot }}</h2>
    </div>
    @isset($footer){{ $footer }}@endisset
</div>
