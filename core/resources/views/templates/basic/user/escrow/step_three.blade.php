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
                                <form class="escrow-form" action="{{ route('user.escrow.step.three.submit') }}" method="POST">
                                    @csrf
                                    @include('Template::user.escrow.steper')
                                    <div class="escrow-form__body">
                                        <div class="escrow-form__wrapper expaned">
                                            <div class="row">
                                                <div class="col-sm-12">
                                                    <div class="form-group">
                                                        <label class="form-label">
                                                            @if ($escrowInfo['type'] == Status::SELLER)
                                                                @lang('Buyer\'s')
                                                            @else
                                                                @lang('Seller\'s')
                                                            @endif @lang('Email')
                                                        </label>
                                                        <input class="form-control form--control" name="email" type="email" value="{{ old('email') }}" placeholder="@if (@$escrowInfo['type'] == Status::SELLER) @lang('Buyer email address') @else @lang('Seller email address') @endif" required>
                                                    </div>
                                                </div>
                                                <div class="col-sm-12">
                                                    <div class="form-group">
                                                        <label class="form-label">@lang('Title')</label>
                                                        <input class="form-control form--control" type="text" name="title" value="{{ old('title') }}" placeholder="@lang('Enter title')" required>
                                                    </div>
                                                </div>

                                                <div class="col-sm-12">
                                                    <div class="form-group">
                                                        <label class="form-label">@lang('Currency')</label>
                                                        <select class="form-select form-select--lg form--select select2" name="currency" data-placeholder="@lang('Select currency')" required>
                                                            <option value=""></option>
                                                            @foreach ($currencies as $currency)
                                                                <option value="{{ $currency->id }}" {{ (old('currency') == $currency->id ? 'selected' : $currency->is_default) ? 'selected' : '' }} data-cur_code="{{ $currency->currency_code }}">
                                                                    {{ $currency->currency_code }}
                                                                </option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="col-sm-12">
                                                    <div class="form-group">
                                                        <label class="form-label">@lang('Amount')</label>
                                                        <div class="input-group">
                                                            <input class="form-control form--control" type="number" value="{{ old('amount', getAmount(@$escrowInfo['amount'])) }}" step="any" name="amount" placeholder="@lang('Enter amount')" required @if (@$escrowInfo['amount']) readonly @endif>
                                                            <span class="input-group-text curCode"></span>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="col-sm-12">
                                                    <div class="form-group">
                                                        <label class="form-label">@lang('Charge')</label>
                                                        <div class="input-group">
                                                            <input class="form-control form--control" type="number" step="any" name="charge" value="{{ old('charge') }}" placeholder="@lang('Charge will pay')" required readonly>
                                                            <span class="input-group-text curCode"></span>
                                                        </div>
                                                    </div>
                                                </div>


                                                <div class="col-sm-12">
                                                    <div class="form-group">
                                                        <label class="form-label">@lang('Charge will Pay')</label>
                                                        <select class="form-select form-select--lg form--select select2" name="charge_payer" data-placeholder="@lang('Charge payer')" required>
                                                            <option value=""></option>
                                                            @foreach ([1 => 'Seller', 2 => 'Buyer', 3 => '50% - 50%'] as $value => $label)
                                                                <option value="{{ $value }}" {{ old('charge_payer') == $value ? 'selected' : '' }}>
                                                                    @lang($label)
                                                                </option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="col-sm-12">
                                                    <div class="form-group mb-0">
                                                        <label class="form-label">@lang('Details')</label>
                                                        <textarea class="form-control form--control" name="details" placeholder="@lang('Enter Details')" required>{{ old('details') }}</textarea>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="escrow-form-price mt-3">
                                                <span class="escrow-form-price__label">@lang('Total'):</span>
                                                <h3 class="escrow-form-price__value">
                                                    <span class="totalAmount"></span>
                                                    <span class="curCode"></span>
                                                </h3>
                                            </div>
                                            <div class="d-flex escrow-btn-wrapper mt-4">
                                                @if ($currentStep > 1)
                                                    <a href="{{ $currentStep === 2 ? route('user.escrow.step.one', ['step' => 1]) : route('user.escrow.step.two', ['step' => 2]) }}" class="btn btn--lg btn-outline--base w-100 me-2">
                                                        @lang('Previous')
                                                    </a>
                                                @endif
                                                @if ($currentStep <= 3)
                                                    <button type="submit" class="btn btn--lg btn--base w-100">
                                                        @lang('Create Escrow')
                                                    </button>
                                                @endif
                                            </div>
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
            let amountInput = $('input[name="amount"]');
            let chargeInput = $('input[name="charge"]');
            let chargePayerSelect = $('select[name="charge_payer"]');
            let totalAmountDisplay = $('.totalAmount');
            let escrowType = "{{ $escrowInfo['type'] }}";

            function updateTotalAmount() {
                let amount = parseFloat(amountInput.val()) || 0;
                let charge = parseFloat(chargeInput.val()) || 0;
                let chargePayer = chargePayerSelect.val();
                let total;

                if (escrowType == {{ Status::SELLER }}) { // Seller
                    if (chargePayer == 2) {
                        total = amount;
                    } else if (chargePayer == 1) {
                        total = amount + charge;
                    } else if (chargePayer == 3) {
                        total = amount + charge / 2;
                    }
                } else { // Buyer
                    if (chargePayer == 1) {
                        total = amount; // Seller bears the charge
                    } else if (chargePayer == 2) {
                        total = amount + charge; // Buyer bears the charge
                    } else if (chargePayer == 3) {
                        total = amount + charge / 2; // 50-50 split
                    }
                }

                if (!isNaN(total)) {
                    totalAmountDisplay.text(total.toFixed(2));
                } else {
                    totalAmountDisplay.text('0.00');
                }
            }

            function calculateCharge() {
                let amount = amountInput.val().trim();
                let currency = $('[name=currency]').find(':selected').data('cur_code');
                if (amount > 0) {
                    $.get("{{ route('user.escrow.show.charge') }}", {
                            amount: amount,
                            currency: currency
                        })
                        .done(function(response) {
                            chargeInput.val(response.charge ?? '');
                            updateTotalAmount();
                        })
                        .fail(function() {
                            chargeInput.val('');
                            updateTotalAmount();
                        });
                } else {
                    chargeInput.val('');
                    updateTotalAmount();
                }
            }

            function restoreOldValues() {
                let oldAmount = "{{ old('amount') }}";
                let oldCharge = "{{ old('charge') }}";
                let oldChargePayer = "{{ old('charge_payer') }}";

                if (oldAmount) amountInput.val(oldAmount);
                if (oldCharge) chargeInput.val(oldCharge);
                if (oldChargePayer) chargePayerSelect.val(oldChargePayer);
                calculateCharge();
            }

            $(document).ready(function() {
                restoreOldValues();
                if (amountInput.val().trim() > 0) {
                    calculateCharge();
                }
            });

            const debouncedCalculateCharge = debounce(calculateCharge, 500);
            const debouncedUpdateTotalAmount = debounce(updateTotalAmount, 500);

            amountInput.on('input', debouncedCalculateCharge);
            chargeInput.on('input', debouncedUpdateTotalAmount);

            chargePayerSelect.on('change', function() {
                debouncedCalculateCharge();
                debouncedUpdateTotalAmount();
            });


            function debounce(func, delay) {
                let timeout;
                return function(...args) {
                    clearTimeout(timeout);
                    timeout = setTimeout(() => func.apply(this, args), delay);
                };
            }

            $('[name=currency]').on('change', function() {
                let code = $(this).find('option:selected').data('cur_code');
                $('.curCode').text(code);
                calculateCharge();
            }).change();

        })(jQuery);
    </script>
@endpush
