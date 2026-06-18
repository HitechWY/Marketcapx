@php
    $content = @getContent('service.content', true)->data_values;
    $elements = @getContent('service.element', orderById:true);
@endphp

<section class="pt-100 pb-150 position-relative z-index-2 section--bg2 section-shape">
    <div class="section-wave-img opacity50">
        <img src="{{ frontendImage('service', $content->background_image, '1920x1080') }}" alt="@lang('image')">
    </div>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-6">
                <x-ui.section-heading class="wow fadeInUp" data-wow-duration="0.3" data-wow-delay="0.3s" light :heading="__(@$content->heading)" :subheading="__(@$content->subheading)" />
            </div>
        </div><!-- row end -->
        <div class="row gy-4">
            @foreach($elements as $element)
                <div class="col-lg-4 col-md-6 wow fadeInUp" data-wow-duration="0.3" data-wow-delay="0.1s">
                    <x-ui.feature-card :title="@$element->data_values->title" align="center">
                        <x-slot:icon>@php echo @$element->data_values->service_icon; @endphp</x-slot:icon>
                        {{ __(@$element->data_values->description) }}
                    </x-ui.feature-card>
                </div>
            @endforeach
        </div>
    </div>
</section>
