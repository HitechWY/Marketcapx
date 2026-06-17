@extends('admin.layouts.app')

@section('panel')
    <div class="row">
        <div class="col-lg-12">
            <div class="card b-radius--10 ">
                <div class="card-body p-0">
                    <div class="table-responsive--md  table-responsive">
                        <table class="table table--light style--two">
                            <thead>
                                <tr>
                                    <th>@lang('Date')</th>
                                    <th>@lang('Note')</th>
                                    <th>@lang('Amount')</th>
                                    <th>@lang('Payment Status')</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($milestones as $milestone)
                                    <tr>
                                        <td>
                                            {{ showDateTime($milestone->created_at, 'Y-m-d') }}
                                        </td>
                                        <td>{{ __($milestone->note) }}</td>
                                        <td>
                                            {{ showAmount($milestone->amount, currencyFormat: false) }} {{ __($escrow->currency->currency_code) }}
                                        </td>
                                        <td>
                                            @if ($milestone->payment_status == Status::MILESTONE_FUNDED)
                                                <span class="badge badge--success">@lang('Funded')</span>
                                            @else
                                                <span class="badge badge--danger">@lang('Unfunded')</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="100%" class="text-center">{{ __($emptyMessage) }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table><!-- table end -->
                    </div>
                </div>
                @if ($milestones->hasPages())
                    <div class="card-footer py-4">
                        {{ paginateLinks($milestones) }}
                    </div>
                @endif
            </div><!-- card end -->
        </div>
    </div>
@endsection
