@extends('Template::layouts.user_master')

@section('content')
    @php
        $currentStep = (int) request()->step;
        $escrowInfo = session('escrow_info');
    @endphp
    <div class="col-xl-10">
        <div class="card style--two">
            <div class="card-header d-flex flex-wrap align-items-center justify-content-center">
                <div class="bank-icon  me-2">
                    <i class="las la-exchange-alt"></i>
                </div>
                <h4 class="fw-normal">{{ __($pageTitle) }}</h4>
            </div>
            <div class="card-body p-4">

                <div class="row justify-content-center">
                    <div class="col-lg-8">
                        <div class="d-widget">
                            <div class="d-widget__content px-5">
                                <form class="escrow-form" action="{{ route('user.escrow.step.two.submit') }}" method="POST">
                                    @csrf
                                    @include('Template::user.escrow.steper')

                                    <div class="escrow-form__body">
                                        <h4 class="escrow-form__title text-start">@lang('Escrow Category')</h4>
                                        <div class="escrow-form__wrapper mb-4">
                                            <div class="form-group">
                                                <select class="form-control form--control select2" name="category_id" data-placeholder="@lang('Escrow category')..." required>
                                                    <option value=""></option>
                                                    @foreach ($categories as $category)
                                                        <option value="{{ $category->id }}" data-is_delivery="{{ $category->is_delivery }}" @selected(@$escrowInfo['category_id'] == $category->id)>
                                                            {{ __($category->name) }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>

                                        <div class="delivery-option d-none">
                                            <h4 class="escrow-form__title text-start">@lang('Require postal or courier services?')</h4>
                                            <div class="escrow-form__wrapper ">
                                                <div class="escrow-form__checkboxes">
                                                    <label class="custom-checkbox" for="custom-checkbox-1">
                                                        <input class="d-none delivery-checked exclude" type="radio" name="delivery_opportunity" value="1" id="custom-checkbox-1" @checked(@$escrowInfo['delivery_opportunity'] == 1) disabled>
                                                        <span class="custom-checkbox__input"></span>
                                                        <svg width="24" height="25" viewBox="0 0 24 25" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                            <path d="M19 3.5H5C3.89543 3.5 3 4.39543 3 5.5V19.5C3 20.6046 3.89543 21.5 5 21.5H19C20.1046 21.5 21 20.6046 21 19.5V5.5C21 4.39543 20.1046 3.5 19 3.5Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
                                                            <path d="M9 12.5L11 14.5L15 10.5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                            </path>
                                                        </svg>
                                                        @lang('Yes')
                                                    </label>
                                                    <label class="custom-checkbox" for="custom-checkbox-2">
                                                        <input class="d-none delivery-checked exclude" type="radio" name="delivery_opportunity" value="0" id="custom-checkbox-2" @checked(@$escrowInfo['delivery_opportunity'] == 0) disabled>
                                                        <span class="custom-checkbox__input"></span>
                                                        <svg width="24" height="25" viewBox="0 0 24 25" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                            <path d="M19 3.5H5C3.89543 3.5 3 4.39543 3 5.5V19.5C3 20.6046 3.89543 21.5 5 21.5H19C20.1046 21.5 21 20.6046 21 19.5V5.5C21 4.39543 20.1046 3.5 19 3.5Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
                                                            <path d="M15 9.5L9 15.5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
                                                            <path d="M9 9.5L15 15.5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
                                                        </svg>
                                                        @lang('No')
                                                    </label>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="d-flex escrow-btn-wrapper mt-4">
                                            @if ($currentStep > 1)
                                                <a href="{{ $currentStep === 2 ? route('user.escrow.step.one', ['step' => 1]) : route('user.escrow.step.two', ['step' => 2]) }}" class="btn btn--lg btn-outline--base w-100 me-2">
                                                    @lang('Previous')
                                                </a>
                                            @endif

                                            @if ($currentStep < 3)
                                                <button type="submit" class="btn btn--lg btn--base w-100">
                                                    @lang('Next')
                                                </button>
                                            @endif
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('style-lib')
    <link rel="stylesheet" href="{{ asset('assets/global/css/select2.min.css') }}">
@endpush

@push('script-lib')
    <script src="{{ asset('assets/global/js/select2.min.js') }}"></script>
@endpush


@push('script')
    <script>
        (function($) {
            "use strict";

            function toggleDeliveryOptions() {
                var selectedOption = $('[name="category_id"] option:selected');
                var isDelivery = selectedOption.data('is_delivery');
                var deliveryOptions = $('.delivery-option');
                var deliverychecked = $('.delivery-checked');

                if (isDelivery == 1) {
                    deliveryOptions.removeClass('d-none');
                    deliverychecked.prop('disabled', false);
                } else {
                    deliveryOptions.addClass('d-none');
                    deliverychecked.prop('disabled', true).prop('checked', false);
                }
            }

            $('[name="category_id"]').on('change', toggleDeliveryOptions);

            // Trigger on load if a value is already selected (e.g., from session)
            if ($('[name="category_id"]').val()) {
                toggleDeliveryOptions();
            }

        })(jQuery);
    </script>
@endpush
