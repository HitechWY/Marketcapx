@extends('admin.layouts.app')
@section('panel')
    <div class="row">
        <div class="col-lg-12 ">
            <div class="card">
                <div class="card-body p-0">
                    <div class="table-responsive--sm table-responsive">
                        <table class="table table--light style--two">
                            <thead>
                                <tr>
                                    <th>@lang('Title | Escrow Number')</th>
                                    <th>@lang('Buyer')</th>
                                    <th>@lang('Seller')</th>
                                    <th>@lang('Amount | Charge')</th>
                                    <th>@lang('Type')</th>
                                    <th>@lang('Charge Payer')</th>
                                    <th>@lang('Status')</th>
                                    <th>@lang('Action')</th>
                                </tr>
                            </thead>
                            <tbody class="list">
                                @forelse ($escrows as $escrow)
                                    <tr>
                                        <td>
                                            <div>
                                                {{ __($escrow->title) }} <br />
                                                {{ $escrow->escrow_number }}
                                            </div>
                                        </td>
                                        <td>
                                            @if ($escrow->buyer)
                                                <span class="fw-bold d-block">{{ __($escrow->buyer->fullname) }}</span>

                                                <span class="small">
                                                    <a href="{{ route('admin.users.detail', $escrow->buyer->id) }}">
                                                        <span>@</span>{{ __($escrow->buyer->username) }}
                                                    </a>
                                                </span>
                                            @else
                                                {{ $escrow->invitation_mail }}
                                            @endif
                                        </td>
                                        <td>
                                            @if ($escrow->seller)
                                                <span class="fw-bold d-block">{{ __($escrow->seller->fullname) }}</span>
                                                <span class="small">
                                                    <a href="{{ route('admin.users.detail', $escrow->seller->id) }}">
                                                        <span>@</span>{{ __($escrow->seller->username) }}
                                                    </a>
                                                </span>
                                            @else
                                                {{ $escrow->invitation_mail }}
                                            @endif
                                        </td>
                                        <td>
                                            <div>
                                                {{ showAmount($escrow->amount, currencyFormat: false) }} {{ __($escrow->currency->currency_code) }} <br />
                                                {{ showAmount($escrow->charge, currencyFormat: false) }} {{ __($escrow->currency->currency_code) }}
                                            </div>
                                        </td>
                                        <td>
                                            {{ $escrow->category->name }}
                                        </td>
                                        <td>
                                            @if ($escrow->charge_payer == Status::CHARGE_PAYER_SELLER)
                                                <span class="badge badge--primary">@lang('Seller')</span>
                                            @elseif($escrow->charge_payer == Status::CHARGE_PAYER_BUYER)
                                                <span class="badge badge--dark">@lang('Buyer')</span>
                                            @else
                                                <span class="badge badge--success">@lang('50%-50%')</span>
                                            @endif
                                        </td>
                                        <td>
                                            @php echo $escrow->escrowStatus @endphp
                                        </td>
                                        <td>
                                            <a href="{{ route('admin.escrow.details', $escrow->id) }}" class="btn btn-sm btn-outline--primary">
                                                <i class="las la-desktop"></i> @lang('Details')
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td class="text-muted text-center" colspan="100%">{{ __($emptyMessage) }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                @if ($escrows->hasPages())
                    <div class="card-footer py-4">
                        {{ paginateLinks($escrows) }}
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection

@push('breadcrumb-plugins')
    <x-search-form placeholder="Title / Category name" />
@endpush
