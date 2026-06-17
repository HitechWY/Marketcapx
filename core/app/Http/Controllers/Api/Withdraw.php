<?php

namespace App\Http\Controllers\Api;

use App\Constants\Status;
use App\Http\Controllers\Api\Common;
use App\Lib\Api\UserActionProcess;
use App\Lib\FormProcessor;
use App\Models\AdminNotification;
use App\Models\Currency;
use App\Models\Transaction;
use App\Models\UserAction;
use App\Models\UserWithdrawMethod;
use App\Models\Wallet;
use App\Models\Withdrawal;
use App\Models\WithdrawMethod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

trait Withdraw
{
    use Common;

    public function withdrawPreview($trx)
    {
        $withdraw = Withdrawal::with('method', 'user')->where('trx', $trx)->where('status', Status::PAYMENT_INITIATE)->orderBy('id', 'desc')->first();
        if (!$withdraw) {
            $notify[] = 'Invalid request';
            return responseError('validation_error', $notify);
        }

        $notify[] = 'Withdraw Preview';
        return responseSuccess('withdraw_preview', $notify, [
            'otp_type'          => otpType(),
            'withdraw'          => $withdraw,
            'remaining_balance' => ($withdraw->wallet->balance - $withdraw->amount),
        ]);
    }

    public function withdrawLog()
    {
        $notify[]  = "Withdraw Log";
        $withdraws = $this->guard()['user']->withdrawals()->searchable(['trx'])->with(['method'])->with('curr')->apiQuery();

        return responseSuccess('withdraw_log', $notify, [
            'withdraws' => $withdraws,
        ]);
    }

    public function withdrawMethods()
    {
        $notify[] = 'Withdraw Methods';

        $user     = $this->guard()['user'];
        $guard    = $this->guard()['guard'];
        $userType = $this->guard()['user_type'];

        $userMethods = UserWithdrawMethod::where('user_type', $userType)->where('user_id', $user->id)
            ->whereHas('withdrawMethod', function ($query) use ($guard) {
                $query->where('status', Status::ENABLE)->whereJsonContains('user_guards', "$guard");
            })->whereHas('withdrawMethod')->with('withdrawMethod', 'currency')
            ->apiQuery();

        return responseSuccess('withdraw_methods', $notify, [
            'methods' => $userMethods,
        ]);
    }

    public function addWithdrawMethodPage()
    {
        $notify[] = "Add Withdraw Method";

        $guard      = $this->guard()['guard'];
        $currencies = Currency::pluck('id', 'currency_code');

        $withdrawMethod = WithdrawMethod::whereJsonContains('user_guards', "$guard")->where('status', 1)->with('form')->get();

        return responseSuccess('add_withdraw_method', $notify, [
            'withdraw_method' => $withdrawMethod,
            'currencies'      => $currencies,
        ]);
    }

    public function addWithdrawMethod(Request $request)
    {
        $rules       = ['name' => 'required', 'method_id' => 'required', 'currency_id' => 'required'];
        $storeHelper = $this->storeHelper($request, $rules);

        if (@$storeHelper['status'] == 'error') {
            return responseError('validation_error', @$storeHelper['errors']);
        }

        $validator = Validator::make(
            $request->all(),
            $storeHelper['rules']
        );

        if ($validator->fails()) {
            return responseError('validation_error', $validator->errors());
        }

        $userMethod              = new UserWithdrawMethod();
        $userMethod->name        = $request->name;
        $userMethod->user_id     = $this->guard()['user']->id;
        $userMethod->user_type   = $this->guard()['user_type'];
        $userMethod->method_id   = $request->method_id;
        $userMethod->currency_id = $request->currency_id;
        $userMethod->user_data   = $storeHelper['user_data'];
        $userMethod->save();

        $notify[] = 'Add withdraw method';
        return responseSuccess('add_withdraw_method', $notify);
    }

    protected function storeHelper($request, $rules, $isUpdate = false)
    {

        $guard = $this->guard()['guard'];

        $withdrawMethod = WithdrawMethod::where('id', $request->method_id)->whereJsonContains('user_guards', "$guard")->where('status', 1)->first();
        if (!$withdrawMethod) {
            $notify[] = 'Withdraw method not found';
            return responseError('validation_error', $notify);
        }

        $formData = $withdrawMethod->form->form_data;

        $formProcessor  = new FormProcessor();
        $validationRule = $formProcessor->valueValidation($formData);

        $validator = Validator::make($request->all(), $validationRule);
        if ($validator->fails()) {
            return responseError('validation_error', $validator->errors());
        }

        $userData = $formProcessor->processFormData($request, $formData);
        return ['user_data' => $userData, 'rules' => $rules];
    }

