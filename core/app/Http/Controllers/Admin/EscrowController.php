<?php

namespace App\Http\Controllers\Admin;

use App\Constants\Status;
use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Escrow;
use App\Models\Message;
use App\Models\Milestone;
use App\Models\Transaction;
use App\Models\Wallet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class EscrowController extends Controller
{
    public function index()
    {
        $pageTitle = 'All Escrows';
        $escrows = $this->escrowData();
        return view('admin.escrow.index', compact('pageTitle', 'escrows'));
    }

    public function accepted()
    {
        $pageTitle = 'Accepted Escrows';
        $escrows = $this->escrowData('accepted');
        return view('admin.escrow.index', compact('pageTitle', 'escrows'));
    }

    public function notAccepted()
    {
        $pageTitle = 'Not Accepted Escrows';
        $escrows = $this->escrowData('notAccepted');
        return view('admin.escrow.index', compact('pageTitle', 'escrows'));
    }

    public function completed()
    {
        $pageTitle = 'Completed Escrows';
        $escrows = $this->escrowData('completed');
        return view('admin.escrow.index', compact('pageTitle', 'escrows'));
    }

    public function disputed()
    {
        $pageTitle = 'Disputed Escrows';
        $escrows = $this->escrowData('disputed');
        return view('admin.escrow.index', compact('pageTitle', 'escrows'));
    }

    public function canceled()
    {
        $pageTitle = 'Canceled Escrows';
        $escrows = $this->escrowData('canceled');
        return view('admin.escrow.index', compact('pageTitle', 'escrows'));
    }

    protected function escrowData($scope = null)
    {
        if ($scope) {
            $escrows = Escrow::$scope();
        } else {
            $escrows = Escrow::query();
        }

        return $escrows
            ->searchable(['title', 'category:name', 'escrow_number'])
            ->orderBy('id', 'desc')
            ->with('seller', 'buyer', 'category', 'currency')
            ->paginate(getPaginate());
    }

    public function details($id)
    {
        $pageTitle = 'Escrow Details';
        $escrow = Escrow::with('conversation', 'conversation.messages', 'conversation.messages.sender', 'conversation.messages.admin')->findOrFail($id);
        $restAmount = $escrow->amount + $escrow->buyer_charge - $escrow->paid_amount;
        $conversation = $escrow->conversation;
        $messages = $conversation->messages;

        return view('admin.escrow.details', compact('pageTitle', 'escrow', 'restAmount', 'conversation', 'messages'));
    }

    public function milestone($id)
    {
        $pageTitle = 'Escrow Milestone';
        $escrow = Escrow::findOrFail($id);
        $milestones = Milestone::where('escrow_id', $escrow->id)->orderBy('id', 'desc')->paginate(getPaginate());
        return view('admin.escrow.milestones', compact('pageTitle', 'escrow', 'milestones'));
    }

    public function replyMessage(Request $request)
    {
        $validate = Validator::make($request->all(), [
            'conversation_id' => 'required',
            'message' => 'required',
        ]);

        if ($validate->fails()) {
            return response()->json(['error' => $validate->errors()]);
        }

        $conversation = Conversation::active()->where('id', $request->conversation_id)->first();

        if (!$conversation) {
            return response()->json(['error' => ['Conversation not found']]);
        }

        $escrow = $conversation->escrow;

        if ($escrow->status != Status::ESCROW_DISPUTED) {
            return response()->json(['error' => ['You couldn\'t attend to this conversation']]);
        }

        $message = new Message();
        $message->admin_id = auth()->guard('admin')->id();
        $message->conversation_id = $conversation->id;
        $message->message = $request->message;
        $message->save();

        return [
            'created_diff' => $message->created_at->diffForHumans(),
            'created_time' => $message->created_at->format('h:i A'),
            'message' => $message->message,
        ];
    }

    public function getMessage(Request $request)
    {
        $validate = Validator::make($request->all(), [
            'conversation_id' => 'required',
        ]);

        if ($validate->fails()) {
            return response()->json(['error' => $validate->errors()]);
        }

        $conversation = Conversation::findOrFail($request->conversation_id);
        $messages = Message::where('conversation_id', $conversation->id)->with('sender', 'admin')->get();
        $escrow = $conversation->escrow;

        return view('admin.escrow.message', compact('messages', 'escrow'));
    }

    public function action(Request $request)
    {
        $request->validate([
            'escrow_id' => 'required|integer|exists:escrows,id',
            'buyer_amount' => 'required|numeric|gte:0',
            'seller_amount' => 'required|numeric|gte:0',
            'status' => 'required|integer|in:1,9',
        ]);

        $escrow = Escrow::disputed()->findOrFail($request->escrow_id);
        $transactAmount = $request->buyer_amount + $request->seller_amount;

        if ($transactAmount > $escrow->paid_amount) {
            $notify[] = ['error', 'You couldn\'t transact greater than funded amount'];
            return back()->withNotify($notify);
        }

        // Dispute charge = the held funds left after both payouts. Previously
        // `paid_amount - buyer + seller` (operator precedence; audit F8) which
        // over-stated the recorded charge. This only sets the recorded
        // dispute_charge field — the payouts are buyer_amount/seller_amount as given.
        $charge = $escrow->paid_amount - ($request->buyer_amount + $request->seller_amount);
        $buyer  = $escrow->buyer;
        $seller = $escrow->seller;
        $trx    = getTrx();

        // Ensure destination wallets exist BEFORE locking them (a brand-new wallet
        // row cannot be locked); balances are mutated only under the locks below.
        $buyerWallet = null;
        if ($request->buyer_amount > 0) {
            $buyerWallet = Wallet::where('user_id', $buyer->id)->where('currency_id', $escrow->currency_id)->where('user_type', 'USER')->first()
                ?: createWallet($escrow->currency, $buyer);
        }
        $sellerWallet = null;
        if ($request->seller_amount > 0) {
            $sellerWallet = Wallet::where('user_id', $seller->id)->where('currency_id', $escrow->currency_id)->where('user_type', 'USER')->first()
                ?: createWallet($escrow->currency, $seller);
        }

        $resolved          = false;
        $buyerPostBalance  = null;
        $sellerPostBalance = null;

        // Atomicity (audit F2): wrap the status change + both payouts + ledgers in
        // one transaction, lock the escrow row and re-check its status under the
        // lock so a concurrent/duplicate resolve cannot double-pay, and lock the
        // affected wallet rows in a deterministic (id-ascending) order.
        DB::transaction(function () use (
            $escrow, $request, $charge, $buyerWallet, $sellerWallet,
            $trx, &$resolved, &$buyerPostBalance, &$sellerPostBalance
        ) {
            $lockedEscrow = Escrow::where('id', $escrow->id)->lockForUpdate()->first();
            if (!$lockedEscrow || $lockedEscrow->status != Status::ESCROW_DISPUTED) {
                return; // already resolved -> idempotent no-op
            }

            $wallets = array_filter([$buyerWallet, $sellerWallet]);
            usort($wallets, fn ($a, $b) => $a->id <=> $b->id);
            $locked = [];
            foreach ($wallets as $w) {
                $locked[$w->id] = Wallet::where('id', $w->id)->lockForUpdate()->first();
            }

            if ($request->buyer_amount > 0) {
                $lw           = $locked[$buyerWallet->id];
                $lw->balance += $request->buyer_amount;
                $lw->save();

                $transaction                = new Transaction();
                $transaction->user_id       = $lockedEscrow->buyer_id;
                $transaction->wallet_id     = $lw->id;
                $transaction->user_type     = 'USER';
                $transaction->currency_id   = $lockedEscrow->currency_id;
                $transaction->before_charge = $request->buyer_amount;
                $transaction->amount        = $request->buyer_amount;
                $transaction->post_balance  = $lw->balance;
                $transaction->charge        = 0;
                $transaction->trx_type      = '+';
                $transaction->charge_type   = '+';
                $transaction->remark        = 'escrow_amount_refunded';
                $transaction->details       = 'Disputed escrow amount refunded';
                $transaction->trx           = $trx;
                $transaction->save();

                $buyerPostBalance = $lw->balance;
            }

            if ($request->seller_amount > 0) {
                $lw           = $locked[$sellerWallet->id];
                $lw->balance += $request->seller_amount;
                $lw->save();

                $transaction                = new Transaction();
                $transaction->user_id       = $lockedEscrow->seller_id;
                $transaction->user_type     = 'USER';
                $transaction->wallet_id     = $lw->id;
                $transaction->currency_id   = $lockedEscrow->currency_id;
                $transaction->before_charge = $request->seller_amount;
                $transaction->amount        = $request->seller_amount;
                $transaction->post_balance  = $lw->balance;
                $transaction->charge        = 0;
                $transaction->trx_type      = '+';
                $transaction->charge_type   = '+';
                $transaction->remark        = 'escrow_amount_received';
                $transaction->details       = 'Disputed escrow amount added';
                $transaction->trx           = $trx;
                $transaction->save();

                $sellerPostBalance = $lw->balance;
            }

            $lockedEscrow->status         = $request->status;
            $lockedEscrow->dispute_charge = $charge;
            $lockedEscrow->save();

            $resolved = true;
        });

        if (!$resolved) {
            $notify[] = ['error', 'This escrow has already been resolved'];
            return back()->withNotify($notify);
        }

        $shortCodes = [
            'title' => $escrow->title,
            'amount' => showAmount($escrow->amount, currencyFormat: false),
            'total_fund' => showAmount($escrow->paid_amount, currencyFormat: false),
            'seller_amount' => showAmount($request->seller_amount, currencyFormat: false),
            'buyer_amount' => showAmount($request->buyer_amount, currencyFormat: false),
            'charge' => showAmount($charge, currencyFormat: false),
            'trx' => $trx,
            'currency'     => $escrow->currency->currency_code,
        ];

        $buyerShortCodes                  = $shortCodes;
        $buyerShortCodes['post_balance']  = showAmount($buyerPostBalance ?? 0, currencyFormat: false);
        $sellerShortCodes                 = $shortCodes;
        $sellerShortCodes['post_balance'] = showAmount($sellerPostBalance ?? 0, currencyFormat: false);

        notify($buyer, 'ESCROW_ADMIN_ACTION', $buyerShortCodes);
        notify($seller, 'ESCROW_ADMIN_ACTION', $sellerShortCodes);

        $conversation = $escrow->conversation;
        if ($conversation) {
            $conversation->status = Status::NO;
            $conversation->save();
        }

        $notify[] = ['success', 'Escrow action taken successfully'];
        return back()->withNotify($notify);
    }
}
