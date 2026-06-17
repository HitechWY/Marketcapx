@php
    $currentStep = (int) request()->step;
@endphp

<div class="escrow-form__header">
    <ul class="escrow-step-list">
        @foreach ([1, 2, 3] as $step)
            <li class="escrow-step-list__item {{ @$currentStep == $step ? 'active' : '' }}">
                <span class="escrow-step-list__step">{{ $step }}</span>
            </li>
        @endforeach
    </ul>
</div>
