<?php

namespace App\Traits;

use App\Constants\Status;
use App\Models\Currency;

/**
 * Shared helpers for the admin READ-ONLY Inertia list pages (F6b). Display mapping only —
 * no writes. Status is mapped to a {label, tone} chip (rendered as an MD3 chip in Vue) so
 * no raw admin Blade-badge HTML (whose CSS lives outside the Inertia bundle) is serialized.
 */
trait BuildsAdminReadonly
{
    /**
     * @return array{label: string, tone: string}
     */
    protected function paymentChip($status): array
    {
        return match ((int) $status) {
            Status::PAYMENT_SUCCESS => ['label' => 'Successful', 'tone' => 'success'],
            Status::PAYMENT_PENDING => ['label' => 'Pending', 'tone' => 'warning'],
            Status::PAYMENT_REJECT  => ['label' => 'Rejected', 'tone' => 'danger'],
            default                 => ['label' => 'Initiated', 'tone' => 'info'],
        };
    }

    /**
     * @return array{label: string, tone: string}
     */
    protected function ticketStatusChip($status): array
    {
        return match ((int) $status) {
            Status::TICKET_ANSWER => ['label' => 'Answered', 'tone' => 'success'],
            Status::TICKET_REPLY  => ['label' => 'Customer Reply', 'tone' => 'warning'],
            Status::TICKET_CLOSE  => ['label' => 'Closed', 'tone' => 'muted'],
            default               => ['label' => 'Open', 'tone' => 'info'],
        };
    }

    /**
     * @return array{label: string, tone: string}
     */
    protected function ticketPriorityChip($status): array
    {
        return match ((int) $status) {
            Status::PRIORITY_HIGH   => ['label' => 'High', 'tone' => 'danger'],
            Status::PRIORITY_MEDIUM => ['label' => 'Medium', 'tone' => 'info'],
            default                 => ['label' => 'Low', 'tone' => 'muted'],
        };
    }

    /**
     * Currency options ({value:id, label:code}) for the read-only currency filter.
     *
     * @return array<int, array{value: int, label: string}>
     */
    protected function currencyOptions(): array
    {
        return Currency::orderBy('currency_code')->get(['id', 'currency_code'])
            ->map(fn ($c) => ['value' => $c->id, 'label' => $c->currency_code])->all();
    }

    /**
     * The user-type filter options shared by deposit/withdraw lists.
     *
     * @return array<int, array{value: string, label: string}>
     */
    protected function userTypeOptions(): array
    {
        return [
            ['value' => 'user', 'label' => 'User'],
            ['value' => 'agent', 'label' => 'Agent'],
        ];
    }
}
