<?php

namespace Tests\Feature\MoneyFlow;

use App\Http\Controllers\GetPaymentController;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Tests\Concerns\CreatesMoneyFixtures;
use Tests\TestCase;

/**
 * Replay safety for the hosted (api_payments) checkout (audit B4).
 *
 * getPaymentInfo() returns an ERROR ARRAY when the session payment is missing or
 * already settled/cancelled (status 1/2). The JSON action endpoints previously
 * dereferenced that array as an object: checkEmail fatally assigned a property on
 * an array, and verifyPaymentConfirm read a property on an array (null) and
 * returned a misleading "code mismatch". The guards make a replayed/expired
 * checkout an explicit, clean "invalid transaction" error.
 */
class HostedPaymentReplayTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesMoneyFixtures;

    private function putSessionTrx(string $trx): void
    {
        // getPaymentInfo() does decrypt(session('trx')) then strips quotes.
        session(['trx' => encrypt($trx)]);
    }

    public function test_check_email_on_settled_payment_returns_clean_error_not_fatal(): void
    {
        $user = $this->makeUser();
        $user->kv = 1; // KYC verified so checkEmail reaches getPaymentInfo()
        $user->save();

        $payment = $this->makeApiPayment(['status' => 1]); // already settled
        $this->putSessionTrx($payment->trx);

        $controller = new GetPaymentController();
        $result = $controller->checkEmail(Request::create('/initiate/payment/check-mail', 'POST', [
            'email' => $user->email,
        ]));

        // Pre-fix this threw "Attempt to assign property on array". Now it returns
        // the standard JSON error shape and writes no verification code.
        $this->assertIsArray($result);
        $this->assertNotSame('no', $result['error']);
        $this->assertSame('Invalid transaction request', $result['message']);
        // No verification code was issued against the settled payment.
        $this->assertNull($payment->fresh()->ver_code);
    }

    public function test_confirm_payment_on_settled_payment_returns_invalid_not_code_mismatch(): void
    {
        $payment = $this->makeApiPayment(['status' => 1, 'ver_code' => '123456']);
        $this->putSessionTrx($payment->trx);

        $controller = new GetPaymentController();
        $result = $controller->verifyPaymentConfirm(Request::create('/confirm/payment', 'POST', [
            'code' => '123456',
        ]));

        // Settled payment -> explicit invalid-transaction error (not the misleading
        // "verification code mismatch" the array-deref used to produce).
        $this->assertIsArray($result);
        $this->assertNotSame('no', $result['error']);
        $this->assertSame('Invalid transaction request', $result['message']);
    }
}
