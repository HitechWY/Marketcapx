@extends('Template::layouts.user_master')

@section('content')
    <div class="col-md-12">
        <div class="custom--card">
            <div class="card-body">
                <div class="row align-items-center mb-3">
                    <div class="col-10">
                        <h6>@lang($pageTitle)</h6>
                    </div>
                    <div class="col-2 text-end">
                        <button class="trans-serach-open-btn"><i class="las la-search"></i></button>
                    </div>
                </div>
                <div class="table-responsive--md">
                    <form class="transaction-top-form mb-4" method="GET">
                        <div class="custom-select-search-box">
                            <input type="text" name="search" class="form--control" value="{{ request()->search }}" placeholder="@lang('Search by transactions')">
                            <button type="submit" class="search-box-btn">
                                <i class="las la-search"></i>
                            </button>
                        </div>
                    </form>

                    <table class="table custom--table">
                        <thead>
                            <tr>
                                <th>@lang('Escrow Number')</th>
                                <th>@lang('Title')</th>
                                <th>@lang('Buyer - Seller')</th>
                                <th>@lang('Amount')</th>
                                <th>@lang('Charge')</th>
                                <th>@lang('Charge Payer')</th>
                                <th>@lang('Status')</th>
                                <th>@lang('Action')</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($escrows as $escrow)
                                <tr>
                                    <td>{{ $escrow->escrow_number }}</td>
                                    <td>{{ __($escrow->title) }}</td>
                                    <td>
                                        @lang('I\'m') @if ($escrow->buyer_id == auth()->user()->id)
                                            @lang('buying from')
                                            {{ __(@$escrow->seller->username ?? $escrow->invitation_mail) }}
                                        @else
                                            @lang('selling to')
                                            {{ __(@$escrow->buyer->username ?? $escrow->invitation_mail) }}
                                        @endif
                                    </td>
                                    <td>{{ showAmount($escrow->amount, currencyFormat: false) }} {{ $escrow->currency->currency_code }}</td>
                                    <td>{{ showAmount($escrow->charge, currencyFormat: false) }} {{ $escrow->currency->currency_code }}</td>
                                    <td>
                                        @if ($escrow->charge_payer == Status::CHARGE_PAYER_SELLER)
                                            <span class="badge badge--dark">@lang('Seller')</span>
                                        @elseif($escrow->charge_payer == Status::CHARGE_PAYER_BUYER)
                                            <span class="badge badge--info">@lang('Buyer')</span>
                                        @else
                                            <span class="badge badge--success">@lang('50%-50%')</span>
                                        @endif
                                    </td>
                                    <td>
                                        @php echo $escrow->escrowStatus @endphp
                                    </td>
                                    <td>
                                        <a href="{{ route('user.escrow.overview', $escrow->id) }}" class="btn btn-sm btn--base">
                                            @lang('Overview')
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr class="text-center">
                                    <td colspan="100%" class="not-found">{{ __($emptyMessage) }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($escrows->hasPages())
                    <div class="card-footer bg-transparent pt-4 pb-2">
                        {{ paginatelinks($escrows) }}
                    </div>
                @endif
            </div>
        </div><!-- custom--card end -->
    </div>
@endsection


@push('script')
    <script>
        (function($) {
            "use strict"
            $('.form-control').addClass('form--control');
        })(jQuery);
    </script>
@endpush
