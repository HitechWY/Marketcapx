<?php

namespace Tests\Feature\MoneyFlow;

use App\Http\Controllers\User\InvoiceController;
use App\Models\Invoice;
use App\Models\Transaction;
use App\Models\UserAction;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\CreatesMoneyFixtures;
use Tests\TestCase;

/**
 * Idempotency coverage for the web invoice-payment flow (payer debit ->
 * invoice-owner credit). A refreshed/replayed confirm must settle once only.
 */
class InvoicePaymentFlowTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesMoneyFixtures;

    public function test_invoice_payment_double_submit_settles_exactly_once(): void
    {
        $currency       = $this->makeCurrency();
        $payer          = $this->makeUser();
        $payerWallet    = $this->makeWallet($payer, 'USER', $currency, 100);
        $receiver       = $this->makeUser();
        $receiverWallet = $this->makeWallet($receiver, 'USER', $currency, 0);

        $invoice = new Invoice();
        $invoice->user_id      = $receiver->id;
        $invoice->user_type    = 'USER';
        $invoice->currency_id  = $currency->id;
        $invoice->invoice_num  = $this->uniq('INV');
        $invoice->invoice_to   = $payer->username;
        $invoice->email        = $payer->email;
        $invoice->address      = 'n/a';
        $invoice->charge       = 0;
        $invoice->total_amount = 30;
        $invoice->get_amount   = 30;
        $invoice->pay_status   = 0;
        $invoice->status       = 1;
        $invoice->save();

        $action = $this->makeUserAction($payer->id, 'USER', 'payment_invoice', [
            'invoice_id'  => $invoice->invoice_num,
            'wallet_id'   => $payerWallet->id,
            'currency_id' => $currency->id,
            'amount'      => 30,
            'done_route'  => route('user.invoice.payment.confirm.done'),
        ]);

        $this->actingAs($payer);
        session(['action_id' => $action->id]);
        $controller = new InvoiceController();

        $controller->invoicePaymentConfirmDone();

        $this->assertEqualsWithDelta(70, $this->balanceOf($payerWallet), 0.00001, 'payer debited 30 once');
        $this->assertEqualsWithDelta(30, $this->balanceOf($receiverWallet), 0.00001, 'receiver credited 30 once');
        $this->assertSame(1, (int) Invoice::whereKey($invoice->id)->value('pay_status'), 'invoice marked paid');
        $this->assertSame(2, Transaction::where('remark', 'invoice_payment')->where('trx', '!=', '')->whereIn('wallet_id', [$payerWallet->id, $receiverWallet->id])->count());
        $this->assertNull(UserAction::find($action->id), 'one-shot action consumed');

        // Replay (one-shot action already consumed -> graceful no-op)
        $controller->invoicePaymentConfirmDone();

        $this->assertEqualsWithDelta(70, $this->balanceOf($payerWallet), 0.00001, 'replay must not debit again');
        $this->assertEqualsWithDelta(30, $this->balanceOf($receiverWallet), 0.00001, 'replay must not credit again');
    }
}
