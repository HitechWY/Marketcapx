@extends('Template::layouts.app')
@section('panel')
    <section class="section bg--light">
        <div class="container">
            @php
                $escrowSuccess = getContent('escrow_success_page.content', true);
            @endphp
            <div class="alert-custom">
                <div class="alert-custom__content">
                    <img class="alert-custom__thumb" src="{{ frontendImage('escrow_success_page', @$escrowSuccess->data_values->image, '300x300') }}" alt="">
                    <h4 class="alert-custom__title text-white">{{ __(@$message) }}</h4>
                    <p class="alert-custom__desc text-white text-center mt-4">
                        @lang('You will be redirected to your escrow overview, after in few seconds.')
                    </p>
                </div>
            </div>
        </div>
    </section>
@endsection

@push('script')
    <script>
        var distance = 5;
        var x = setInterval(function() {
            distance--;
            if (distance <= 0) {
                clearInterval(x);
                location.href = "{{ route('user.escrow.overview', $escrow->id) }}";
            }
        }, 1000);
    </script>
@endpush
