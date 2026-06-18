<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Lib\CurlRequest;
use App\Models\ApiPayment;
use App\Models\Merchant;
use App\Models\Transaction;
use App\Models\TransactionCharge;
use App\Models\User;
use App\Models\Wallet;
use App\Traits\ApiPaymentProcess;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class GetPaymentController extends Controller
{

    use ApiPaymentProcess;

    protected $paymentType = 'live';

    public function __construct()
    {
        parent::__construct();
    }

    public function checkValidCode($apiPayment, $code, $add_min = 10000)
    {
        if (!$code) {
            return false;
        }

        if (!$apiPayment->ver_code_at) {
            return false;
        }

        if (Carbon::parse($apiPayment->ver_code_at)->addMinutes($add_min) < Carbon::now()) {
            return false;
        }

        if ($apiPayment->ver_code !== $code) {
            return false;
        }

        return true;
    }

    public function checkEmail(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'email' => 'required',
        ]);

        if ($validator->fails()) {
            return $this->notify('email_validation');
        }

        $user = User::where('email', $request->email)->first();
        if (!$user) {
            return $this->notify('email_check');
        }

        if ($user->kv == 0) {
            return $this->notify('kyc_unverified');
        }
        if ($user->kv == 2) {
            return $this->notify('kyc_pending');
        }

        $apiPayment = $this->getPaymentInfo();
        // REPLAY SAFETY (B4): getPaymentInfo() returns an error array when the
        // session payment is missing or already settled/cancelled (status 1/2).
        // Without this guard the next line assigns a property on an array, which is
        // a fatal Error on a replayed/expired checkout. Return the existing JSON
        // error shape instead.
        if (!$apiPayment instanceof ApiPayment) {
            return $apiPayment;
        }
        $apiPayment->ver_code    = verificationCode(6);
        $apiPayment->ver_code_at = Carbon::now();
        $apiPayment->payer_id    = $user->id;
        $apiPayment->save();

        notify($user, 'PAYMENT_VER_CODE', [
            'code' => $apiPayment->ver_code,
        ], ['email']);

        return $this->notify('email_check_done');

    }

    public function sendVerifyCode()
    {

        $pageTitle  = "Verify Payment";
        $apiPayment = $this->getPaymentInfo();
        // REPLAY SAFETY (B4): this view both reads and saves the payment, so a
        // settled/expired payment (error array) would fatally assign a property on
        // an array. A 404 is the correct "this hosted payment is no longer valid"
        // response instead of a 500.
        if (!$apiPayment instanceof ApiPayment) {
            abort(404);
        }

        if ($this->checkValidCode($apiPayment, $apiPayment->ver_code, 2)) {
            $target_time = Carbon::parse($apiPayment->ver_code_at)->addMinutes(2)->timestamp;
            $delay       = $target_time - time();
            throw ValidationException::withMessages(['resend' => 'Please Try after ' . $delay . ' Seconds']);
        }

        if (!$this->checkValidCode($apiPayment, $apiPayment->ver_code)) {
            $apiPayment->ver_code    = verificationCode(6);
            $apiPayment->ver_code_at = Carbon::now();
            $apiPayment->save();
        } else {
            $apiPayment->ver_code    = $apiPayment->ver_code;
            $apiPayment->ver_code_at = Carbon::now();
            $apiPayment->save();
        }

        notify($apiPayment->payer, 'PAYMENT_VER_CODE', [
            'code' => $apiPayment->ver_code,
        ], ['email']);

        if ($this->paymentType == 'live') {
            $verifyRoute = route('confirm.payment');
        } else {
            $verifyRoute = route('test.confirm.payment');
        }

        return view('Template::api_payment.verify_payment', compact('pageTitle', 'apiPayment', 'verifyRoute'));
    }

    public function verifyPaymentConfirm(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'code' => 'required',
        ]);

        if ($validator->fails()) {
            return $this->notify('code_validation');
        }

        $apiPayment = $this->getPaymentInfo();
        // REPLAY SAFETY (B4): a settled/cancelled/expired payment yields an error
        // array here. Returning it (JSON error shape) makes a double-confirm an
        // explicit "invalid transaction" instead of dereferencing an array (which
        // read null and produced a misleading "code mismatch").
        if (!$apiPayment instanceof ApiPayment) {
            return $apiPayment;
        }

        if ($request->code != $apiPayment->ver_code) {
            return $this->notify('code_not_match');
        }

        $payer = User::find($apiPayment->payer_id);
        if (!$payer) {
            return $this->notify('user_not_found');
        }

        $payerWallet = Wallet::hasCurrency()->where('user_type', 'USER')->where('user_id', $payer->id)->where('currency_id', $apiPayment->currency_id)->first();
        if (!$payerWallet) {
            return $this->notify('wallet_not_found');
        }

        $merchant = Merchant::find($apiPayment->merchant_id);
        if (!$merchant) {
            return $this->notify('merchant_not_found');
        }

        $merchantWallet = Wallet::hasCurrency()->where('user_type', 'MERCHANT')->where('user_id', $merchant->id)->where('currency_id', $apiPayment->currency_id)->first();
        if (!$merchantWallet) {
            $merchantWallet                = new Wallet();
            $merchantWallet->user_id       = $merchant->id;
            $merchantWallet->user_type     = 'MERCHANT';
            $merchantWallet->currency_id   = $payerWallet->currency_id;
            $merchantWallet->currency_code = $payerWallet->currency->currency_code;
            $merchantWallet->save();
        }

        if ($apiPayment->amount > $payerWallet->balance) {
            return $this->notify('insuf_balance');
        }

        $paymentCharge = TransactionCharge::where('slug', 'api_charge')->first();
        if (!$paymentCharge) {
            return $this->notify('charge_not_found');
        }

        $rate        = @$apiPayment->currency->rate;
        $fixedCharge = currencyConverter($paymentCharge->fixed_charge, $rate);
        $totalCharge = chargeCalculator($apiPayment->amount, $paymentCharge->percent_charge, $fixedCharge);

        $cap = currencyConverter($paymentCharge->cap, $rate);
        if ($paymentCharge->cap != -1 && $totalCharge > $cap) {
            $totalCharge = $cap;
        }

        // ATOMICITY + IDEMPOTENCY (deferred money-flow wave; mirrors the Phase 5
        // payment fix): getPaymentInfo() checks status outside any lock and the
        // status flag was flipped AFTER crediting, so concurrent confirms could
        // both debit. Lock the apiPayment row and re-check status==0 inside the tx,
        // lock both wallets (id-ascending) and re-check the payer balance, then
        // flip status BEFORE the wallet mutations and write both ledger rows. The
        // outbound IPN curl + notify run after commit (no network I/O inside the
        // tx). Every amount/charge/ledger field is unchanged.
        $transactedAmount = ($apiPayment->amount - $totalCharge);
        $apiPaymentId     = $apiPayment->id;
        $alreadyProcessed = false;
        $insufficient     = false;
        $processed        = false;
        $merchantBalance  = null;

        DB::transaction(function () use (
            $apiPayment, $apiPaymentId, $payer, $merchant, $payerWallet, $merchantWallet, $transactedAmount, $totalCharge,
            &$alreadyProcessed, &$insufficient, &$processed, &$merchantBalance
        ) {
            $lockedPayment = $apiPayment->newQuery()->where('id', $apiPaymentId)->where('status', 0)->lockForUpdate()->first();
            if (!$lockedPayment) {
                $alreadyProcessed = true;
                return; // concurrent confirm already settled this payment
            }

            $ids = array_unique([$payerWallet->id, $merchantWallet->id]);
            sort($ids);
            $locked = [];
            foreach ($ids as $id) {
                $locked[$id] = Wallet::where('id', $id)->lockForUpdate()->first();
            }
            $lockedPayerWallet    = $locked[$payerWallet->id];
            $lockedMerchantWallet = $locked[$merchantWallet->id];

            if ($lockedPayment->amount > $lockedPayerWallet->balance) {
                $insufficient = true;
                return;
            }

            $lockedPayment->status = 1;
            $lockedPayment->save();

            $lockedPayerWallet->balance -= $lockedPayment->amount;
            $lockedPayerWallet->save();

            $payerTrx                = new Transaction();
            $payerTrx->user_id       = $payer->id;
            $payerTrx->user_type     = 'USER';
            $payerTrx->wallet_id     = $lockedPayerWallet->id;
            $payerTrx->currency_id   = $lockedPayerWallet->currency_id;
            $payerTrx->amount        = $lockedPayment->amount;
            $payerTrx->post_balance  = $lockedPayerWallet->balance;
            $payerTrx->charge        = 0;
            $payerTrx->trx_type      = '-';
            $payerTrx->remark        = 'make_payment';
            $payerTrx->details       = 'Payment successful to';
            $payerTrx->receiver_id   = $merchant->id;
            $payerTrx->receiver_type = 'MERCHANT';
            $payerTrx->trx           = $lockedPayment->trx;
            $payerTrx->save();

            $lockedMerchantWallet->balance += $transactedAmount;
            $lockedMerchantWallet->save();

            $merchantTrx                = new Transaction();
            $merchantTrx->user_id       = $merchant->id;
            $merchantTrx->user_type     = 'MERCHANT';
            $merchantTrx->wallet_id     = $lockedMerchantWallet->id;
            $merchantTrx->currency_id   = $lockedMerchantWallet->currency_id;
            $merchantTrx->before_charge = $lockedPayment->amount;
            $merchantTrx->amount        = $transactedAmount;
            $merchantTrx->post_balance  = $lockedMerchantWallet->balance;
            $merchantTrx->charge_type   = '-';
            $merchantTrx->charge        = $totalCharge;
            $merchantTrx->trx_type      = '+';
            $merchantTrx->remark        = 'make_payment';
            $merchantTrx->details       = 'Payment successful from';
            $merchantTrx->receiver_id   = $payer->id;
            $merchantTrx->receiver_type = 'USER';
            $merchantTrx->trx           = $lockedPayment->trx;
            $merchantTrx->save();

            $processed       = true;
            $merchantBalance = $lockedMerchantWallet->balance;
        });

        if ($insufficient) {
            return $this->notify('insuf_balance');
        }

        if ($alreadyProcessed && !$processed) {
            // payment already settled by a concurrent request -> idempotent success
            return [
                'error'        => 'no',
                'redirect_url' => $apiPayment->success_url,
            ];
        }

        $apiPayment->status = 1;

        $customKey = $apiPayment->amount . $apiPayment->identifier;

        $res = CurlRequest::curlPostContent($apiPayment->ipn_url, [
            'status'     => 'success',
            'signature'  => strtoupper(hash_hmac('sha256', $customKey, $merchant->secret_api_key)),
            'identifier' => $apiPayment->identifier,
            'data'       => [
                'payment_trx'       => $apiPayment->trx,
                'amount'            => $apiPayment->amount,
                'account_holder'    => @$apiPayment->payer->fullname,
                'payment_type'      => 'hosted',
                'payment_timestamp' => $apiPayment->created_at,
                'charge'            => $totalCharge,
                'currency'          => [
                    'code'   => @$apiPayment->currency->currency_code,
                    'symbol' => @$apiPayment->currency->currency_symbol,

                ],
            ],
        ]);

        notify($merchant, 'MERCHANT_PAYMENT', [
            'amount'        => showAmount($apiPayment->amount, $apiPayment->currency, currencyFormat:false),
            'currency_code' => @$apiPayment->currency->currency_code,
            'customer_name' => @$apiPayment->payer->fullname,
            'charge'        => $totalCharge,
            'trx'           => $apiPayment->trx,
            'time'          => showDateTime($apiPayment->created_at, 'd M Y @ g:i a'),
            'balance'       => showAmount($merchantBalance, $apiPayment->curr, currencyFormat:false),
        ]);

        return [
            'error'        => 'no',
            'redirect_url' => $apiPayment->success_url,
        ];

    }
}
