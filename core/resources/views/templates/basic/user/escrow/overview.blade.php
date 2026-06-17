@extends('Template::layouts.user_master')

@section('content')

    @php
        $joinedParties = $escrow->status == Status::ESCROW_ACCEPTED || $escrow->status == Status::ESCROW_COMPLETED || $escrow->paid_amount > 0;
        $holdPayment = $escrow->paid_amount > 0 || $escrow->status == Status::ESCROW_COMPLETED;
        $user = auth()->user();
        $hasSellerAndBuyer = $escrow->seller_id && $escrow->buyer_id;
    @endphp

    <div class="col-xl-12">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <h6>{{ __($pageTitle) }}</h6>
            <div class="d-flex align-items-center flex-wrap gap-3">
                @if ($escrow->status == Status::ESCROW_ACCEPTED || $escrow->status == Status::ESCROW_NOT_ACCEPTED)
                    @if ($escrow->status == Status::ESCROW_NOT_ACCEPTED)
                        <button class="flex-grow-1 btn btn-sm btn--danger confirmationBtn" data-question="@lang('Are you sure to cancel this escrow?')" data-action="{{ route('user.escrow.cancel', $escrow->id) }}">
                            <span>@lang('Cancel')</span>
                            <span class="btn--icon"><i class="la la-times"></i> </span>
                        </button>

                        @if ($escrow->creator_id != auth()->id() && $hasSellerAndBuyer)
                            <button class="flex-grow-1 btn btn-sm btn--success confirmationBtn" data-question="@lang('Are you sure to accept this escrow?')" data-action="{{ route('user.escrow.accept', $escrow->id) }}">
                                <span>@lang('Accept')</span>
                                <span class="btn--icon"><i class="la la-check"></i> </span>
                            </button>
                        @endif
                    @else
                        {{-- payment dispute button --}}
                        @if ($hasSellerAndBuyer)
                            <button class="flex-grow-1 btn btn-sm btn--danger user-action">
                                <span> @lang('Dispute Escrow')</span>
                                <span class="btn--icon">
                                    <i class="las la-exclamation-triangle"></i>
                                </span>
                            </button>
                        @endif
                        {{-- If all amount is paid and the escrow is accepted --}}
                        @if ($escrow->restAmount() <= 0 && $escrow->buyer_id == auth()->user()->id && $hasSellerAndBuyer)
                            <button class="flex-grow-1 btn btn-sm btn--primary wow confirmationBtn" data-question="@lang('Are you sure to dispatch this escrow?')" data-action="{{ route('user.escrow.dispatch', $escrow->id) }}">
                                <span>@lang('Dispatch Payment')</span>
                                <span class="btn--icon"><i class="la la-money-check-alt"></i> </span>
                            </button>
                        @endif
                    @endif
                @endif


                @if ($escrow->status != Status::ESCROW_NOT_ACCEPTED)
                    <a class="flex-grow-1 btn btn-sm btn--base" href="{{ route('user.escrow.milestone.index', $escrow->id) }}">
                        @lang('See Milestones')
                        <span class="btn--icon">
                            <i class="las la-arrow-right"></i>
                        </span>
                    </a>
                @endif

                <button class="flex-grow-1 btn btn-sm btn--primary @if ($unreadCount) readMsg @endif" data-bs-toggle="offcanvas" data-bs-target="#offcanvasRight" aria-controls="offcanvasRight">
                    <span>
                        @lang('Conversations')
                        @if ($unreadCount > 0)
                            <span class="badge badge--notification unreadCounter ms-1" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-offset="0,15" title="@lang('Unread messages')">
                                {{ $unreadCount > 99 ? '99+' : $unreadCount }}
                            </span>
                        @endif
                    </span>
                    <span class="btn--icon">
                        <i class="lab la-rocketchat"></i>
                    </span>
                </button>
            </div>
        </div>
        <div class="card custom--card">
            <div class="card-body">
                <div class="trx-status">
                    <ul class="trx-status-step-list">
                        <li class="trx-status-step-list__item   @if ($joinedParties) active @endif">
                            <div class="trx-status-step-list__wrapper">
                                <span class="trx-status-step-list__step">
                                    <i class="fas fa-lock"></i>
                                </span>
                                <span class="trx-status-step-list__text">@lang('Both Parties Joined')</span>
                            </div>
                        </li>

                        <li class="trx-status-step-list__item @if ($holdPayment) active @endif">
                            <div class="trx-status-step-list__wrapper">
                                <span class="trx-status-step-list__step">
                                    <i class="fas fa-lightbulb"></i>
                                </span>
                                <span class="trx-status-step-list__text"> @lang('Payment Hold in')
                                    {{ __(gs('site_name')) }}</span>
                            </div>
                        </li>

                        <li class="trx-status-step-list__item @if ($escrow->status == Status::ESCROW_COMPLETED || $escrow->status == Status::ESCROW_CANCELLED) active @endif">
                            <div class="trx-status-step-list__wrapper">
                                <span class="trx-status-step-list__step">
                                    <i class="fas fa-paper-plane"></i>
                                </span>
                                <span class="trx-status-step-list__text">
                                    @if ($escrow->status == Status::ESCROW_CANCELLED)
                                        @lang('Fund Canceled')
                                    @else
                                        @lang('Fund Released')
                                    @endif
                                </span>
                            </div>
                        </li>
                    </ul>

                    <div class="trx-status-heading">
                        <h4 class="trx-status-heading__title">@lang('Transaction has been created.') </h4>
                        <p class="trx-status-heading__subtitle">{{ __($notify) }}</p>
                    </div>
                    <div class="input-group input-group--copy">
                        <input class="form-control form--control" type="text" value="{{ route('user.escrow.overview', $escrow->id) }}" readonly>
                        <button class="btn btn--base copy-button" type="button">
                            <i class="las la-copy"></i>
                            <span>@lang('Copy')</span>
                        </button>
                    </div>
                    <button class="trx-status-info-btn" data-bs-toggle="collapse" data-bs-target="#collapseOne" aria-expanded="true" aria-controls="collapseOne">
                        <span class="text">@lang('Escrow Breakdown')</span>
                    </button>
                    <div class="collapse show" id="collapseOne">
                        <div class="row gy-4 mt-4 replace-wrapper">
                            <div class="col-md-6">
                                <div class="trx-status-info">
                                    <span class="label">
                                        @if ($escrow->buyer_id == auth()->id())
                                            @lang('Seller')
                                        @else
                                            @lang('Buyer')
                                        @endif
                                    </span>
                                    <span class="value">
                                        @if ($escrow->buyer_id == auth()->id())
                                            <span>{{ __(@$escrow->seller->username ? $escrow->seller->fullname : $escrow->invitation_mail) }}</span>
                                        @else
                                            {{ __(@$escrow->buyer->username ? $escrow->buyer->fullname : $escrow->invitation_mail) }}
                                        @endif
                                    </span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="trx-status-info">
                                    <span class="label">
                                        @if ($escrow->buyer_id == auth()->id())
                                            @lang('Seller Email')
                                        @else
                                            @lang('Buyer Email')
                                        @endif
                                    </span>
                                    <span class="value clt-email">
                                        @if ($escrow->buyer_id == auth()->id())
                                            <span>{{ __(@$escrow->seller->email ?? $escrow->invitation_mail) }}</span>
                                        @else
                                            {{ __(@$escrow->buyer->email ?? $escrow->invitation_mail) }}
                                        @endif
                                    </span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="trx-status-info">
                                    <span class="label">@lang('Category')</span>
                                    <span class="value">
                                        {{ $escrow->category->name }}
                                    </span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="trx-status-info">
                                    <span class="label">
                                        @lang('Postal or Courier')
                                    </span>
                                    <span class="value">
                                        @if ($escrow->category->is_delivery)
                                            @lang('Yes')
                                        @else
                                            @lang('No')
                                        @endif
                                    </span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="trx-status-info">
                                    <span class="label">@lang('Title')</span>
                                    <span class="value">{{ __($escrow->title) }}</span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="trx-status-info">
                                    <span class="label">
                                        @lang('Escrow Number')
                                    </span>
                                    <span class="value">
                                        {{ $escrow->escrow_number }}
                                    </span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="trx-status-info">
                                    <span class="label">@lang('Amount')</span>
                                    <span class="value display-amount">{{ showAmount($escrow->amount, currencyFormat: false) }}
                                        {{ $escrow->currency->currency_code }}</span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="trx-status-info">
                                    <span class="label">
                                        @lang('Charge Payer')
                                    </span>
                                    <span class="value">
                                        @if ($escrow->charge_payer == Status::CHARGE_PAYER_SELLER)
                                            @lang('Seller')
                                        @elseif($escrow->charge_payer == Status::CHARGE_PAYER_BUYER)
                                            @lang('Buyer')
                                        @else
                                            @lang('Sharing') <span class="fw-bold">@lang('50%-50%')</span>
                                        @endif
                                    </span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="trx-status-info">
                                    <span class="label">@lang('Charge')</span>
                                    <span class="value display-charge">{{ showAmount($escrow->charge, currencyFormat: false) }}
                                        {{ $escrow->currency->currency_code }}</span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="trx-status-info">
                                    <span class="label">
                                        @lang('Created Milestone')
                                    </span>
                                    <span class="value">
                                        {{ showAmount($escrow->milestones->sum('amount'), currencyFormat: false) }}
                                        {{ $escrow->currency->currency_code }}
                                    </span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="trx-status-info">
                                    <span class="label">@lang('Total/Rest Amount')</span>
                                    <span class="value"> {{ showAmount($escrow->restAmount(), currencyFormat: false) }}
                                        {{ $escrow->currency->currency_code }}</span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="trx-status-info">
                                    <span class="label">
                                        @lang('Milestone Funded')
                                    </span>
                                    <span class="value">
                                        {{ showAmount($escrow->milestones->where('payment_status', Status::MILESTONE_FUNDED)->sum('amount'), currencyFormat: false) }}
                                        {{ $escrow->currency->currency_code }}
                                    </span>
                                </div>
                            </div>
                            <div class="col-md-6 mt-3">
                                <div class="trx-status-info">
                                    <span class="label">
                                        @lang('Status')
                                    </span>
                                    <span class="value"> @php echo $escrow->escrowStatus @endphp </span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="trx-status-info">
                                    <span class="label">@lang('Milestone Unfunded')</span>
                                    <span class="value">{{ showAmount($escrow->milestones->where('payment_status', Status::MILESTONE_UNFUNDED)->sum('amount'), currencyFormat: false) }}
                                        {{ $escrow->currency->currency_code }}</span>
                                </div>
                            </div>

                            @if ($escrow->status == Status::ESCROW_DISPUTED)
                                <div class="col-md-6">
                                    <div class="trx-status-info">
                                        <span class="label">@lang('Dispute Reason')</span>
                                        <span class="value">{{ $escrow->dispute_note }}</span>
                                    </div>
                                </div>
                            @endif

                            <div class="col-md-6">
                                @if ($isDelivery)
                                    <div class="trx-status-info">
                                        <span class="label">@lang('Post/Courier for this transaction')</span>
                                        <span class="value delivery-status">
                                            @if ($escrow->delivery_opportunity)
                                                @lang('Yes')
                                            @else
                                                @lang('No')
                                            @endif
                                        </span>
                                    </div>
                                @endif
                                @if ($escrow->status == Status::ESCROW_DISPUTED)
                                    <div class="trx-status-info">
                                        <span class="label">@lang('Disputed By')</span>
                                        <span class="value"> {{ $escrow->disputer->username }}</span>
                                    </div>
                                @endif
                            </div>
                            <div class="col-12 text-center">
                                @if ($escrow->status == Status::ESCROW_NOT_ACCEPTED && auth()->id() == $escrow->creator_id)
                                    <button class="w-100 btn btn--base trx-status-info__edit-btn" type="button">
                                        <i class="far fa-pen-to-square"></i> @lang('Edit')
                                    </button>
                                @endif
                            </div>
                        </div>

                        <!-- Hidden Form -->
                        <div class="row gy-3 mt-4 edit-form d-none">
                            <div class="col-md-6">
                                <label class="form-label">
                                    @if (auth()->id() == $escrow->seller_id)
                                        @lang('Buyer email')
                                    @else
                                        @lang('Seller email')
                                    @endif
                                </label>
                                <input type="email" class="form-control form--control" value="{{ $escrow->buyer_id == auth()->id() ? @$escrow->seller->email ?? $escrow->invitation_mail : @$escrow->buyer->email ?? $escrow->invitation_mail }}" name="email" required>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">@lang('Total price')</label>
                                <div class="input-group">
                                    <input type="text" class="form-control form--control" name="amount" value="{{ old('amount', getAmount(@$escrow->amount)) }}" required>
                                    <span class="input-group-text">{{ __(gs('cur_text')) }}</span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                @if ($isDelivery)
                                    <div class="form-group">
                                        <label class="form-label">@lang('Post/Courier for this transaction')</label>
                                        <select name="delivery_opportunity" class="form-control form--control select2" data-minimum-results-for-search="-1">
                                            <option value="{{ Status::YES }}" @if ($escrow->delivery_opportunity) selected @endif>
                                                @lang('Yes')</option>
                                            <option value="{{ Status::NO }}" @if (!$escrow->delivery_opportunity) selected @endif>
                                                @lang('No')</option>
                                        </select>
                                    </div>
                                @endif
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">@lang('Notify To') @if (auth()->id() == $escrow->seller_id)
                                        @lang('Buyer')
                                    @else
                                        @lang('Seller')
                                    @endif
                                </label>
                                <select name="to_notify" class="form-control form--control select2" data-minimum-results-for-search="-1">
                                    <option value="{{ Status::NO }}">
                                        @lang('No')</option>
                                    <option value="{{ Status::YES }}">
                                        @lang('Yes')</option>
                                </select>
                            </div>

                            @if (auth()->id() == $escrow->creator_id)
                                <div class="col-12 text-center">
                                    <button class="w-100 btn btn--base trx-status-info__save-btn" type="button">
                                        <i class="far fa-save"></i> @lang('Save')
                                    </button>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- chat/conversation canvas --}}
    <div class="offcanvas offcanvas-end" tabindex="-1" id="offcanvasRight" aria-labelledby="offcanvasRightLabel">
        <div class="offcanvas-header">
            <h5 id="offcanvasRightLabel">@lang('Conversation with') <span class="text--base">
                    @if ($escrow->buyer_id == auth()->id())
                        <span>{{ __(@$escrow->seller->username ? $escrow->seller->fullname : $escrow->invitation_mail) }}</span>
                    @else
                        {{ __(@$escrow->buyer->username ? $escrow->buyer->fullname : $escrow->invitation_mail) }}
                    @endif
                </span></h5>
            <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body">
            <div class="card custom--card">
                <div class="card-header d-flex flex-wrap align-items-center justify-content-between">
                    <h6 class="offcanvas-title">
                        @if ($escrow->buyer_id == auth()->user()->id)
                            @lang('Buying') {{ __($escrow->category->name) }}
                        @else
                            @lang('Selling') {{ __($escrow->category->name) }}
                        @endif
                    </h6>
                    <button type="button" class="btn btn-sm btn--base reloadButton"><i class="las la-redo-alt"></i></button>
                </div>
                <div class="card-body">
                    <div class="messaging msg_history">
                        <div class="inbox_msg">
                            <ul class="list msg-list d-flex flex-column">
                                @if ($messages->count() > 0)
                                    @foreach ($messages as $message)
                                        @php
                                            $classText = $message->sender_id == auth()->user()->id ? 'send' : 'receive';
                                        @endphp
                                        <div class="msg-list__item">
                                            <div class="msg-{{ $classText }}">
                                                @if ($escrow->status == Status::ESCROW_DISPUTED && $message->sender_id != auth()->id())
                                                    <p class="mb-0">
                                                        @if ($message->admin)
                                                            <span class="fw-bold">
                                                                @lang('System')
                                                            </span>
                                                        @else
                                                            {{ @$message->sender->username }}
                                                        @endif
                                                    </p>
                                                @endif
                                                <div class="msg-{{ $classText }}__content">
                                                    <p class="msg-{{ $classText }}__text mb-0">
                                                        {{ __($message->message) }}
                                                    </p>
                                                </div>
                                                <ul class="list msg-{{ $classText }}__history @if ($classText == 'send') justify-content-end @endif">
                                                    <div class="msg-receive__history-item">
                                                        {{ $message->created_at->format('h:i A') }}
                                                    </div>
                                                    <div class="msg-receive__history-item">
                                                        {{ $message->created_at->diffForHumans() }}
                                                    </div>
                                                </ul>
                                            </div>
                                            </li>
                                    @endforeach
                                @else
                                    <div class="empty-message text-center">
                                        <div class="empty-message__icon">
                                            <i class="la la-comment-slash"></i>
                                        </div>
                                        <div class="empty-message__heading">
                                            @lang('No conversation yet')
                                        </div>
                                    </div>
                                @endif
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            @if ($escrow->status != Status::ESCROW_CANCELLED && $escrow->status != Status::ESCROW_COMPLETED && $hasSellerAndBuyer)
                <div class="msg-option">
                    <form class="message-form">
                        <div class="msg-option__content rounded-pill">
                            <div class="msg-option__group ">
                                <input type="text" class="form-control msg-option__input" name="message" autocomplete="off" placeholder="@lang('Send Message')">
                                <button type="submit" class="btn btn--base msg-option__button reloadButton rounded-pill">
                                    <i class="lab la-telegram-plane"></i>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            @endif
        </div>
    </div>


    {{-- Dispute Escrow modal --}}
    <div class="modal fade " id="actionModal">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">@lang('Dispute Escrow')</h5>
                    <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                        <i class="las la-times"></i>
                    </button>
                </div>
                <form action="{{ route('user.escrow.dispute', $escrow->id) }}" method="post">
                    @csrf
                    <div class="modal-body">
                        <div class="form-group">
                            <label class="form-label form-label">@lang('Dispute Reason')</label>
                            <textarea class="form-control form--control" name="dispute_reason" rows="3" placeholder="@lang('Enter the reason')" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn--base h-45 w-100">@lang('Submit')</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <x-user-confirmation-modal />

