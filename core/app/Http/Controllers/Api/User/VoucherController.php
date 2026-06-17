<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Models\Deposit;
use App\Models\Transaction;
use App\Models\TransactionCharge;
use App\Models\UserAction;
use App\Lib\Api\UserActionProcess;
use App\Models\Voucher;
use App\Models\Wallet;
use App\Models\Withdrawal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class VoucherController extends Controller{

    public function userVoucherList(){
        $notify[] = "Voucher List";
        $vouchers = Voucher::where('user_type', 'USER')->where('user_id', auth()->id())->whereHas('currency')->with('currency')->orderBy('is_used', "ASC")->apiQuery();

        return response()->json([
            'remark'=>'voucher_list',
            'status'=>'success',
            'message'=>['success'=>$notify],
            'data'=>[
                'vouchers'=>$vouchers,
            ]
        ]);
    }

    public function userVoucher(){ 
        $notify[] = "Create Voucher";
        $voucherCharge = TransactionCharge::where('slug', 'voucher_charge')->first();
        
        $wallets = Wallet::checkWallet(['user' => auth()->user(), 'type' => 'USER'])->where('balance', '>', 0)->orderBy('balance', 'DESC')->get();
        $wallets = $this->withVoucherLimit($wallets, $voucherCharge);

        return response()->json([
            'remark'=>'create_voucher',
            'status'=>'success',
            'message'=>['success'=>$notify],

            'data'=>[
                'otp_type'=>otpType(),
                'wallets'=>$wallets,
                'voucher_charge'=>$voucherCharge,
            ]
        ]);
    }

    public function withVoucherLimit($wallets, $moneyOutCharge){
        foreach($wallets ?? [] as $wallet){ 
        
            $rate = $wallet->currency->rate;
            
            $min = $moneyOutCharge->min_limit/$rate;
            $max = $moneyOutCharge->max_limit/$rate;
    
            $wallet->currency->voucher_min_limit = $min;
            $wallet->currency->voucher_max_limit = $max;
        }
    
        return $wallets;
    }

    public function userVoucherCreate(Request $request){
       
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|gt:0',
            'wallet_id' => 'required|integer',
            'otp_type' => otpType(validation:true)
        ]);
      
        if ($validator->fails()) {
            return response()->json([
                'remark'=>'validation_error',
                'status'=>'error',
                'message'=>['error'=>$validator->errors()->all()],
            ]);
        }

        $voucherCharge = TransactionCharge::where('slug', 'voucher_charge')->first();
        if (!$voucherCharge) {
            return response()->json([
                'remark'=>'validation_error',
                'status'=>'error',
                'message'=>['error'=>['Sorry, Transaction charge not found']],
            ]);
        }

        $wallet = Wallet::checkWallet(['user' => auth()->user(), 'type' => 'USER'])->where('id', $request->wallet_id)->first();
        if (!$wallet) {
            return response()->json([
                'remark'=>'validation_error',
                'status'=>'error',
                'message'=>['error'=>['Sorry! Wallet not found']],
            ]);
        }

        $rate = $wallet->currency->rate;
        if ($request->amount < currencyConverter($voucherCharge->min_limit, $rate) || $request->amount > currencyConverter($voucherCharge->max_limit, $rate)) {
            return response()->json([
                'remark'=>'validation_error',
                'status'=>'error',
                'message'=>['error'=>['Please Follow the voucher limit']],
            ]);
        }

        $myVouchers = Voucher::where('user_type', 'USER')->where('user_id',auth()->id())->whereDate('created_at',now())->count();
        if ($voucherCharge->voucher_limit != -1 && $myVouchers >= $voucherCharge->voucher_limit) {
            return response()->json([
                'remark'=>'validation_error',
                'status'=>'error',
                'message'=>['error'=>['Daily voucher create limit has been exceeded']],
            ]);
        }

        $rate = $wallet->currency->rate;
        $fixedCharge = currencyConverter($voucherCharge->fixed_charge, $rate);
        $totalCharge = chargeCalculator($request->amount, $voucherCharge->percent_charge, $fixedCharge);

        $cap = currencyConverter($voucherCharge->cap, $rate);
        if ($voucherCharge->cap != -1 && $totalCharge > $cap) {
            $totalCharge = $cap;
        }

        if ($wallet->currency->currency_type == 1) {
            $precision = 2;
        }else{
            $precision = 8;
        }

        $totalCharge = getAmount($totalCharge, $precision);
        $totalAmount = getAmount($request->amount + $totalCharge, $precision);

        if ($totalAmount > $wallet->balance) {
            return response()->json([
                'remark'=>'validation_error',
                'status'=>'error',
                'message'=>['error'=>['Insufficient balance']],
            ]);
        }

        $userAction = new UserActionProcess();
        $userAction->user_id = auth()->user()->id;
        $userAction->user_type = 'USER';
        $userAction->act = 'create_voucher';

        $userAction->details = [
            'wallet_id' => $wallet->id,
            'amount' => $request->amount,
            'totalAmount' => $totalAmount,
            'totalCharge' => $totalCharge,
            'done_route' => 'api.voucher.create.done',
        ];

        if(count(otpType())){
            $userAction->type = $request->otp_type; 
        }
        $userAction->submit();
        $actionId = $userAction->action_id;
    
        if($userAction->verify_api_otp){
            return response()->json([
                'remark'=>'verify_otp',
                'status'=>'success',
                'message'=>['success'=>['Verify otp']],
                'data'=>[
                    'action_id'=>$actionId,
                ]
            ]);
        }
      
        return callApiMethod($userAction->next_route, $actionId);
    }

    public function userVoucherCreateDone($actionId){
      
        $userAction = UserAction::where('user_id', auth()->user()->id)->where('user_type', 'USER')->where('is_api', 1)->where('used', 0)->where('id', $actionId)->first();
        if (!$userAction) {
            return response()->json([
                'remark'=>'validation_error',
                'status'=>'error',
                'message'=>['error'=>['Sorry! Unable to process']],
            ]);
        }

        $details = $userAction->details;

        $wallet = Wallet::checkWallet(['user' => auth()->user(), 'type' => 'USER'])->where('id', $details->wallet_id)->first();
        if (!$wallet) {
            return response()->json([
                'remark'=>'validation_error',
                'status'=>'error',
                'message'=>['error'=>['Sorry! Wallet not found']],
            ]);
        }

        if(@$userAction->details->totalAmount > $wallet->balance){ 
            return response()->json([
                'remark'=>'validation_error',
                'status'=>'error',
                'message'=>['error'=>['Sorry! Insufficient balance in wallet']],
            ]);
        }

        // ATOMICITY + IDEMPOTENCY (Phase 5): debit + voucher + ledger + withdrawal
        // record in one locked transaction; flip the one-shot UserAction `used`
        // flag inside the tx so a replayed call cannot mint a second voucher or
        // double-debit. Fields unchanged.
        $authId       = auth()->id();
        $insufficient = false;
        $created      = false;

        DB::transaction(function () use ($details, $wallet, $authId, $actionId, &$insufficient, &$created) {
            $action = UserAction::where('id', $actionId)->where('used', 0)->lockForUpdate()->first();
            if (!$action) {
                return; // already processed -> idempotent no-op
            }

            $w = Wallet::where('id', $wallet->id)->lockForUpdate()->first();
            if ($details->totalAmount > $w->balance) {
                $insufficient = true;
                return;
            }

            $action->used = 1;
            $action->save();

            $w->balance -= $details->totalAmount;
            $w->save();

            $voucher = new Voucher();
            $voucher->user_id = $authId;
            $voucher->user_type =  'USER';
            $voucher->currency_id = $w->currency_id;
            $voucher->amount = $details->amount;
            $voucher->voucher_code = getVoucher();
            $voucher->save();

            $trx = new Transaction();
            $trx->user_id = $authId;
            $trx->user_type = 'USER';
            $trx->wallet_id = $w->id;
            $trx->currency_id = $w->currency_id;
            $trx->before_charge = $details->amount;
            $trx->amount = $details->totalAmount;
            $trx->post_balance =  $w->balance;
            $trx->charge =  $details->totalCharge;
            $trx->charge_type = '+';
            $trx->trx_type = '-';
            $trx->remark = 'create_voucher';
            $trx->details = 'Voucher created successfully';
            $trx->trx = getTrx();
            $trx->save();

            $withdraw = new Withdrawal();
            $withdraw->method_id = 0;
            $withdraw->user_id = $authId;
            $withdraw->user_type = 'USER';
            $withdraw->amount = $details->totalAmount;
            $withdraw->rate = $w->currency->rate;
            $withdraw->currency_id = $w->currency_id;
            $withdraw->wallet_id = $w->id;
            $withdraw->currency = $w->currency->currency_code;
            $withdraw->charge = $details->totalCharge;
            $withdraw->final_amount = $details->amount;
            $withdraw->after_charge = $withdraw->final_amount;
            $withdraw->withdraw_information = null;
            $withdraw->trx = getTrx();
            $withdraw->status = 1;
            $withdraw->save();

            $created = true;
        });

        if ($insufficient) {
            return response()->json([
                'remark'=>'validation_error',
                'status'=>'error',
                'message'=>['error'=>['Sorry! Insufficient balance in wallet']],
            ]);
        }

        if (!$created) {
            return response()->json([
                'remark'=>'validation_error',
                'status'=>'error',
                'message'=>['error'=>['Sorry! Unable to process']],
            ]);
        }

        return response()->json([
            'remark'=>'voucher_create_done',
            'status'=>'success',
            'message'=>['success'=>['Voucher created successfully']],
        ]);
    }

    public function userVoucherRedeemConfirm(Request $request){
        
        $request->validate([
            'code' => 'required'
        ]);
  
        $voucher = Voucher::where('voucher_code', $request->code)->where('user_id', '!=', auth()->user()->id)->where('is_used', 0)->first(); 
        if (!$voucher) {
            return response()->json([
                'remark'=>'validation_error',
                'status'=>'error',
                'message'=>['error'=>['Invalid voucher code or This is one of your voucher']],
            ]);
        }
   
        $user = auth()->user();
        
        $wallet = Wallet::checkWallet(['user' => auth()->user(), 'type' => 'USER'])->where('currency_id', $voucher->currency_id)->first();
        if (!$wallet) {
            return response()->json([
                'remark'=>'validation_error',
                'status'=>'error',
                'message'=>['error'=>['Wallet not found']],
            ]);
        }

        // ATOMICITY + IDEMPOTENCY (Phase 5): lock the voucher, re-check unused
        // under the lock, mark it used, and credit the wallet -- all in one
        // transaction. The original set is_used=1 only after crediting with no
        // lock, allowing concurrent double-redeem. Deposit/ledger fields unchanged.
        $userId      = $user->id;
        $walletId    = $wallet->id;
        $alreadyUsed = false;
        $depositRef  = null;

        DB::transaction(function () use ($voucher, $userId, $walletId, &$alreadyUsed, &$depositRef) {
            $v = Voucher::where('id', $voucher->id)->where('is_used', 0)->lockForUpdate()->first();
            if (!$v) {
                $alreadyUsed = true;
                return; // already redeemed (or lost a race) -> no credit
            }

            $v->is_used     = 1;
            $v->redeemer_id = $userId;
            $v->save();

            $w = Wallet::where('id', $walletId)->lockForUpdate()->first();

            $deposit = new Deposit();
            $deposit->user_id = $userId;
            $deposit->user_type = 'USER';
            $deposit->method_code = 0;
            $deposit->amount = $v->amount;
            $deposit->wallet_id = $w->id;
            $deposit->currency_id = $w->currency_id;
            $deposit->method_currency = $w->currency->currency_code;
            $deposit->charge = 0;
            $deposit->rate = $w->currency->rate;
            $deposit->final_amount = $v->amount;
            $deposit->btc_amount = 0;
            $deposit->btc_wallet = "";
            $deposit->trx = getTrx();
            $deposit->status = 1;
            $deposit->save();

            $w->balance += $v->amount;
            $w->save();

            $trx = new Transaction();
            $trx->user_id = $userId;
            $trx->user_type = 'USER';
            $trx->wallet_id = $w->id;
            $trx->currency_id = $w->currency_id;
            $trx->before_charge = $v->amount;
            $trx->amount = $v->amount;
            $trx->post_balance = $w->balance;
            $trx->charge =  0;
            $trx->charge_type = '+';
            $trx->trx_type = '+';
            $trx->remark = 'redeem_voucher';
            $trx->details = 'Redeemed Voucher ';
            $trx->trx = $deposit->trx;
            $trx->save();

            $depositRef = $deposit;
        });

        if ($alreadyUsed) {
            return response()->json([
                'remark'=>'validation_error',
                'status'=>'error',
                'message'=>['error'=>['Invalid voucher code or This is one of your voucher']],
            ]);
        }

        return response()->json([
            'remark'=>'voucher_redeem_done',
            'status'=>'success',
            'message'=>['success'=>[getAmount($voucher->amount) . ' ' . $depositRef->method_currency . ' has been added to your wallet']],
        ]);
    }

    public function userVoucherRedeemLog(){ 
        $notify[] = "Voucher Redeem Log";
        $logs = Voucher::where('redeemer_id', auth()->id())->where('is_used', 1)->whereHas('currency')->apiQuery();

        return response()->json([
            'remark'=>'check_agent',
            'status'=>'success',
            'message'=>['success'=>$notify],
            'data'=>[
                'logs'=>$logs,
            ]
        ]);
    }
}
