{{-- UI Testimonial card (rebuild R2).
     Quote + author block for the homepage testimonial section. Used INSIDE each
     slick `.single-slide` (the slide element itself is kept as the slider's direct
     child so the slick init / asNavFor sync is untouched). Quote via the default
     slot; author name/designation via props. Colours use --md-sys tokens so it
     adapts to light/dark. Presentation only. --}}
@props([
    'name' => null,
    'designation' => null,
])
<div {{ $attributes->merge(['class' => 'md-testimonial']) }}>
    <span class="md-testimonial__mark" aria-hidden="true">&ldquo;</span>
    <div class="md-testimonial__quote">{{ $slot }}</div>
    @if ($name)
        <div class="md-testimonial__author">
            <span class="md-testimonial__name">{{ $name }}</span>
            @if ($designation)
                <span class="md-testimonial__role">{{ $designation }}</span>
            @endif
        </div>
    @endif
</div>
