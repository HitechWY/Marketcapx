<?php

namespace App\Http\Controllers\User;

use App\Models\Wallet;
use App\Models\Deposit;
use App\Models\Voucher;
use App\Constants\Status;
use App\Models\UserAction;
use App\Models\Withdrawal;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Lib\UserActionProcess;
use App\Models\TransactionCharge;
use App\Http\Controllers\Controller;

class VoucherController extends Controller{

    public function userVoucherList(){
        $pageTitle = "Voucher List";
        $vouchers = Voucher::where('user_type', 'USER')->where('user_id', auth()->id())->whereHas('currency')
        ->with('currency')->orderBy('is_used', "ASC")->orderBy('id', "DESC")->paginate(getPaginate());
        return view('Template::user.voucher.list', compact('pageTitle', 'vouchers'));
    }

    public function userVoucher(){
        $pageTitle = "Create Voucher";
        $wallets = Wallet::checkWallet(['user' => auth()->user(), 'type' => 'USER'])->where('balance', '>', 0)->orderBy('balance', 'DESC')->get();
        $voucherCharge = TransactionCharge::where('slug', 'voucher_charge')->first();
        return view('Template::user.voucher.create', compact('pageTitle', 'wallets', 'voucherCharge'));
    }

    public function userVoucherCreate(Request $request){
   
        $request->validate([
            'amount' => 'required|numeric|gt:0',
            'wallet_id' => 'required|integer',
            'otp_type' => otpType(validation:true)
        ]);

        $voucherCharge = TransactionCharge::where('slug', 'voucher_charge')->first();
        if (!$voucherCharge) {
            $notify[] = ['error', 'Sorry! Something went wrong. Please try again'];
            return redirect(route('user.voucher.create'))->withNotify($notify);
        }

        $wallet = Wallet::checkWallet(['user' => auth()->user(), 'type' => 'USER'])->where('id', $request->wallet_id)->first();
        if (!$wallet) {
            $notify[] = ['error', 'Sorry! Wallet not found'];
            return redirect(route('user.voucher.create'))->withNotify($notify);
        }

        $rate = $wallet->currency->rate;
        if ($request->amount < currencyConverter($voucherCharge->min_limit, $rate) || $request->amount > currencyConverter($voucherCharge->max_limit, $rate)) {
            $notify[] = ['error', 'Please Follow the voucher limit'];
            return back()->withNotify($notify)->withInput();
        }

        $myVouchers = Voucher::where('user_type',userGuard()['type'])->where('user_id',auth()->id())->whereDate('created_at',now())->count();

        if ($voucherCharge->voucher_limit != -1 && $myVouchers >= $voucherCharge->voucher_limit) {
            $notify[] = ['error', 'Daily voucher create limit has been exceeded'];
            return back()->withNotify($notify)->withInput();
        }

        $rate = $wallet->currency->rate;
        $fixedCharge = currencyConverter($voucherCharge->fixed_charge, $rate);
        $totalCharge = chargeCalculator($request->amount, $voucherCharge->percent_charge, $fixedCharge);

        $cap = currencyConverter($voucherCharge->cap, $rate);
        if ($voucherCharge->cap != -1 && $totalCharge > $cap) {
            $totalCharge = $cap;
        }

        if ($wallet->currency->currency_type == Status::FIAT_CURRENCY) {
            $precision = 2;
        }else{
            $precision = 8;
        }

        $totalCharge = getAmount($totalCharge, $precision);
        $totalAmount = getAmount($request->amount + $totalCharge, $precision);

        if ($totalAmount > $wallet->balance) {
            $notify[] = ['error', 'Insufficient balance'];
            return back()->withNotify($notify)->withInput();
        }

        $userAction = new UserActionProcess();
        $userAction->user_id = auth()->user()->id;
        $userAction->user_type = userGuard()['type'];
        $userAction->act = 'create_voucher';

        $userAction->details = [
            'wallet_id' => $wallet->id,
            'amount' => $request->amount,
            'totalAmount' => $totalAmount,
            'totalCharge' => $totalCharge,
            'done_route' => route('user.voucher.create.done'),
        ];

        if(count(otpType())){
            $userAction->type = $request->otp_type; 
        }
        $userAction->submit();

        return redirect($userAction->next_route);
    }

