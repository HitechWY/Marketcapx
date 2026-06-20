<?php

namespace App\Traits;

use App\Constants\Status;
use App\Models\Transaction;
use App\Models\Wallet;
use Illuminate\Support\Facades\Route;

/**
 * Shared, READ-ONLY Inertia dashboard prop builder for the user / merchant / agent
 * panels (they share the same dashboard shape and helper methods). No writes, no money
 * mutation — display mapping only. Used by the F2/F3 dashboard migrations.
 */
trait BuildsPanelDashboard
{
    /**
     * Shape a Transaction for the Inertia UI (read-only display fields).
     *
     * @return array<string, mixed>
     */
    protected function mapTransaction($h): array
    {
        return [
            'trxType'      => $h->trx_type,
            'remark'       => __(ucwords(str_replace('_', ' ', (string) $h->remark))),
            'date'         => showDateTime($h->created_at, 'M d Y @g:i:a'),
            'details'      => __((string) $h->details),
            'receiver'     => $h->receiver ? @$h->receiver->username : null,
            'amount'       => showAmount($h->amount, $h->currency, currencyFormat: false),
            'code'         => $h->currency->currency_code,
            'trx'          => $h->trx,
            'charge'       => (float) $h->charge,
            'chargeType'   => $h->charge_type,
            'chargeFmt'    => showAmount($h->charge, $h->currency, currencyFormat: false),
            'beforeCharge' => showAmount($h->before_charge, $h->currency, currencyFormat: false),
            'postBalance'  => showAmount($h->post_balance, $h->currency, currencyFormat: false),
        ];
    }

    /**
     * Build the full read-only dashboard props for a panel owner.
     *
     * @param  mixed   $owner   the authenticated user/merchant/agent model
     * @param  string  $type    USER | MERCHANT | AGENT
     * @param  string  $prefix  route-name prefix: user | merchant | agent
     * @return array<string, mixed>
     */
    protected function buildDashboardProps($owner, string $type, string $prefix): array
    {
        $wallets    = $owner->topTransactedWallets()->take(3)->with('currency')->get();
        $allWallets = $owner->topTransactedWallets()->with('currency')->get();

        $totalBalance = 0;
        foreach ($allWallets as $w) {
            $totalBalance += $w->balance * $w->currency->rate;
        }

        $insight   = $owner->moneyInOut();
        $histories = Transaction::where('user_id', $owner->id)->where('user_type', $type)
            ->with('currency', 'receiverUser', 'receiverAgent', 'receiverMerchant')
            ->orderBy('id', 'desc')->take(10)->get();

        $kycContent = @getContent('kyc.content', true)?->data_values;
        $transferOn = (bool) optional(module('transfer_money'))->status;

        return [
            'wallets'      => $wallets->map(fn ($w) => [
                'balance'     => showAmount($w->balance, $w->currency, currencyFormat: false),
                'symbol'      => $w->currency->currency_symbol,
                'code'        => $w->currency->currency_code,
                'transferUrl' => ($transferOn && Route::has("$prefix.transfer"))
                    ? route("$prefix.transfer", ['wallet' => $w->currency->currency_code])
                    : null,
            ])->all(),
            'totalBalance' => [
                'amount' => showAmount($totalBalance, currencyFormat: false),
                'symbol' => gs('cur_sym'),
                'code'   => gs('cur_text'),
            ],
            'insight'      => [
                'in'  => $insight['totalMoneyIn'],
                'out' => $insight['totalMoneyOut'],
            ],
            'kyc'          => [
                'status'          => (int) $owner->kv,
                'rejectionReason' => $owner->kyc_rejection_reason,
                'messages'        => [
                    'reject'   => $kycContent ? __(@$kycContent->reject) : null,
                    'required' => $kycContent ? __(@$kycContent->required) : null,
                    'pending'  => $kycContent ? __(@$kycContent->pending) : null,
                ],
                'formUrl'         => Route::has("$prefix.kyc.form") ? route("$prefix.kyc.form") : null,
                'dataUrl'         => Route::has("$prefix.kyc.data") ? route("$prefix.kyc.data") : null,
            ],
            'histories'    => $histories->map(fn ($h) => $this->mapTransaction($h))->all(),
            'walletsUrl'   => Route::has("$prefix.wallets") ? route("$prefix.wallets") : null,
            'insightUrl'   => Route::has("$prefix.check.insight") ? route("$prefix.check.insight") : null,
        ];
    }

    /**
     * Read-only transactions props (paginator + filters + currency options).
     *
     * @return array<string, mixed>
     */
    protected function buildTransactionsProps($owner, $request): array
    {
        $transactions = $owner->trxLog($request);
        $transactions->through(fn ($h) => $this->mapTransaction($h));

        return [
            'transactions' => $transactions,
            'filters'      => [
                'search'    => (string) $request->search,
                'type'      => (string) $request->type,
                'operation' => (string) $request->operation,
                'time'      => (string) $request->time,
                'currency'  => (string) $request->currency,
            ],
            'currencies'   => $owner->wallets()->with('currency')->get()
                ->map(fn ($w) => $w->currency->currency_code)->unique()->values()->all(),
        ];
    }

    /**
     * Read-only wallets props.
     *
     * @return array<string, mixed>
     */
    protected function buildWalletsProps($owner, string $type, string $prefix): array
    {
        $wallets    = Wallet::hasCurrency()->where('user_id', $owner->id)->where('user_type', $type)
            ->with('currency')->orderBy('balance', 'DESC')->get();
        $transferOn = (bool) optional(module('transfer_money'))->status;

        return [
            'wallets' => $wallets->map(fn ($w) => [
                'balance'     => showAmount($w->balance, $w->currency, currencyFormat: false),
                'symbol'      => $w->currency->currency_symbol,
                'code'        => $w->currency->currency_code,
                'transferUrl' => ($transferOn && Route::has("$prefix.transfer"))
                    ? route("$prefix.transfer", ['wallet' => $w->currency->currency_code])
                    : null,
            ])->all(),
        ];
    }

    /**
     * Shape a Deposit row for the Inertia UI (read-only display fields). Mirrors the
     * user deposit-history mapping; manual-deposit file attachments resolve through the
     * shared user.download.attachment route (the same one the Blade views used).
     *
     * @return array<string, mixed>
     */
    protected function mapDeposit($d): array
    {
        $isManual = $d->method_code >= 1000 && $d->method_code <= 5000;
        $detail   = [];
        if ($isManual) {
            foreach (@$d->detail ?? [] as $info) {
                $value = $info->value ?? null;
                if (($info->type ?? null) == 'file' && $value) {
                    $value = route('user.download.attachment', encrypt(getFilePath('verify') . '/' . $value));
                }
                $detail[] = ['name' => $info->name ?? '', 'type' => $info->type ?? 'text', 'value' => $value];
            }
        }

        return [
            'trx'           => $d->trx,
            'gateway'       => __(@$d->gateway?->name),
            'date'          => showDateTime($d->created_at),
            'dateHuman'     => diffForHumans($d->created_at),
            'amount'        => showAmount($d->amount, currencyFormat: false),
            'charge'        => showAmount($d->charge, currencyFormat: false),
            'total'         => showAmount($d->amount + $d->charge, currencyFormat: false),
            'symbol'        => $d->currency->currency_symbol,
            'code'          => $d->currency->currency_code,
            'statusBadge'   => $d->statusBadge, // admin-trusted HTML
            'status'        => (int) $d->status,
            'isManual'      => $isManual,
            'detail'        => $detail,
            'adminFeedback' => $d->status == Status::PAYMENT_REJECT ? $d->admin_feedback : null,
        ];
    }
}