@endsection

@push('style-lib')
    <link rel="stylesheet" href="{{ asset(activeTemplate(true) . 'css/chat.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/global/css/select2.min.css') }}">
@endpush

@push('script-lib')
    <script src="{{ asset('assets/global/js/select2.min.js') }}"></script>
@endpush

@push('script')
    <script>
        (function($) {
            "use strict";

            $(".copy-button").on("click", function() {
                var copyText = $(this).prev("input");
                if (copyText.length && copyText.is("input, textarea")) {
                    copyText.select();
                    copyText[0].setSelectionRange(0, 99999);
                }

                navigator.clipboard.writeText(copyText.val())
                    .then(() => {
                        let button = $(".copy-button");
                        button.html('<i class="las la-check"></i> <span>@lang('Copied')</span>');

                        // Revert button text after 2 seconds
                        setTimeout(() => {
                            button.html(
                                '<i class="las la-copy"></i> <span>@lang('Copy') </span>');
                        }, 2000);
                    })
                    .catch(error => {
                        console.error("Failed to copy text: ", error);
                    });
            });

            let editBtn = $(".trx-status-info__edit-btn");
            let saveBtn = $(".trx-status-info__save-btn");
            let displayWrapper = $(".replace-wrapper");
            let editForm = $(".edit-form");

            editBtn.on("click", function() {
                displayWrapper.toggleClass("d-none");
                editForm.toggleClass("d-none");
            });

            saveBtn.on("click", function() {

                let formData = {
                    escrow_id: "{{ $escrow->id }}",
                    email: $("input[name='email']").val(),
                    amount: parseFloat($("input[name='amount']").val()) || 0,
                    delivery_opportunity: $("select[name='delivery_opportunity']").val(),
                    to_notify: $("select[name='to_notify']").val(),
                };

                $.ajax({
                    headers: {
                        "X-CSRF-TOKEN": "{{ csrf_token() }}",
                    },
                    url: "{{ route('user.escrow.item.update') }}",
                    type: "POST",
                    data: formData,
                    dataType: "json",
                    success: function(response) {
                        if (response.status === "success") {
                            notify('success', response.message);
                            $(".clt-email").text(formData.email);
                            $(".delivery-status").text(formData.delivery_opportunity == 1 ? "Yes" :
                                "No");
                            $(".display-amount").text(formData.amount);
                            $(".display-charge").text(response.charge);

                            $("#displayWrapper").toggleClass("d-none");
                            $("#editForm").toggleClass("d-none");
                        } else {
                            $.each(response['error'], function(i, v) {
                                notify('error', v);
                            });
                            return true;
                        }
                    }
                });
            });

            $('.readMsg').one('click', function() {
                var escrowId = `{{ $escrow->id }}`;

                $.ajax({
                    url: "{{ route('user.escrow.read.message', ':id') }}".replace(':id', escrowId),
                    type: "GET",
                    success: function(response) {
                        $('.unreadCounter').hide();
                    }
                });
            });

            // chat-area
            $(".msg_history").animate({
                scrollTop: $('.msg_history').prop("scrollHeight")
            }, 1);

            var actionModal = $('#actionModal');

            $('.user-action').on('click', function() {
                actionModal.modal('show');
            });

            $('.message-form').on("submit", function(e) {
                e.preventDefault();
                $(this).find('button[type=submit]');
                let message = $(this).find('[name=message]').val();

                var url = '{{ route('user.escrow.message.reply') }}';
                var data = {
                    _token: "{{ csrf_token() }}",
                    conversation_id: "{{ $conversation->id }}",
                    message: $(this).find('[name=message]').val()
                }

                $.post(url, data, function(response) {
                    if (response['error']) {
                        $.each(response['error'], function(i, v) {
                            notify('error', v);
                        });
                        return true;
                    }

                    var html = `
                            <div class="msg-list__item">
                                <div class="msg-send">
                                    <div class="msg-send__content">
                                        <p class="msg-send__text mb-0">
                                            ${response['message']}
                                        </p>
                                    </div>
                                    <ul class="list msg-send__history  justify-content-end ">
                                        <div class="msg-receive__history-item">${response['created_time']}</div>
                                        <div class="msg-receive__history-item">${response['created_diff']}</div>
                                    </ul>
                                </div>
                            </li>
                    `;

                    $('.msg-list').append(html);
                    $(".msg_history").animate({
                        scrollTop: $('.msg_history').prop("scrollHeight")
                    }, 1);
                });
                $(this).find('[name=message]').val('')

            });

            $('.reloadButton').on("click", function() {
                var url = '{{ route('user.escrow.message.get') }}';
                var data = {
                    conversation_id: "{{ $conversation->id }}"
                }
                $.get(url, data, function(response) {

                    if (response['error']) {
                        $.each(response['error'], function(i, v) {
                            notify('error', v);
                        });
                        return true;
                    }

                    $('.msg-list').html(response);
                    $(".msg_history").animate({
                        scrollTop: $('.msg_history').prop("scrollHeight")
                    }, 1);
                });
            });

        })(jQuery);
    </script>
@endpush


@push('style')
    <style>
        .offcanvas {
            width: 1000px !important;
        }

        .badge--notification {
            padding: 1px 6px;
            color: hsl(var(--white));
            background-color: hsl(var(--danger-l-100));
            font-size: 10px;
            border-radius: 999px;
            font-weight: 700;
        }

        .list-group-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .offcanvas .custom--card {
            border-bottom-left-radius: 0;
            border-bottom-right-radius: 0;
        }

        .msg-receive__content {
            color: #000000;
            padding: 15px;
            font-size: 14px;
            border: 1px solid #f6f7f8;
            background: #f6f7f8;
            border-radius: 10px;
            display: inline-block;
            max-width: 60ch;
            min-width: 60ch;
        }

        .select2-container,
        .select2-container>.selection {
            width: 100% !important;
        }
    </style>
@endpush