    public function editWithdrawMethod($id)
    {

        $notify[] = 'Withdraw Method Edit';

        $user     = $this->guard()['user'];
        $guard    = $this->guard()['guard'];
        $userType = $this->guard()['user_type'];

        $userMethod = UserWithdrawMethod::where('user_type', $userType)->where('user_id', $user->id)
            ->whereHas('withdrawMethod', function ($query) use ($guard) {
                $query->where('status', 1)->whereJsonContains('user_guards', "$guard");
            })->where('id', $id)
            ->first();

        if (!$userMethod) {
            $notify[] = 'Withdraw method not found';
            return responseError('validation_error', $notify);
        }

        $currencies = Currency::pluck('id', 'currency_code');

        return responseSuccess('edit_withdraw_method', $notify, [
            'withdraw_method' => $userMethod,
            'currencies'      => $currencies,
            'form'            => $userMethod->withdrawMethod->form,
            'file_path'       => asset(getFilePath('verify')),
        ]);
    }

    public function withdrawMethodUpdate(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'id'        => 'required',
            'method_id' => 'required',
        ]);

        if ($validator->fails()) {
            return responseError('validation_error', $validator->errors());
        }

        $user     = $this->guard()['user'];
        $userType = $this->guard()['user_type'];

        $userMethod = UserWithdrawMethod::where('user_type', $userType)->where('user_id', $user->id)->where('id', $request->id)->first();
        if (!$userMethod) {
            $notify[] = 'Withdraw method not found';
            return responseError('validation_error', $notify);
        }

        $rules       = ['name' => 'required'];
        $storeHelper = $this->storeHelper($request, $rules, true);

        if (@$storeHelper['status'] == 'error') {
            return responseError('validation_error', @$storeHelper['errors']);
        }

        $validator = Validator::make(
            $request->all(),
            $storeHelper['rules']
        );

        if ($validator->fails()) {
            return responseError('validation_error', $validator->errors());
        }

        $userMethod->name      = $request->name;
        $userMethod->user_id   = $user->id;
        $userMethod->user_type = $userType;
        $userMethod->user_data = $storeHelper['user_data'];
        $userMethod->status    = $request->status ? 1 : 0;
        $userMethod->save();

        $notify[] = 'Update withdraw method';
        return responseSuccess('update_withdraw_method', $notify);
    }

    public function withdrawMoney(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'method_id'      => 'required',
            'user_method_id' => 'required',
            'amount'         => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return responseError('validation_error', $validator->errors());
        }

        $user     = $this->guard()['user'];
        $guard    = $this->guard()['guard'];
        $userType = $this->guard()['user_type'];

        $method = WithdrawMethod::where('id', $request->method_id)->where('status', Status::ENABLE)->whereJsonContains('user_guards', "$guard")->first();
        if (!$method) {
            $notify[] = 'Withdraw method not found';
            return responseError('validation_error', $notify);
        }

        $userMethod = UserWithdrawMethod::where('user_type', $userType)->where('user_id', $user->id)
            ->whereHas('withdrawMethod', function ($query) use ($guard) {
                $query->where('status', Status::ENABLE)->whereJsonContains('user_guards', "$guard");
            })
            ->find($request->user_method_id);

        if (!$userMethod) {
            $notify[] = 'User withdraw method not found';
            return responseError('validation_error', $notify);
        }

        $currency = Currency::find($userMethod->currency_id);
        if (!$currency) {
            $notify[] = 'Currency not found';
            return responseError('validation_error', $notify);
        }

        $wallet = Wallet::hasCurrency()->where('user_type', $userType)->where('user_id', $user->id)->where('currency_id', $currency->id)->first();
        if (!$wallet) {
            $notify[] = 'Wallet not found';
            return responseError('validation_error', $notify);
        }

        if ($method->min_limit / $currency->rate > $request->amount || $method->max_limit / $currency->rate < $request->amount) {
            $notify[] = 'Please follow the limits';
            return responseError('validation_error', $notify);
        }

        if ($request->amount > $wallet->balance) {
            $notify[] = 'You do not have sufficient balance for withdraw';
            return responseError('validation_error', $notify);
        }

        $charge      = ($method->fixed_charge / $currency->rate) + ($request->amount * $method->percent_charge / 100);
        $finalAmount = $request->amount - $charge;

        $withdraw                       = new Withdrawal();
        $withdraw->method_id            = $method->id;
        $withdraw->user_id              = $user->id;
        $withdraw->user_type            = $userType;
        $withdraw->amount               = $request->amount;
        $withdraw->rate                 = $currency->rate;
        $withdraw->currency_id          = $currency->id;
        $withdraw->wallet_id            = $wallet->id;
        $withdraw->currency             = $currency->currency_code;
        $withdraw->charge               = $charge;
        $withdraw->final_amount         = $finalAmount;
        $withdraw->after_charge         = $finalAmount;
        $withdraw->withdraw_information = $userMethod->user_data;
        $withdraw->trx                  = getTrx();
        $withdraw->save();

        $notify[] = 'Withdraw money';
        return responseSuccess('withdraw_money', $notify, [
            'trx' => $withdraw->trx,
        ]);
    }

    public function withdrawSubmit(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'otp_type' => otpType(validation: true),
            'trx'      => 'required',
        ]);

        if ($validator->fails()) {
            return responseError('validation_error', $validator->errors());
        }

        $user     = $this->guard()['user'];
        $userType = $this->guard()['user_type'];

        $userAction            = new UserActionProcess();
        $userAction->user_id   = $user->id;
        $userAction->user_type = $userType;
        $userAction->act       = 'withdraw_money';

        if ($userType == 'USER') {
            $doneRoute = 'api.withdraw.submit.done';
        } elseif ($userType == 'AGENT') {
            $doneRoute = 'api.agent.withdraw.submit.done';
        } elseif ($userType == 'MERCHANT') {
            $doneRoute = 'api.merchant.withdraw.submit.done';
        }

        $userAction->details = [
            'trx'        => $request->trx,
            'done_route' => $doneRoute,
        ];

        if (count(otpType())) {
            $userAction->type = $request->otp_type;
        }

        $userAction->submit();
        $actionId = $userAction->action_id;

        if ($userAction->verify_api_otp) {
            $notify[] = 'Verify otp';
            return responseSuccess('verify_otp', $notify, [
                'action_id' => $actionId,
            ]);
        }

        return callApiMethod($userAction->next_route, $actionId);
    }

    public function withdrawSubmitDone($actionId)
    {

        $user     = $this->guard()['user'];
        $userType = $this->guard()['user_type'];

        $userAction = UserAction::where('user_id', $user->id)->where('user_type', $userType)->where('is_api', Status::YES)->where('used', Status::NO)->where('id', $actionId)->first();
        if (!$userAction) {
            $notify[] = 'Sorry! Unable to process';
            return responseError('validation_error', $notify);
        }

        $withdraw = Withdrawal::with('method', strtolower($userType))->where('trx', $userAction->details->trx)->where('status', 0)->orderBy('id', 'desc')->first();
        if (!$withdraw) {
            $notify[] = 'Invalid request';
            return responseError('validation_error', $notify);
        }

        $wallet = Wallet::checkWallet(['user' => $user, 'type' => $userType])->find($withdraw->wallet_id);
        if (!$wallet) {
            $notify[] = 'Wallet not found';
            return responseError('validation_error', $notify);
        }

        if ($withdraw->amount > $wallet->balance) {
            $notify[] = 'You do not have sufficient balance for withdraw';
            return responseError('validation_error', $notify);
        }

        $withdraw->status = Status::PAYMENT_PENDING;
        $withdraw->save();

        $userAction->used = Status::YES;
        $userAction->save();

        $wallet->balance -= $withdraw->amount;
        $wallet->save();

        $transaction                = new Transaction();
        $transaction->user_id       = $withdraw->user_id;
        $transaction->user_type     = $withdraw->user_type;
        $transaction->wallet_id     = $wallet->id;
        $transaction->currency_id   = $withdraw->currency_id;
        $transaction->before_charge = $withdraw->amount;
        $transaction->amount        = $withdraw->amount;
        $transaction->post_balance  = $wallet->balance;
        $transaction->charge        = 0;
        $transaction->charge_type   = '+';
        $transaction->trx_type      = '-';
        $transaction->remark        = 'withdraw';
        $transaction->details       = 'Money withdrawal';
        $transaction->trx           = $withdraw->trx;
        $transaction->save();

        $adminNotification            = new AdminNotification();
        $adminNotification->user_id   = $user->id;
        $adminNotification->user_type = $withdraw->user_type;
        $adminNotification->title     = 'New withdraw request from ' . $user->username;
        $adminNotification->click_url = urlPath('admin.withdraw.data.details', $withdraw->id);
        $adminNotification->save();

        $general = gs();

        notify($user, 'WITHDRAW_REQUEST', [
            'method_name'     => $withdraw->method->name,
            'method_currency' => $wallet->currency->currency_code,
            'method_amount'   => showAmount($withdraw->final_amount, $general->currency, currencyFormat: false),
            'amount'          => showAmount($withdraw->amount, $general->currency, currencyFormat: false),
            'charge'          => showAmount($withdraw->charge, $general->currency, currencyFormat: false),
            'currency'        => $wallet->currency->currency_code,
            'trx'             => $withdraw->trx,
            'post_balance'    => showAmount($wallet->balance, $general->currency, currencyFormat: false),
        ]);

        $notify[] = 'Withdraw request sent successfully';
        return responseSuccess('withdraw_money_done', $notify);
    }
}
