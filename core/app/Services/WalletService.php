<?php

namespace App\Services;

use App\Models\Transaction;
use App\Models\Wallet;

/**
 * Central money-movement primitives.
 *
 * Phase 4 (first extraction): this currently owns the "credit a wallet and record
 * the ledger transaction" primitive, wired into the deposit flow only. The caller
 * is responsible for running it inside a DB transaction and for any status changes,
 * so locking + the ledger insert stay atomic with the surrounding operation.
 *
 * Future flows (transfer, payment, money-out, withdrawal, etc.) should migrate to
 * this service one at a time, behind tests — see wallet-ledger-service-design.md.
 */
class WalletService
{
    /**
     * Atomically credit a wallet and write the corresponding ledger transaction.
     *
     * MUST be called inside a DB transaction by the caller. Locks the wallet row
     * (lockForUpdate) before mutating the balance.
     *
     * @param  int|string  $walletId
     * @param  float|string  $amount
     * @param  array  $meta  user_id, user_type, currency_id, trx (required);
     *                       before_charge, charge, trx_type, details, remark (optional)
     * @return array{wallet: Wallet, transaction: Transaction}
     */
    public function credit($walletId, $amount, array $meta): array
    {
        $wallet = Wallet::where('id', $walletId)->lockForUpdate()->first();
        $wallet->balance += $amount;
        $wallet->save();

        $transaction                = new Transaction();
        $transaction->user_id       = $meta['user_id'];
        $transaction->user_type     = $meta['user_type'];
        $transaction->wallet_id     = $wallet->id;
        $transaction->currency_id   = $meta['currency_id'];
        $transaction->before_charge = $meta['before_charge'] ?? $amount;
        $transaction->amount        = $amount;
        $transaction->post_balance  = $wallet->balance;
        $transaction->charge        = $meta['charge'] ?? 0;
        $transaction->trx_type      = $meta['trx_type'] ?? '+';
        $transaction->details       = $meta['details'] ?? '';
        $transaction->trx           = $meta['trx'];
        $transaction->remark        = $meta['remark'] ?? '';
        $transaction->save();

        return ['wallet' => $wallet, 'transaction' => $transaction];
    }
}
