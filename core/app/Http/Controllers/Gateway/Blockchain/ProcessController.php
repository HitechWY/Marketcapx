<?php

namespace App\Http\Controllers\Gateway\Blockchain;

use App\Constants\Status;
use App\Models\Deposit;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Gateway\PaymentController;
use App\Lib\CurlRequest;
use Illuminate\Http\Request;

class ProcessController extends Controller
{
    /*
     * Blockchain Pay Gateway
     */

    public static function process($deposit)
    {
        $blockchainAcc = json_decode($deposit->gatewayCurrency()->gateway_parameter);

        $url = "https://blockchain.info/ticker";
        $response = CurlRequest::curlContent($url);
        $res = json_decode($response);

        $btcrate = @$res->USD->last ?? 0;

        $usd = $deposit->final_amount;
        $btcamount = $usd / $btcrate;
        $btc = round($btcamount, 8);

        $deposit = Deposit::where('trx', $deposit->trx)->orderBy('id', 'DESC')->firstOrFail();


        if ($deposit->btc_amount == 0 || $deposit->btc_wallet == "") {
            $myApiKey = trim($blockchainAcc->api_key);
            $url = "https://api.blockchain.com/v3/exchange/deposits/BTC";
            $header = [
                "X-API-Token: $myApiKey"
            ];
            $response = CurlRequest::curlPostContent($url,header:$header);
            $response = json_decode($response);
            if (@$response->address == '') {
                $send['error'] = true;
                $send['message'] = 'BLOCKCHAIN API HAVING ISSUE. PLEASE TRY LATER. ' . @$response->message;
            } else {

                $sendto = $response->address;
                $deposit['btc_wallet'] = $sendto;
                $deposit['btc_amount'] = $btc;
                $deposit->update();
            }
        }
        $send['amount'] = $deposit->btc_amount;
        $send['sendto'] = $deposit->btc_wallet;
        $send['img'] = cryptoQR($deposit->btc_wallet);
        $send['currency'] = "BTC";
        $send['view'] = 'payment.crypto';
        return json_encode($send);
    }

    public function ipn(Request $request)
    {
        $track   = $request->invoice_id;
        $deposit = Deposit::where('trx', $track)->orderBy('id', 'DESC')->first();

        // Reject unknown or already-processed deposits without persisting anything.
        if (!$deposit || $deposit->status != Status::PAYMENT_INITIATE) {
            abort(404);
        }

        // SECURITY (Phase 2): verify authenticity against the gateway's CONFIGURED
        // secret (previously a hardcoded constant). Fail closed if none is configured.
        $blockchainAcc    = json_decode($deposit->gatewayCurrency()->gateway_parameter);
        $configuredSecret = isset($blockchainAcc->secret) ? (string) $blockchainAcc->secret : '';
        $providedSecret   = (string) $request->input('secret', '');

        $valueInBtc = ((float) $request->input('value', 0)) / 100000000;

        $authentic = $configuredSecret !== ''
            && hash_equals($configuredSecret, $providedSecret)
            && $request->address == $deposit->btc_wallet
            && $deposit->btc_amount == $valueInBtc
            && $request->confirmations > 2;

        if (!$authentic) {
            abort(403);
        }

        // Authenticity confirmed — record the (now-trusted) callback details, then credit.
        $details = [];
        foreach ($request->query() as $key => $value) {
            $details[$key] = $value;
        }
        $deposit->detail = $details;
        $deposit->save();

        PaymentController::userDataUpdate($deposit);
    }
}
