@extends('Template::layouts.frontend')

@php
    $content = @getContent('blog.content', true)->data_values;
@endphp

@section('content')
    <section class="pt-100 pb-100 section--bg">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-6">
                    <div class="section-header text-center wow fadeInUp" data-wow-duration="0.3" data-wow-delay="0.3s">
                        <span class="section-subtitle border-left">{{ __(@$content->title) }}</span>
                        <h2 class="section-title">{{ __(@$content->heading) }}</h2>
                        <p class="mt-3">{{ __(@$content->subheading) }}</p>
                    </div>
                </div>
            </div><!-- row end -->
            <div class="row gy-4 justify-content-center">
                @foreach ($blogs as $blog)
                    <div class="col-lg-4 col-md-6 wow fadeInUp" data-wow-duration="0.3" data-wow-delay="0.1s">
                        <x-ui.blog-card
                            :image="frontendImage('blog', @$blog->data_values->image)"
                            :day="showDateTime(@$blog->created_at, 'd')"
                            :month="showDateTime(@$blog->created_at, 'M')"
                            :title="__(@$blog->data_values->title)"
                            :url="route('blog.details', $blog->slug)"
                            :excerpt="strLimit(strip_tags(@$blog->data_values->description), 200)">
                            <a href="{{ route('blog.details', $blog->slug) }}" class="md-blog-card__more">@lang('Read More')</a>
                        </x-ui.blog-card>
                    </div>
                @endforeach

            </div>
            <div class="mt-5">
                {{ paginateLinks($blogs) }}
            </div>
        </div>
    </section>
@endsection
