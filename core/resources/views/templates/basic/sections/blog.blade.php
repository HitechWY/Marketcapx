@php
    $content = @getContent('blog.content', true)->data_values;
    $elements = @getContent('blog.element', false, 3);
@endphp
<section class="pt-100 pb-100 section--bg">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-6">
                <x-ui.section-heading class="wow fadeInUp" data-wow-duration="0.3" data-wow-delay="0.3s" :subtitle="__(@$content->title)" :heading="__(@$content->heading)" :subheading="__(@$content->subheading)" />
            </div>
        </div><!-- row end -->
        <div class="row gy-4 justify-content-center">
            @foreach ($elements as $element)
                <div class="col-lg-4 col-md-6 wow fadeInUp" data-wow-duration="0.3" data-wow-delay="0.1s">
                    <x-ui.blog-card
                        :image="frontendImage('blog', 'thumb_'.@$element->data_values->image)"
                        :day="showDateTime(@$element->created_at, 'd')"
                        :month="showDateTime(@$element->created_at, 'M')"
                        :title="__(@$element->data_values->title)"
                        :url="route('blog.details', $element->slug)"
                        :excerpt="strLimit(strip_tags(@$element->data_values->description), 200)">
                        <a href="{{ route('blog.details', $element->slug) }}" class="md-blog-card__more">@lang('Read More')</a>
                    </x-ui.blog-card>
                </div>
            @endforeach
        </div>
    </div>
</section>
