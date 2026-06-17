@foreach ($messages as $message)
    @php
        $classText = $message->admin_id != 0 ? 'send' : 'receive';
    @endphp
    <li class="msg-list__item">
        <div class="msg-{{ $classText }}">
            @if ($escrow->status == Status::ESCROW_DISPUTED && $message->admin_id == 0)
                <p>{{ @$message->sender->username ?? $message->admin->username }}</p>
            @endif
            <div class="msg-{{ $classText }}__content">
                <p class="msg-{{ $classText }}__text mb-0">
                    {{ __($message->message) }}
                </p>
            </div>
            <ul class="msg-{{ $classText }}__history @if ($classText == 'send') justify-content-end @endif">
                <li class="msg-receive__history-item">{{ $message->created_at->format('h:i A') }}</li>
                <li class="msg-receive__history-item">{{ $message->created_at->diffForHumans() }}</li>
            </ul>
        </div>
    </li>
@endforeach
