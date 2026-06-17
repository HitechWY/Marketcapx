@extends('Template::layouts.user_master')

@section('content')
    <div class="col-xl-12">
        <div class="custom--card">
            <div class="card-body">
                <div class="row align-items-center mb-3">
                    <div class="col-md-6">
                        <h6>@lang($pageTitle)</h6>
                    </div>
                    @if ($escrow->status == Status::ESCROW_ACCEPTED && $escrow->restAmount() && $escrow->buyer_id == auth()->id())
                        <div class="col-md-6 text-end">
                            <button class="btn btn--base btn-sm" data-bs-toggle="modal" data-bs-target="#newModal">@lang('Create Milestone')</button>
                        </div>
                    @endif
                </div>

                <table class="table custom--table">
                    <thead>
                        <tr>
                            <th>@lang('Date')</th>
                            <th>@lang('Note')</th>
                            <th>@lang('Amount')</th>
                            <th>@lang('Payment Status')</th>

                            @if ($escrow->buyer_id == auth()->id() && $escrow->restAmount())
                                <th>@lang('Action')</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($milestones as $milestone)
                            <tr>
                                <td>{{ showDateTime($milestone->created_at, 'Y-m-d') }}</td>

                                <td>{{ $milestone->note }}</td>

                                <td>{{ showAmount($milestone->amount, currencyFormat: false) . ' ' . $escrow->currency->currency_code }}
                                </td>

                                <td>
                                    @if ($milestone->payment_status == Status::MILESTONE_FUNDED)
                                        <span class="badge badge--success">@lang('Funded')</span>
                                    @else
                                        <span class="badge badge--danger">@lang('Unfunded')</span>
                                    @endif
                                </td>

                                @if ($escrow->buyer_id == auth()->id() && $escrow->restAmount())
                                    <td>
                                        <button class="btn btn--primary btn-sm payBtn" @disabled($milestone->payment_status == Status::MILESTONE_FUNDED) data-id="{{ $milestone->id }}">
                                            @lang('Pay Now')
                                        </button>
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td colspan="100%" class="text-center">{{ __($emptyMessage) }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($milestones->hasPages())
                <div class="col-md-12">
                    {{ $milestones->links() }}
                </div>
            @endif
        </div>
    </div>


    <div class="modal custom--modal fade " id="newModal">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">@lang('New Milestone')</h5>
                    <button role="button" class="close"><i class="las la-times" data-bs-dismiss="modal"></i></button>
                </div>
                <form action="{{ route('user.escrow.milestone.create', $escrow->id) }}" method="POST">
                    @csrf
                    <div class="modal-body">
                        <div class="form-group">
                            <label class="form-label">@lang('Note')</label>
                            <input type="text" name="note" placeholder="@lang('Enter note')" class="form-control form--control" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">@lang('Rest Amount')</label>
                            <input type="text" class="form-control form--control" value="{{ $restAmount }}" readonly>
                        </div>

                        <div class="form-group">
                            <label class="form-label">@lang('Amount')</label>
                            <div class="input-group">
                                <input type="number" step="any" class="form-control form--control" name="amount" required>
                                <span class="input-group-text ">{{ __($escrow->currency->currency_code) }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn--base w-100 h-45">@lang('Submit')</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade " id="payModal">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">@lang('Pay Milestone')</h5>
                    <button role="button" class="close"><i class="las la-times" data-bs-dismiss="modal"></i></button>
                </div>

                <form action="" method="POST">
                    @csrf
                    <div class="modal-body">
                        <p>@lang('Are you sure pay milestone?')</p>
                        <p>@lang('Avaiable Balance'): <strong>{{ showAmount($wallet->balance, currencyFormat: false) }}
                                {{ __($wallet->currency->currency_code) }}</strong></p>
                        @if (gs('otp_verification') && (gs('en') || gs('sn') || auth()->user()->ts))
                            <div class="form-group mt-2">
                                @include('Template::partials.otp_select')
                            </div>
                        @endif

                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn--base h-45 w-100">@lang('Submit')</button>
                    </div>
                </form>
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
            "use strict"

            $('.payBtn').on('click', function() {
                var modal = $('#payModal');
                let route = "{{ route('user.escrow.milestone.pay', ':id') }}";
                modal.find('form')[0].action = route.replace(':id', $(this).data('id'));
                modal.modal('show');
            });

        })(jQuery);
    </script>
@endpush

@push('style')
    <style>
        span.select2.select2-container {
            width: 100% !important;
        }
    </style>
@endpush
