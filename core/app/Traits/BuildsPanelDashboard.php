<?php

namespace App\Traits;

use App\Models\Transaction;
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
}
