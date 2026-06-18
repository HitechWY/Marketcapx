<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\TransactionCharge;
use App\Models\Wallet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class MoneyExchangeController extends Controller
{

    public function exchangeForm()
    {
        $notify[]       = "Exchange Money";
        $user           = auth()->user();
        $exchangeCharge = TransactionCharge::where('slug', 'exchange_charge')->first();
        $fromWallets    = $user->wallets()->where('balance', '>', 0)->get();
        $toWallets      = $user->wallets()->get();

        return response()->json([
            'remark'  => 'exchange_money',
            'status'  => 'success',
            'message' => ['success' => $notify],
            'data'    => [
                'from_wallets'    => $fromWallets,
                'to_wallets'      => $toWallets,
                'exchange_charge' => $exchangeCharge,
            ],
        ]);
    }

    public function exchangeConfirm(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'amount'         => 'required|gt:0',
            'from_wallet_id' => 'required|integer',
            'to_wallet_id'   => 'required|integer',
        ],
            [
                'from_wallet_id.required' => 'Your wallet currency is required from which you want to exchange.',
                'to_wallet_id.required'   => 'Your wallet currency is required to which you want to exchange.',
            ]);

        if ($validator->fails()) {
            return response()->json([
                'remark'  => 'validation_error',
                'status'  => 'error',
                'message' => ['error' => $validator->errors()->all()],
            ]);
        }

        $user = auth()->user();

        $exchangeCharge = TransactionCharge::where('slug', 'exchange_charge')->first();
        if (!$exchangeCharge) {
            return response()->json([
                'remark'  => 'validation_error',
                'status'  => 'error',
                'message' => ['error' => ['Sorry, Transaction charge not found']],
            ]);
        }

        $fromWallet = Wallet::checkWallet(['user' => $user, 'type' => 'USER'])->find($request->from_wallet_id);
        if (!$fromWallet) {
            return response()->json([
                'remark'  => 'validation_error',
                'status'  => 'error',
                'message' => ['error' => ['Your wallet currency is not found from which you want to exchange']],
            ]);
        }

        if ($request->amount > $fromWallet->balance) {
            return response()->json([
                'remark'  => 'validation_error',
                'status'  => 'error',
                'message' => ['error' => ['Sorry! Insufficient balance in this wallet']],
            ]);
        }

        // SCOPE (B3): the destination must be one of the user's OWN wallets — the
        // exchange form only ever returns $user->wallets() as to_wallets. Scoping
        // this exactly like $fromWallet prevents crediting an arbitrary/other-user
        // wallet (and writing a self-owned ledger row onto someone else's wallet).
        $toWallet = Wallet::checkWallet(['user' => $user, 'type' => 'USER'])->find($request->to_wallet_id);
        if (!$toWallet) {
            return response()->json([
                'remark'  => 'validation_error',
                'status'  => 'error',
                'message' => ['error' => ['Your wallet currency is not found to which you want to exchange']],
            ]);
        }

        if ($fromWallet->id == $toWallet->id) {
            return response()->json([
                'remark'  => 'validation_error',
                'status'  => 'error',
                'message' => ['error' => ['Can\'t exchange money to same wallet']],
            ]);
        }

        //Converting charges to FROM wallet currency
        $fromWalletAmount      = $request->amount;
        $fixedCharge           = $exchangeCharge->fixed_charge / $fromWallet->currency->rate;
        $totalFromWalletCharge = chargeCalculator($fromWalletAmount, $exchangeCharge->percent_charge, $fixedCharge);

        $cap = $exchangeCharge->cap / $fromWallet->currency->rate;
        if ($exchangeCharge->cap != -1 && $totalFromWalletCharge > $cap) {
            $totalFromWalletCharge = $cap;
        }

        if ($totalFromWalletCharge > $fromWallet->balance) {
            return response()->json([
                'remark'  => 'validation_error',
                'status'  => 'error',
                'message' => ['error' => ['Your don\'t have sufficient balance from which you want to exchange']],
            ]);
        }

        $fromWalletAmount += $totalFromWalletCharge; //Total amount of FROM currency including charge
        $baseCurrAmount = $fromWallet->currency->rate * $request->amount; // Converting amount to site default currency

        // Converting amount to expected currency
        $finalAmount = getAmount($baseCurrAmount / $toWallet->currency->rate, 8);

        if ($toWallet->currency->currency_type == 1) {
            $finalAmount = getAmount($baseCurrAmount / $toWallet->currency->rate, 2);
        }

        // ATOMICITY (Phase 5, mirrors the Phase 3 deposit fix): debit source +
        // credit destination in one transaction with both wallet rows locked
        // (id-ascending) and the source balance re-checked under the lock, so a
        // partial failure can't lose money and concurrent exchanges can't
        // overdraft. Amounts/charges/ledger fields unchanged.
        $fromWalletTrxRef = null;
        $insufficient     = false;
        $fromBalance      = null;
        $toBalance        = null;

        DB::transaction(function () use (
            $user, $fromWallet, $toWallet, $request, $fromWalletAmount,
            $totalFromWalletCharge, $finalAmount,
            &$fromWalletTrxRef, &$insufficient, &$fromBalance, &$toBalance
        ) {
            $ids = [$fromWallet->id, $toWallet->id];
            sort($ids);
            $locked = [];
            foreach ($ids as $id) {
                $locked[$id] = Wallet::where('id', $id)->lockForUpdate()->first();
            }
            $fromW = $locked[$fromWallet->id];
            $toW   = $locked[$toWallet->id];

            if ($fromWalletAmount > $fromW->balance) {
                $insufficient = true;
                return;
            }

            $fromW->balance -= $fromWalletAmount;
            $fromW->save();

            $fromWalletTrx                = new Transaction();
            $fromWalletTrx->user_id       = $user->id;
            $fromWalletTrx->user_type     = 'USER';
            $fromWalletTrx->wallet_id     = $fromW->id;
            $fromWalletTrx->currency_id   = $fromW->currency_id;
            $fromWalletTrx->before_charge = $request->amount;
            $fromWalletTrx->amount        = $fromWalletAmount;
            $fromWalletTrx->post_balance  = $fromW->balance;
            $fromWalletTrx->charge        = $totalFromWalletCharge;
            $fromWalletTrx->charge_type   = '+';
            $fromWalletTrx->trx_type      = '-';
            $fromWalletTrx->remark        = 'exchange_money';
            $fromWalletTrx->details       = 'Exchange Money (From)';
            $fromWalletTrx->trx           = getTrx();
            $fromWalletTrx->save();

            $toW->balance += $finalAmount;
            $toW->save();

            $toWalletTrx                = new Transaction();
            $toWalletTrx->user_id       = $user->id;
            $toWalletTrx->user_type     = 'USER';
            $toWalletTrx->wallet_id     = $toW->id;
            $toWalletTrx->currency_id   = $toW->currency_id;
            $toWalletTrx->before_charge = $finalAmount;
            $toWalletTrx->amount        = $finalAmount;
            $toWalletTrx->post_balance  = $toW->balance;
            $toWalletTrx->charge        = 0;
            $toWalletTrx->charge_type   = '+';
            $toWalletTrx->trx_type      = '+';
            $toWalletTrx->remark        = 'exchange_money';
            $toWalletTrx->details       = 'Exchange Money (To)';
            $toWalletTrx->trx           = $fromWalletTrx->trx;
            $toWalletTrx->save();

            $fromWalletTrxRef = $fromWalletTrx;
            $fromBalance      = $fromW->balance;
            $toBalance        = $toW->balance;
        });

        if ($insufficient) {
            return response()->json([
                'remark'  => 'validation_error',
                'status'  => 'error',
                'message' => ['error' => ['Sorry! Insufficient balance in this wallet']],
            ]);
        }

        notify($user, 'EXCHANGE_MONEY', [
            'from_wallet_amount'   => $request->amount,
            'from_wallet_currency' => $fromWallet->currency->currency_code,
            'to_wallet_amount'     => showAmount($finalAmount, $fromWallet->currency, currencyFormat:false),
            'to_wallet_currency'   => $toWallet->currency->currency_code,
            'from_balance'         => showAmount($fromBalance, $fromWallet->currency, currencyFormat:false),
            'to_balance'           => showAmount($toBalance, $fromWallet->currency, currencyFormat:false),
            'trx'                  => $fromWalletTrxRef->trx,
            'time'                 => showDateTime($fromWalletTrxRef->created_at, 'd/M/Y @h:i a'),
        ]);

        return response()->json([
            'remark'  => 'exchange_money_done',
            'status'  => 'success',
            'message' => ['success' => ['Money exchanged successfully']],
        ]);
    }
}
