@php
    $cookie = App\Models\Frontend::where('data_keys', 'cookie.data')->first();
@endphp

@if ($cookie->data_values->status == 1 && !\Cookie::get('gdpr_cookie'))
    <!-- cookies dark version start -->
    <div class="cookies-card text-center hide">
        <div class="cookies-card__icon bg--base">
            <i class="las la-cookie-bite"></i>
        </div>
        <p class="mt-4 cookies-card__content">
            {{ $cookie->data_values->short_desc }}
            <a href="{{ route('cookie.policy') }}" target="_blank" class="text--base">@lang('learn more')</a>
        </p>
        <div class="cookies-card__btn mt-4">
            <x-ui.button href="javascript:void(0)" variant="filled" class="w-100 policy">@lang('Allow')</x-ui.button>
        </div>
    </div>
    <!-- cookies dark version end -->
@endif
