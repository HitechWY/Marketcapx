<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Constants\Status;
use App\Lib\UserActionProcess;
use App\Models\Escrow;
use App\Models\Milestone;
use App\Models\Transaction;
use App\Models\UserAction;
use App\Models\Wallet;
use Illuminate\Http\Request;

class MilestoneController extends Controller
{

    public function milestones($id)
    {
        $pageTitle   = "Escrow Milestones";
        $escrow      = Escrow::checkUser()->findOrFail($id);
        $milestones  = Milestone::where('escrow_id', $id)->orderBy('id', 'desc')->paginate(getPaginate());
        $totalAmount = $escrow->milestones()->sum('amount');
        $restAmount  = $escrow->amount + $escrow->buyer_charge - $totalAmount;
        $wallet = Wallet::where('user_id', auth()->id())->where('currency_id', $escrow->currency_id)->where('user_type', 'USER')->first();

        if (!$wallet) {
            createWallet($escrow->currency_id, auth()->user());
        }

        return view('Template::user.escrow.milestones', compact('pageTitle', 'escrow', 'milestones', 'restAmount', 'wallet'));
    }

    public function createMilestone(Request $request, $id)
    {
        $escrow      = Escrow::where('buyer_id', auth()->user()->id)->accepted()->findOrFail($id);
        $totalAmount = $escrow->milestones()->sum('amount');
        $restAmount  = $escrow->amount + $escrow->buyer_charge - $totalAmount;

        $request->validate([
            'amount' => 'required|numeric|gt:0|lte:' . $restAmount,
            'note'   => 'required|max:255',
        ]);

        $totalMileStoneCount = $escrow->milestones->count();

        if ($totalMileStoneCount == 0 && $escrow->buyer_charge > $request->amount) {
            $notify[] = ['error', 'First milestone amount must be equal or greater than ' . showAmount($escrow->buyer_charge, currencyFormat: false) . ' ' . $escrow->currency->currency_code];
            return back()->withNotify($notify);
        }

        $milestone            = new Milestone();
        $milestone->escrow_id = $escrow->id;
        $milestone->user_id   = auth()->id();
        $milestone->amount    = $request->amount;
        $milestone->note      = $request->note;
        $milestone->save();

        $notify[] = ['success', 'Milestone created successfully'];
        return back()->withNotify($notify);
    }

    public function payMilestone(Request $request, $id)
    {
        $request->validate([
            'otp_type'  => otpType(validation: true),
        ]);

        $milestone = Milestone::unFunded()->whereHas('escrow', function ($query) {
            $query->where('buyer_id', auth()->user()->id);
        })->find($id);

        if (!$milestone) {
            $notify[] = ['error', 'Milestone not found'];
            return back()->withNotify($notify);
        }

        if ($milestone->escrow->status != Status::ESCROW_ACCEPTED) {
            $notify[] = ['error', 'You can only pay for a milestone when its escrow status is accepted'];
            return back()->withNotify($notify);
        }

        $paidMilestoneCount = Milestone::funded()->where('escrow_id', $milestone->escrow_id)->count();

        if ($paidMilestoneCount == 0 && $milestone->escrow->buyer_charge > $milestone->amount) {
            $notify[] = ['error', 'First payment milestone amount must be equal or greater than ' . showAmount($milestone->escrow->buyer_charge, currencyFormat: false) . ' ' . $milestone->escrow->currency->currency_code];
            return back()->withNotify($notify);
        }

        $user = auth()->user();

        $wallet = Wallet::where('user_id', $user->id)->where('currency_id', $milestone->escrow->currency_id)->where('user_type', 'USER')->first();

        if ($wallet->balance < $milestone->amount) {
            $notify[] = ['error', 'You have no sufficient balance on your wallet'];
            return back()->withNotify($notify);
        }

        $userAction            = new UserActionProcess();
        $userAction->user_id   = $user->id;
        $userAction->user_type = userGuard()['type'];
        $userAction->act       = 'milestone_pay';

        $userAction->details = [
            'wallet_id'   => $wallet->id,
            'milestone_id' => $milestone->id,
            'done_route'  => route('user.escrow.milestone.pay.done'),
        ];

        if (count(otpType())) {
            $userAction->type = $request->otp_type;
        }

        $userAction->submit();

        return redirect($userAction->next_route);
    }

    public function payMilestoneDone()
    {
        $user = auth()->user();

        $userAction = UserAction::where('user_id', $user->id)->where('user_type', 'USER')->where('id', session('action_id'))->first();
        if (!$userAction) {
            $notify[] = ['error', 'Sorry! Unable to process'];
            return to_route('user.transfer')->withNotify($notify)->withInput();
        }

        $details = $userAction->details;

        $wallet = Wallet::checkWallet(['user' => $user, 'type' => 'USER'])->find($details->wallet_id);
        $milestone = Milestone::where('user_id', $user->id)->where('id', $details->milestone_id)->first();

        $wallet->balance -= $milestone->amount;
        $wallet->save();

        $paidMilestoneCount = Milestone::funded()->where('escrow_id', $milestone->escrow_id)->count();

        $transaction               = new Transaction();
        $transaction->user_id       = auth()->id();
        $transaction->user_type     = 'USER';
        $transaction->wallet_id     = $wallet->id;
        $transaction->currency_id   = $wallet->currency_id;

        if ($paidMilestoneCount == 0) {
            $transaction->before_charge = $milestone->amount - $milestone->escrow->buyer_charge;
            $transaction->charge = $milestone->escrow->buyer_charge;
        } else {
            $transaction->before_charge = $milestone->amount;
            $transaction->charge = 0;
        }

        $transaction->amount       = $milestone->amount;
        $transaction->post_balance = $wallet->balance;
        $transaction->trx_type     = '-';
        $transaction->charge_type = '+';
        $transaction->remark       = "milestone_paid";
        $transaction->details      = 'Milestone amount paid';
        $transaction->trx          = getTrx();
        $transaction->save();

        $milestone->payment_status = Status::MILESTONE_FUNDED;
        $milestone->save();

        $escrow               = $milestone->escrow;
        $escrow->paid_amount += $milestone->amount;
        $escrow->save();

        $notify[] = ['success', 'Milestone amount paid successfully'];
        return to_route('user.escrow.milestone.index', $escrow->id)->withNotify($notify);
    }
}
