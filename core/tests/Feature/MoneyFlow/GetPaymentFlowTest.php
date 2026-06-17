<?php

namespace Tests\Feature\MoneyFlow;

use App\Http\Controllers\GetPaymentController;
use App\Models\ApiPayment;
use App\Models\Transaction;
use App\Models\TransactionCharge;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Tests\Concerns\CreatesMoneyFixtures;
use Tests\TestCase;

/**
 * Idempotency coverage for the hosted API-payment confirm flow
 * (GetPaymentController::verifyPaymentConfirm). The status flag was flipped
 * after crediting and checked outside any lock, so a concurrent confirm could
 * double-debit. A second confirm must move funds once only.
 *
 * The outbound IPN curl targets 127.0.0.1:1 (connection refused, fast) so no
 * real merchant endpoint is contacted.
 */
class GetPaymentFlowTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesMoneyFixtures;

    public function test_api_payment_confirm_double_submit_settles_exactly_once(): void
    {
        $currency       = $this->makeCurrency();
        $payer          = $this->makeUser();
        $payerWallet    = $this->makeWallet($payer, 'USER', $currency, 100);
        $merchant       = $this->makeMerchant();
        $merchantWallet = $this->makeWallet($merchant, 'MERCHANT', $currency, 0);

        $charge = new TransactionCharge();
        $charge->slug           = 'api_charge';
        $charge->fixed_charge   = 0;
        $charge->percent_charge = 0;
        $charge->cap            = -1;
        $charge->save();

        $apiPayment = new ApiPayment();
        $apiPayment->trx            = $this->uniq('APIP');
        $apiPayment->merchant_id    = $merchant->id;
        $apiPayment->payer_id       = $payer->id;
        $apiPayment->identifier     = $this->uniq('id');
        $apiPayment->currency_id    = $currency->id;
        $apiPayment->amount         = 30;
        $apiPayment->details        = 'test';
        $apiPayment->ipn_url        = 'http://127.0.0.1:1/ipn';
        $apiPayment->cancel_url     = 'http://127.0.0.1:1/cancel';
        $apiPayment->success_url    = 'http://127.0.0.1:1/success';
        $apiPayment->customer_name  = 'Test Buyer';
        $apiPayment->customer_email = 'buyer@example.test';
        $apiPayment->ver_code       = 123456;
        $apiPayment->ver_code_at    = now();
        $apiPayment->status         = 0;
        $apiPayment->type           = 'live';
        $apiPayment->save();

        session(['trx' => encrypt(json_encode($apiPayment->trx))]);
        $request    = Request::create('/confirm/payment', 'POST', ['code' => '123456']);
        $controller = new GetPaymentController();

        $controller->verifyPaymentConfirm($request);

        $this->assertEqualsWithDelta(70, $this->balanceOf($payerWallet), 0.00001, 'payer debited 30 once');
        $this->assertEqualsWithDelta(30, $this->balanceOf($merchantWallet), 0.00001, 'merchant credited 30 once');
        $this->assertSame(1, (int) ApiPayment::whereKey($apiPayment->id)->value('status'), 'payment marked complete');
        $this->assertSame(1, Transaction::where('wallet_id', $payerWallet->id)->where('remark', 'make_payment')->count());

        // Replay: status==1 now, so getPaymentInfo() rejects the payment before
        // any wallet mutation. (It surfaces a pre-existing notice from the
        // array-return contract of getPaymentInfo, which is not part of this fix;
        // what matters here is that no funds move on replay.)
        try {
            $controller->verifyPaymentConfirm($request);
        } catch (\Throwable $e) {
            // expected: rejected before crediting -> no double-charge
        }

        $this->assertEqualsWithDelta(70, $this->balanceOf($payerWallet), 0.00001, 'replay must not debit again');
        $this->assertEqualsWithDelta(30, $this->balanceOf($merchantWallet), 0.00001, 'replay must not credit again');
        $this->assertSame(1, Transaction::where('wallet_id', $payerWallet->id)->where('remark', 'make_payment')->count());
    }
}
