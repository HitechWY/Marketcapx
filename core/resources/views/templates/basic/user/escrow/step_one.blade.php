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
                                <form class="escrow-form" action="{{ route('user.escrow.step.one.submit') }}" method="POST">
                                    @csrf
                                    @include('Template::user.escrow.steper')
                                    <div class="escrow-form__body">
                                        <h4 class="escrow-form__title">@lang('I\'m a')</h4>
                                        <div class="escrow-form__wrapper">
                                            <div class="escrow-form__checkboxes">
                                                <label class="custom-checkbox" for="custom-checkbox-1">
                                                    <input class="d-none exclude" type="radio" name="type" value="2" @checked(@$escrowInfo['type'] == 2) id="custom-checkbox-1" checked>
                                                    <span class="custom-checkbox__input"></span>
                                                    <svg width="24" height="25" viewBox="0 0 24 25" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                        <path d="M8 22.5C8.55228 22.5 9 22.0523 9 21.5C9 20.9477 8.55228 20.5 8 20.5C7.44772 20.5 7 20.9477 7 21.5C7 22.0523 7.44772 22.5 8 22.5Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                                        <path d="M19 22.5C19.5523 22.5 20 22.0523 20 21.5C20 20.9477 19.5523 20.5 19 20.5C18.4477 20.5 18 20.9477 18 21.5C18 22.0523 18.4477 22.5 19 22.5Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                                        <path d="M2.0498 2.55005H4.0498L6.7098 14.97C6.80738 15.4249 7.06048 15.8315 7.42552 16.1199C7.79056 16.4083 8.24471 16.5604 8.7098 16.55H18.4898C18.945 16.5493 19.3863 16.3933 19.7408 16.1079C20.0954 15.8224 20.3419 15.4246 20.4398 14.98L22.0898 7.55005H5.1198" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                                    </svg>
                                                    @lang('Buyer')
                                                </label>
                                                <label class="custom-checkbox" for="custom-checkbox-2">
                                                    <input class="d-none exclude" type="radio" name="type" value="1" @checked(@$escrowInfo['type'] == 1) id="custom-checkbox-2">
                                                    <span class="custom-checkbox__input"></span>
                                                    <svg width="24" height="25" viewBox="0 0 24 25" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                        <path d="M2 7.5L6.41 3.09C6.59606 2.90283 6.81732 2.75434 7.06103 2.65308C7.30474 2.55182 7.56609 2.49979 7.83 2.5H16.17C16.4339 2.49979 16.6953 2.55182 16.939 2.65308C17.1827 2.75434 17.4039 2.90283 17.59 3.09L22 7.5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                                        <path d="M4 12.5V20.5C4 21.0304 4.21071 21.5391 4.58579 21.9142C4.96086 22.2893 5.46957 22.5 6 22.5H18C18.5304 22.5 19.0391 22.2893 19.4142 21.9142C19.7893 21.5391 20 21.0304 20 20.5V12.5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                                        <path d="M15 22.5V18.5C15 17.9696 14.7893 17.4609 14.4142 17.0858C14.0391 16.7107 13.5304 16.5 13 16.5H11C10.4696 16.5 9.96086 16.7107 9.58579 17.0858C9.21071 17.4609 9 17.9696 9 18.5V22.5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                                        <path d="M2 7.5H22" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                                        <path
                                                            d="M22 7.5V10.5C22 11.0304 21.7893 11.5391 21.4142 11.9142C21.0391 12.2893 20.5304 12.5 20 12.5C19.4157 12.4678 18.8577 12.2467 18.41 11.87C18.2907 11.7838 18.1472 11.7374 18 11.7374C17.8528 11.7374 17.7093 11.7838 17.59 11.87C17.1423 12.2467 16.5843 12.4678 16 12.5C15.4157 12.4678 14.8577 12.2467 14.41 11.87C14.2907 11.7838 14.1472 11.7374 14 11.7374C13.8528 11.7374 13.7093 11.7838 13.59 11.87C13.1423 12.2467 12.5843 12.4678 12 12.5C11.4157 12.4678 10.8577 12.2467 10.41 11.87C10.2907 11.7838 10.1472 11.7374 10 11.7374C9.85279 11.7374 9.70932 11.7838 9.59 11.87C9.14227 12.2467 8.58426 12.4678 8 12.5C7.41574 12.4678 6.85773 12.2467 6.41 11.87C6.29068 11.7838 6.14721 11.7374 6 11.7374C5.85279 11.7374 5.70932 11.7838 5.59 11.87C5.14227 12.2467 4.58426 12.4678 4 12.5C3.46957 12.5 2.96086 12.2893 2.58579 11.9142C2.21071 11.5391 2 11.0304 2 10.5V7.5"
                                                            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                                    </svg>
                                                    @lang('Seller')
                                                </label>
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
