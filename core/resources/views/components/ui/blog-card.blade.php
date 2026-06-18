{{-- UI Blog card (rebuild R1b).
     Reusable post card (thumb + date badge + title link + excerpt + footer slot),
     replacing the legacy .blog-card markup on the homepage blog section and the
     blog listing page. Emits the M0 .md-card.md-card--elevated surface inline so
     $attributes forwards onto a plain element. All links/CMS values are passed in
     by the caller — this component owns no route or binding. Presentation only. --}}
@props([
    'image' => null,
    'day' => null,
    'month' => null,
    'title' => null,
    'url' => '#',
    'excerpt' => null,
])
<div {{ $attributes->merge(['class' => 'md-card md-card--elevated md-blog-card']) }}>
    <div class="md-blog-card__thumb">
        <img src="{{ $image }}" alt="{{ $title }}">
        @if (!is_null($day))
            <span class="md-blog-card__date">
                <strong>{{ $day }}</strong>
                <span>{{ $month }}</span>
            </span>
        @endif
    </div>
    <div class="md-card__body">
        <h4 class="md-blog-card__title"><a href="{{ $url }}">{{ $title }}</a></h4>
        @if ($excerpt)
            <p class="md-blog-card__excerpt">{{ $excerpt }}</p>
        @endif
        {{ $slot }}
    </div>
</div>
