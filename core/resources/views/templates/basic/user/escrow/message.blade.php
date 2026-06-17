@foreach ($messages as $message)
    @php
        $classText = $message->sender_id == auth()->user()->id ? 'send' : 'receive';
    @endphp

    <li class="msg-list__item">
        <div class="msg-{{ $classText }}">
            @if ($escrow->status == 8 && $message->sender_id != auth()->id())
                <p class="mb-0 fw-bold">{{ @$message->sender->username ?? __('System') }}</p>
            @endif
            <div class="msg-{{ $classText }}__content">
                <p class="msg-{{ $classText }}__text mb-0">
                    {{ __($message->message) }}
                </p>
            </div>
            <ul
                class="list msg-{{ $classText }}__history @if ($classText == 'send') justify-content-end @endif">
                <li class="msg-receive__history-item">{{ $message->created_at->format('h:i A') }}</li>
                <li class="msg-receive__history-item">{{ $message->created_at->diffForHumans() }}</li>
            </ul>
        </div>
    </li>
@endforeach