    public function userVoucherCreateDone(){
    
        $userAction = UserAction::where('user_id', auth()->user()->id)->where('user_type', 'USER')->where('id', session('action_id'))->first();
        if (!$userAction) {
            $notify[] = ['error', 'Sorry! Unable to process'];
            return to_route('user.voucher.create')->withNotify($notify)->withInput();
        }

        $details = $userAction->details;

        $wallet = Wallet::checkWallet(['user' => auth()->user(), 'type' => 'USER'])->where('id', $details->wallet_id)->first();
        if (!$wallet) {
            $notify[] = ['error', 'Sorry! Wallet not found'];
            return to_route('user.voucher.create')->withNotify($notify)->withInput();
        }

        if(@$userAction->details->totalAmount > $wallet->balance){ 
            $notify[]=['error','Sorry! Insufficient balance in wallet'];
            return to_route('user.voucher.create')->withNotify($notify)->withInput();
        }

        // ATOMICITY + IDEMPOTENCY (Phase 5): debit the wallet and write the
        // voucher + ledger + withdrawal record in one locked transaction, and
        // consume the one-shot UserAction inside the tx so a refreshed/replayed
        // submit cannot mint a second voucher or double-debit. Fields unchanged.
        $authId       = auth()->id();
        $actionId     = session('action_id');
        $insufficient = false;
        $created      = false;

        DB::transaction(function () use ($details, $wallet, $authId, $actionId, &$insufficient, &$created) {
            $action = UserAction::where('id', $actionId)->lockForUpdate()->first();
            if (!$action) {
                return; // already processed -> idempotent no-op
            }

            $w = Wallet::where('id', $wallet->id)->lockForUpdate()->first();
            if ($details->totalAmount > $w->balance) {
                $insufficient = true;
                return;
            }

            $w->balance -=  $details->totalAmount;
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

            $action->delete(); // one-shot: prevent replay
            $created = true;
        });

        if ($insufficient) {
            $notify[] = ['error', 'Sorry! Insufficient balance in wallet'];
            return to_route('user.voucher.create')->withNotify($notify)->withInput();
        }

        if (!$created) {
            $notify[] = ['error', 'Sorry! Unable to process'];
            return to_route('user.voucher.create')->withNotify($notify)->withInput();
        }

        $notify[] = ['success', 'Voucher created successfully'];
        return to_route('user.voucher.list')->withNotify($notify);
    }

    public function userVoucherRedeem(){
        $pageTitle = "Redeem Voucher";
        return view('Template::user.voucher.redeem', compact('pageTitle'));
    }

    public function userVoucherRedeemConfirm(Request $request){
 
        $request->validate([
            'code' => 'required'
        ]);

        $voucher = Voucher::where('voucher_code', $request->code)->where('user_id', '!=', auth()->user()->id)->where('is_used', 0)->first();
        if (!$voucher) {
            $notify[] = ['error', 'Invalid voucher code or This is one of your voucher'];
            return back()->withNotify($notify);
        }

        $user = auth()->user();
        $wallet = Wallet::checkWallet(['user' => auth()->user(), 'type' => 'USER'])->where('currency_id', $voucher->currency_id)->first();
        if (!$wallet) {
            $wallet = createWallet($voucher->currency, $user);
        }

        // ATOMICITY + IDEMPOTENCY (Phase 5): the original checked is_used=0 and
        // only set is_used=1 AFTER crediting, with no lock/transaction, so two
        // concurrent redeems of the same code could both credit (double-spend).
        // Lock the voucher row, re-check unused under the lock, mark it used, and
        // credit the wallet -- all in one transaction. Deposit/ledger fields
        // unchanged.
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
            $notify[] = ['error', 'Invalid voucher code or This is one of your voucher'];
            return back()->withNotify($notify);
        }

        $notify[] = ['success', getAmount($voucher->amount) . ' ' . $depositRef->method_currency . ' has been added to your wallet'];
        return back()->withNotify($notify);
    }

    public function userVoucherRedeemLog(){
        $pageTitle = "Voucher Redeem Log";
        $logs = Voucher::where('redeemer_id', auth()->id())->where('is_used', 1)->whereHas('currency')->paginate(getPaginate());
        return view('Template::user.voucher.redeem_log', compact('pageTitle', 'logs'));
    }
}
