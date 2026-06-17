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
        $charge = $escrow->paid_amount - $request->buyer_amount + $request->seller_amount;
        $escrow->status = $request->status;
        $escrow->dispute_charge = $charge;
        $buyer = $escrow->buyer;
        $seller = $escrow->seller;
        $trx = getTrx();
        $escrow->save();

        if ($request->buyer_amount) {
            $buyerWallet = Wallet::where('user_id', $buyer->id)->where('currency_id', $escrow->currency_id)->where('user_type', 'USER')->first();

            if (!$buyerWallet) {
                $buyerWallet = createWallet($escrow->currency, $buyer);
            }

            $buyerWallet->balance += $request->buyer_amount;
            $buyerWallet->save();

            $transaction = new Transaction();
            $transaction->user_id = $buyer->id;
            $transaction->wallet_id = $buyerWallet->id;
            $transaction->user_type = 'USER';
            $transaction->currency_id = $escrow->currency_id;
            $transaction->before_charge = $request->buyer_amount;
            $transaction->amount = $request->buyer_amount;
            $transaction->post_balance = $buyerWallet->balance;
            $transaction->charge = 0;
            $transaction->trx_type = '+';
            $transaction->charge_type = '+';
            $transaction->remark = 'escrow_amount_refunded';
            $transaction->details = 'Disputed escrow amount refunded';
            $transaction->trx = $trx;
            $transaction->save();
        }

        if ($request->seller_amount) {
            $sellerWallet = Wallet::where('user_id', $seller->id)->where('currency_id', $escrow->currency_id)->where('user_type', 'USER')->first();

            if (!$sellerWallet) {
                $sellerWallet = createWallet($escrow->currency, $seller);
            }

            $sellerWallet->balance += $request->seller_amount;
            $sellerWallet->save();

            $transaction = new Transaction();
            $transaction->user_id = $seller->id;
            $transaction->user_type = 'USER';
            $transaction->wallet_id = $sellerWallet->id;
            $transaction->currency_id = $escrow->currency_id;
            $transaction->before_charge = $request->seller_amount;
            $transaction->amount = $request->seller_amount;
            $transaction->post_balance = $sellerWallet->balance;
            $transaction->charge = 0;
            $transaction->trx_type = '+';
            $transaction->charge_type = '+';
            $transaction->remark = 'escrow_amount_received';
            $transaction->details = 'Disputed escrow amount added';
            $transaction->trx = $trx;
            $transaction->save();
        }

        $shortCodes = [
            'title' => $escrow->title,
            'amount' => showAmount($escrow->amount, currencyFormat: false),
            'total_fund' => showAmount($escrow->paid_amount, currencyFormat: false),
            'seller_amount' => showAmount($request->seller_amount, currencyFormat: false),
            'buyer_amount' => showAmount($request->buyer_amount, currencyFormat: false),
            'charge' => showAmount($charge, currencyFormat: false),
            'trx' => $trx,
            'post_balance' => showAmount($transaction->post_balance, currencyFormat: false),
            'currency'     => $escrow->currency->currency_code,
        ];

        $buyerShortCodes = $shortCodes;
        $sellerShortCodes = $shortCodes;

        $buyerShortCodes['post_balance'] = showAmount($buyerWallet->balance, currencyFormat: false);
        $sellerShortCodes['post_balance'] = showAmount($sellerWallet->balance, currencyFormat: false);

        notify($buyer, 'ESCROW_ADMIN_ACTION', $buyerShortCodes);
        notify($seller, 'ESCROW_ADMIN_ACTION', $sellerShortCodes);

        $conversation = $escrow->conversation;
        $conversation->status = Status::NO;
        $conversation->save();

        $notify[] = ['success', 'Escrow action taken successfully'];
        return back()->withNotify($notify);
    }
}
