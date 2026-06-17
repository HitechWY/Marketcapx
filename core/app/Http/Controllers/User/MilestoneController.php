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
use Illuminate\Support\Facades\DB;

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

        if (!$wallet || !$milestone) {
            $notify[] = ['error', 'Sorry! Unable to process'];
            return to_route('user.transfer')->withNotify($notify)->withInput();
        }

        // ATOMICITY + IDEMPOTENCY (deferred money-flow wave; mirrors the Phase 5
        // money-out fix): the milestone debit ran with no lock, no balance
        // re-check, and the one-shot UserAction was never consumed, so a refresh/
        // replay or concurrent pay could debit twice and double-fund the escrow.
        // Lock the milestone (payment_status==unfunded) inside the tx, lock the
        // wallet + re-check balance, lock the escrow row before bumping
        // paid_amount, then debit, write the ledger row, fund the milestone, and
        // consume the one-shot UserAction. Charge/amount logic is unchanged.
        $authId        = auth()->id();
        $actionId      = session('action_id');
        $walletId      = $wallet->id;
        $milestoneId   = $milestone->id;
        $escrowId      = $milestone->escrow_id;
        $alreadyFunded = false;
        $insufficient  = false;
        $escrowRef     = null;

        DB::transaction(function () use (
            $authId, $actionId, $walletId, $milestoneId, $escrowId,
            &$alreadyFunded, &$insufficient, &$escrowRef
        ) {
            $action = UserAction::where('id', $actionId)->lockForUpdate()->first();
            if (!$action) {
                $alreadyFunded = true;
                return; // replay -> idempotent no-op
            }

            $lockedMilestone = Milestone::where('id', $milestoneId)->where('payment_status', Status::MILESTONE_UNFUNDED)->lockForUpdate()->first();
            if (!$lockedMilestone) {
                $alreadyFunded = true;
                return; // already funded by a concurrent request
            }

            $lockedWallet = Wallet::where('id', $walletId)->lockForUpdate()->first();
            if (!$lockedWallet || $lockedWallet->balance < $lockedMilestone->amount) {
                $insufficient = true;
                return;
            }

            $lockedEscrow = Escrow::where('id', $escrowId)->lockForUpdate()->first();

            $lockedWallet->balance -= $lockedMilestone->amount;
            $lockedWallet->save();

            $paidMilestoneCount = Milestone::funded()->where('escrow_id', $escrowId)->count();

            $transaction               = new Transaction();
            $transaction->user_id       = $authId;
            $transaction->user_type     = 'USER';
            $transaction->wallet_id     = $lockedWallet->id;
            $transaction->currency_id   = $lockedWallet->currency_id;

            if ($paidMilestoneCount == 0) {
                $transaction->before_charge = $lockedMilestone->amount - $lockedEscrow->buyer_charge;
                $transaction->charge = $lockedEscrow->buyer_charge;
            } else {
                $transaction->before_charge = $lockedMilestone->amount;
                $transaction->charge = 0;
            }

            $transaction->amount       = $lockedMilestone->amount;
            $transaction->post_balance = $lockedWallet->balance;
            $transaction->trx_type     = '-';
            $transaction->charge_type = '+';
            $transaction->remark       = "milestone_paid";
            $transaction->details      = 'Milestone amount paid';
            $transaction->trx          = getTrx();
            $transaction->save();

            $lockedMilestone->payment_status = Status::MILESTONE_FUNDED;
            $lockedMilestone->save();

            $lockedEscrow->paid_amount += $lockedMilestone->amount;
            $lockedEscrow->save();

            $action->delete(); // one-shot: prevent replay

            $escrowRef = $lockedEscrow;
        });

        if ($insufficient) {
            $notify[] = ['error', 'You have no sufficient balance on your wallet'];
            return back()->withNotify($notify);
        }

        if (!$escrowRef) {
            $notify[] = ['error', 'Sorry! Unable to process'];
            return to_route('user.transfer')->withNotify($notify)->withInput();
        }

        $notify[] = ['success', 'Milestone amount paid successfully'];
        return to_route('user.escrow.milestone.index', $escrowRef->id)->withNotify($notify);
    }
}
