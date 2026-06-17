<?php

namespace App\Http\Controllers\Api;

use App\Models\Wallet;
use App\Models\Deposit;
use App\Models\Currency;
use App\Constants\Status;
use Illuminate\Http\Request;
use App\Models\GatewayCurrency;
use App\Http\Controllers\Api\Common;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use App\Lib\FormProcessor;
use App\Models\AdminNotification;


class PaymentController extends Controller
{
    use Common;

    public function methods()
    {
        $wallets = $this->gatewayWithLimit(auth()->user()->wallets);

        $notify[] = 'Payment Methods';
        return responseSuccess('deposit_methods', $notify, [
            'wallets' => $wallets,
        ]);
    }

    public function gatewayWithLimit($wallets)
    {
        foreach ($wallets ?? [] as $wallet) {

            $wallet->currency->gateways = $wallet->gateways();

            foreach ($wallet->currency->gateways ?? [] as $gateway) {
                $rate = $wallet->currency->rate;

                $min = $gateway->min_amount / $rate;
                $max = $gateway->max_amount / $rate;

                $gateway->deposit_min_limit = $min;
                $gateway->deposit_max_limit = $max;
            }
        }

        return $wallets;
    }

    public function depositInsert(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'amount'      => 'required|numeric|gt:0',
            'method_code' => 'required',
            'wallet_id'   => 'required',
        ]);

        if ($validator->fails()) {
            return responseError('validation_error', $validator->errors());
        }

        $user     = auth()->user();
        $userType = $this->guard()['user_type'];

        $wallet = Wallet::where('user_id', $user->id)->where('user_type', $userType)->where('id', $request->wallet_id)->first();

        if (!$wallet) {
            $notify[] = 'Invalid wallet';
            return responseError('validation_error', $notify);
        }

        $currency = Currency::enable()->where('currency_code', $wallet->currency_code)->first();

        if (!$currency) {
            $notify[] = 'Invalid gateway';
            return responseError('validation_error', $notify);
        }

        $gate = GatewayCurrency::whereHas('method', function ($gate) {
            $gate->where('status', Status::ENABLE);
        })->where('method_code', $request->method_code)->where('currency', $currency->currency_code)->first();

        if (!$gate) {
            $notify[] = 'Invalid gateway';
            return responseError('validation_error', $notify);
        }

        if ($gate->min_amount / $currency->rate > $request->amount || $gate->max_amount / $currency->rate < $request->amount) {
            $notify[] = 'Please follow deposit limit';
            return responseError('validation_error', $notify);
        }

        $charge      = $gate->fixed_charge + ($request->amount * $gate->percent_charge / 100);
        $payable     = $request->amount + $charge;
        $finalAmount = $payable;

        $data          = new Deposit();
        $data->from_api = Status::YES;
        $data->is_web = $request->is_web ? 1 : 0;
        $data->user_id = $user->id;

        $data->user_type   = $userType;
        $data->wallet_id   = $request->wallet_id;
        $data->currency_id = $currency->id;

        $data->method_code     = $gate->method_code;
        $data->method_currency = strtoupper($gate->currency);
        $data->amount          = $request->amount;
        $data->charge          = $charge;
        $data->rate            = 1;
        $data->final_amount    = $finalAmount;
        $data->btc_amount      = 0;
        $data->btc_wallet      = "";
        $data->success_url     = route(strToLower($data->user_type) . '.deposit.history');
        $data->failed_url      = route(strToLower($data->user_type) . '.deposit.history');
        $data->trx             = getTrx();
        $data->save();

        $notify[] = 'Deposit inserted';
        if ($request->is_web && $data->gateway->code < 1000) {
            $dirName = $data->gateway->alias;
            $new = 'App\\Http\\Controllers\\Gateway\\' . $dirName . '\\ProcessController';

            $gatewayData = $new::process($data);
            $gatewayData = json_decode($gatewayData);

            // for Stripe V3
            if (isset($data->session)) {
                $data->btc_wallet = $gatewayData->session->id;
                $data->save();
            }

            return responseSuccess('deposit_inserted', $notify, [
                'deposit' => $data,
                'gateway_data' => $gatewayData
            ]);
        }

        $data->load('gateway', 'gateway.form');

        return responseSuccess('deposit_inserted', $notify, [
            'deposit' => $data,
            'redirect_url' => route('deposit.app.confirm', encrypt($data->id))
        ]);
    }

    public function manualDepositConfirm(Request $request)
    {
        $track = $request->track;
        $data = Deposit::with('gateway')->where('status', Status::PAYMENT_INITIATE)->where('trx', $track)->first();

        if (!$data) {
            $notify[] = 'Invalid request';
            return responseError('invalid_request', $notify);
        }

        $gatewayCurrency = $data->gatewayCurrency();
        $gateway = $gatewayCurrency->method;
        $formData = $gateway->form->form_data;

        $formProcessor = new FormProcessor();
        $validationRule = $formProcessor->valueValidation($formData);
        $request->validate($validationRule);
        $userData = $formProcessor->processFormData($request, $formData);

        $data->detail = $userData;
        $data->status = Status::PAYMENT_PENDING;
        $data->save();

        $adminNotification            = new AdminNotification();
        $adminNotification->user_id   = $data->user->id;
        $adminNotification->title     = 'Deposit request from ' . $data->user->username;
        $adminNotification->click_url = urlPath('admin.deposit.details', $data->id);
        $adminNotification->save();

        notify($data->user, 'DEPOSIT_REQUEST', [
            'method_name'     => $data->gatewayCurrency()->name,
            'method_currency' => $data->method_currency,
            'method_amount'   => showAmount($data->final_amount, currencyFormat: false),
            'amount'          => showAmount($data->amount, currencyFormat: false),
            'charge'          => showAmount($data->charge, currencyFormat: false),
            'rate'            => showAmount($data->rate, currencyFormat: false),
            'trx'             => $data->trx
        ]);

        $notify[] = ['You have deposit request has been taken'];
        return responseSuccess('deposit_request_taken', $notify);
    }
}
