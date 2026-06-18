<?php

namespace App\Http\Controllers\User;

use App\Constants\Status;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Conversation;
use App\Models\Currency;
use App\Models\Escrow;
use App\Models\Message;
use App\Models\Transaction;
use App\Models\TransactionCharge;
use App\Models\User;
use App\Models\Wallet;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class EscrowController extends Controller
{

    public function index($type = null)
    {
        $pageTitle = 'My Escrow';
        $escrows   = Escrow::searchable(['escrow_number'])->where(function ($query) {
            $query->orWhere('buyer_id', auth()->id())->orWhere('seller_id', auth()->id());
        })->with('seller', 'buyer');

        if ($type) {
            try {
                $escrows = $escrows->$type();
            } catch (Exception $e) {
                abort(404);
            }
        }

        $escrows = $escrows->orderBy('id', 'desc')->with('category')->paginate(getPaginate());
        return view('Template::user.escrow.index', compact('pageTitle', 'escrows'));
    }

    public function stepOne()
    {
        if (!in_array(request()->step, ['1', '2', '3'])) {
            return to_route('user.escrow.step.one', ['step' => '1']);
        }
        $pageTitle = "New Escrow - Step One";
        return view('Template::user.escrow.step_one', compact('pageTitle'));
    }

    public function submitStepOne(Request $request)
    {
        $request->validate([
            'type' => 'required|in:1,2',
        ]);
        $newData         = $request->except('_token');
        $existingSession = session('escrow_info', []);
        $mergedData      = array_merge($existingSession, $newData);
        session()->put('escrow_info', $mergedData);
        return to_route('user.escrow.step.two', ['step' => 2]);
    }

    public function stepTwo()
    {
        if (!in_array(request()->step, ['1', '2', '3'])) {
            return to_route('user.escrow.step.one', ['step' => 'one']);
        }
        $escrowInfo = session('escrow_info');
        if (!$escrowInfo) {
            $notify[] = ['error', 'Invalid action for step two'];
            return to_route('user.escrow.step.one', ['step' => 1])->withNotify($notify);
        }
        $pageTitle  = "New Escrow - Step Two";
        $categories = Category::active()->get();
        return view('Template::user.escrow.step_two', compact('pageTitle', 'categories'));
    }

    public function submitStepTwo(Request $request)
    {
        $escrowInfo         = session('escrow_info');
        $isDeliveryRequired = Category::where('id', $request->category_id)->value('is_delivery');
        $rules              = [
            'category_id'          => 'required|exists:categories,id',
            'delivery_opportunity' => [
                $isDeliveryRequired ? 'required' : 'nullable',
                'in:1,0',
            ],
        ];

        //from banner
        if ($request->has('amount') || $request->has('type')) {
            $rules = array_merge($rules, [
                'type'   => 'required|in:1,2',
                'amount' => 'required|numeric|gt:0',
            ]);
        } else {
            if (!$escrowInfo || !isset($escrowInfo['type'])) {
                return to_route('user.escrow.step.one', ['step' => 1])
                    ->withNotify([['error', 'Invalid action for step two']]);
            }
        }
        if (!$request->is_banner) {
            unset($escrowInfo['amount']);
        }

        $request->validate($rules);
        $newData     = $request->except('_token');
        $updatedData = array_merge($escrowInfo ?? [], $newData);
        session()->put('escrow_info', $updatedData);
        return to_route('user.escrow.step.three', ['step' => 3]);
    }

    public function stepThree()
    {
        if (!in_array(request()->step, ['1', '2', '3'])) {
            return to_route('user.escrow.step.one', ['step' => 'one']);
        }
        $escrowInfo = session('escrow_info');

        $pageTitle = "New Escrow - Step Three";
        if (!$escrowInfo || !isset($escrowInfo['category_id'])) {
            $notify[] = ['error', 'Invalid action for step three'];
            return to_route('user.escrow.step.one', ['step' => 2])->withNotify($notify);
        }

        $currencies = Currency::enable()->get();

        return view('Template::user.escrow.step_three', compact('pageTitle', 'escrowInfo', 'currencies'));
    }

    public function submitStepThree(Request $request)
    {
        $request->validate([
            'email'        => 'required|max:40',
            'title'        => 'required|max:255',
            'currency'     => 'required|integer',
            'amount'       => 'required|gt:0',
            'charge'       => 'required|gte:0',
            'details'      => 'required',
            'charge_payer' => 'required|in:1,2,3',
        ]);

        $this->checkSessionData($request->email);

        $escrowInfo = session('escrow_info');
        if (!$escrowInfo) {
            $notify[] = ['error', 'Invalid action for step three'];
            return to_route('user.escrow.step.three', ['step' => 3])->withNotify($notify);
        }

        $currency = Currency::enable()->findOrFail($request->currency);

        $categoryId          = $escrowInfo['category_id'];
        $escrowType          = $escrowInfo['type'];
        $deliveryOpportunity = @$escrowInfo['delivery_opportunity'] ?? 0;
        $user                = auth()->user();
        $toUser              = User::where('email', $request->email)->first();
        $amount              = $request->amount;
        $charge              = $this->getCharge($amount, $currency->rate);
        $charge              = $currency->currency_type == Status::FIAT_CURRENCY ? getAmount($charge, 2) : getAmount($charge, 8);

        $sellerCharge = 0;
        $buyerCharge  = 0;

        if ($request->charge_payer == Status::SELLER) {
            $sellerCharge = $charge;
        } elseif ($request->charge_payer == Status::BUYER) {
            $buyerCharge = $charge;
        } else {
            $sellerCharge = $charge / 2;
            $buyerCharge  = $charge / 2;
        }

        $escrow = new Escrow();

        $escrow->escrow_number = getTrx();
        if ($escrowType == Status::SELLER) {
            $escrow->seller_id = $user->id ?? 0;
            $escrow->buyer_id  = @$toUser->id ?? 0;
        } else {
            $escrow->buyer_id  = $user->id ?? 0;
            $escrow->seller_id = @$toUser->id ?? 0;
        }
        $escrow->creator_id           = $user->id;
        $escrow->currency_id          = $currency->id;
        $escrow->amount               = $amount;
        $escrow->charge_payer         = $request->charge_payer;
        $escrow->charge               = $charge;
        $escrow->buyer_charge         = $buyerCharge;
        $escrow->seller_charge        = $sellerCharge;
        $escrow->category_id          = $categoryId;
        $escrow->delivery_opportunity = $deliveryOpportunity;
        $escrow->title                = $request->title;
        $escrow->details              = $request->details;

        if (!$toUser) {
            $escrow->invitation_mail = $request->email;
        }

        $escrow->save();
        $conversation            = new Conversation();
        $conversation->escrow_id = $escrow->id;
        $conversation->buyer_id  = $escrow->buyer_id;
        $conversation->seller_id = $escrow->seller_id;
        $conversation->save();

        if (!$toUser) {
            $user = (object) [
                'fullname' => $request->email,
                'username' => $request->email,
                'email'    => $request->email,
            ];

            notify($user, 'INVITATION_LINK', [
                'link' => route('user.register') . "?invite_email=" . $request->email,
            ], ['email']);
        }

        session()->forget('escrow_info');

        if ($escrow->creator_id == $escrow->seller_id) {
            $userId = $escrow->buyer_id;
        } else {
            $userId = $escrow->seller_id;
        }
        $toUser  = User::where('id', $userId)->first();
        $message = 'You\'ve successfully created an escrow transaction.';
        if (!$toUser) {
            $message = 'You\'ve successfully created an escrow transaction and invitation link sent successfully.';
        }

        $notify[] = ['success', $message];

        return to_route('user.escrow.overview', $escrow->id)->withNotify($notify);
    }

    public function overview($id)
    {
        $pageTitle = "Escrow Overview";
        $escrow    = Escrow::checkUser()
            ->with(['conversation.messages.sender', 'conversation.messages.admin', 'category'])
            ->findOrFail($id);

        $conversation = $escrow->conversation;
        $messages     = $conversation->messages;

        $opponentId = ($escrow->creator_id == $escrow->seller_id)
            ? $escrow->buyer_id
            : $escrow->seller_id;

        $isDelivery = $escrow->category->is_delivery;

        $toUser = User::find($opponentId);
        $notify = $toUser
            ? "You've successfully created an escrow transaction."
            : "You've successfully created an escrow transaction, and an invitation link has been sent successfully.";

        $unreadCount = $conversation->messages()
            ->where('sender_id', '!=', auth()->id())
            ->where('is_read', Status::NO)
            ->count();

        $oppositePartyId = auth()->id() == $escrow->seller_id ? $escrow->buyer_id : $escrow->seller_id;

        return view('Template::user.escrow.overview', compact('pageTitle', 'escrow', 'conversation', 'messages', 'notify', 'isDelivery', 'unreadCount'));
    }

    public function readMessages($escrowId)
    {
        $escrow = Escrow::checkUser()
            ->with(['conversation.messages.sender', 'conversation.messages.admin', 'category'])
            ->findOrFail($escrowId);

        $conversation = $escrow->conversation;
        $updated      = $conversation->messages()
            ->where('sender_id', '!=', auth()->id())
            ->where('is_read', Status::NO)
            ->update(['is_read' => Status::YES]);

        return response()->json([
            'success' => true,
            'message' => $updated ? 'Messages marked as read' : 'No messages to update',
        ]);
    }

    public function updateEscrow(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'escrow_id'            => 'required|exists:escrows,id',
            'email'                => 'required|email',
            'amount'               => 'required|numeric|min:0',
            'delivery_opportunity' => 'nullable|in:0,1',
            'to_notify'            => 'required|in:0,1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error'   => $validator->errors()->messages(),
                'success' => false,
            ]);
        }

        $escrow = Escrow::findOrFail($request->escrow_id);
        $amount = $request->amount;
        $charge = $this->getCharge($amount, $escrow->currency->rate);
        $charge = $escrow->currency->currency_type == Status::FIAT_CURRENCY ? getAmount($charge, 2) : getAmount($charge, 8);

        $sellerCharge = 0;
        $buyerCharge  = 0;
        if ($escrow->charge_payer == Status::SELLER) {
            $sellerCharge = $charge;
        } elseif ($escrow->charge_payer == Status::BUYER) {
            $buyerCharge = $charge;
        } else {
            $sellerCharge = $charge / 2;
            $buyerCharge  = $charge / 2;
        }
        $escrow->charge               = $charge;
        $escrow->buyer_charge         = $buyerCharge;
        $escrow->seller_charge        = $sellerCharge;
        $escrow->amount               = $amount;
        $escrow->delivery_opportunity = $request->delivery_opportunity;
        $escrow->save();

        if ($request->to_notify == Status::YES) {
            $toUser = User::where('email', $request->email)->first();
            if (!$toUser) {
                $user = (object) [
                    'fullname' => $request->email,
                    'username' => $request->email,
                    'email'    => $request->email,
                ];

                notify($user, 'INVITATION_LINK', [
                    'link' => route('user.register') . "?invite_email=" . $request->email,
                ], ['email']);
            } else {
                notify($toUser, 'INVITATION_LINK', [
                    'link' => route('user.register') . "?invite_email=" . $request->email,
                ], ['email']);
            }
        }

        return response()->json([
            'status'  => 'success',
            'charge'  => showAmount($charge),
            'message' => 'Transaction updated successfully!',
        ]);
    }

    public function replyMessage(Request $request)
    {
        $validate = Validator::make($request->all(), [
            'conversation_id' => 'required|exists:conversations,id',
            'message'         => 'required',
        ]);

        if ($validate->fails()) {
            return response()->json(['error' => $validate->errors()]);
        }

        $conversation = Conversation::where('id', $request->conversation_id)->checkUser()->active()->first();

        if (!$conversation) {
            return response()->json(['error' => ['Conversation not found']]);
        }

        $message                  = new Message();
        $message->sender_id       = auth()->id();
        $message->conversation_id = $conversation->id;
        $message->message         = $request->message;
        $message->save();

        return [
            'created_diff' => $message->created_at->diffForHumans(),
            'created_time' => $message->created_at->format('h:i A'),
            'message'      => $message->message,
        ];
    }

    public function getMessages(Request $request)
    {
        $validate = Validator::make($request->all(), [
            'conversation_id' => 'required|exists:conversations,id',
        ]);

        if ($validate->fails()) {
            return response()->json(['error' => $validate->errors()]);
        }

        $conversation = Conversation::where('id', $request->conversation_id)->where(function ($query) {
            $query->orWhere('buyer_id', auth()->id())->orWhere('seller_id', auth()->id());
        })->first();

        if (!$conversation) {
            return response()->json(['error' => ['Conversation not found']]);
        }

        $escrow   = $conversation->escrow;
        $messages = Message::where('conversation_id', $conversation->id)->with('sender', 'admin')->get();
        return view('Template::user.escrow.message', compact('messages', 'escrow'));
    }

    public function cancel($id)
    {
        $escrow         = Escrow::checkUser()->notAccepted()->findOrFail($id);
        $escrow->status = Status::ESCROW_CANCELLED;
        $escrow->save();

        if ($escrow->buyer_id == auth()->id()) {
            $mailReceiver = $escrow->seller;
            $canceller    = 'buyer';
        } else {
            $mailReceiver = $escrow->buyer;
            $canceller    = 'seller';
        }

        $conversation         = $escrow->conversation;
        $conversation->status = Status::CONVERSION_CLOSE;
        $conversation->save();

        if ($mailReceiver) {
            notify($mailReceiver, 'ESCROW_CANCELLED', [
                'title'      => $escrow->title,
                'amount'     => showAmount($escrow->amount, currencyFormat: false),
                'canceller'  => $canceller,
                'total_fund' => showAmount($escrow->paid_amount, currencyFormat: false),
                'currency'   => $escrow->currency->currency_code,
            ]);
        }

        $notify[] = ['success', 'Escrow cancelled successfully'];
        return back()->withNotify($notify);
    }

    public function accept($id)
    {
        $escrow = Escrow::checkUser()->where('creator_id', '!=', auth()->id())->notAccepted()->findOrFail($id);

        $escrow->status = Status::ESCROW_ACCEPTED;
        $escrow->save();

        if ($escrow->buyer_id = auth()->id()) {
            $mailReceiver = $escrow->seller;
            $accepter     = 'buyer';
        } else {
            $mailReceiver = $escrow->buyer;
            $accepter     = 'seller';
        }

        notify($mailReceiver, 'ESCROW_ACCEPTED', [
            'title'      => $escrow->title,
            'amount'     => showAmount($escrow->amount, currencyFormat: false),
            'accepter'   => $accepter,
            'total_fund' => showAmount($escrow->paid_amount, currencyFormat: false),
            'currency'   => $escrow->currency->currency_code,
        ]);

        $notify[] = ['success', 'Escrow accepted successfully'];
        return back()->withNotify($notify);
    }

    public function dispute(Request $request, $id)
    {
        $request->validate([
            'dispute_reason' => 'required|string',
        ]);

        $escrow = Escrow::checkUser()->accepted()->findOrFail($id);

        $escrow->status       = Status::ESCROW_DISPUTED;
        $escrow->disputer_id  = auth()->id();
        $escrow->dispute_note = $request->dispute_reason;
        $escrow->save();

        $conversation           = $escrow->conversation;
        $conversation->is_group = 1;
        $conversation->save();

        if ($escrow->buyer_id == auth()->id()) {
            $mailReceiver = $escrow->seller;
            $disputer     = 'buyer';
        } else {
            $mailReceiver = $escrow->buyer;
            $disputer     = 'seller';
        }

        notify($mailReceiver, 'ESCROW_DISPUTED', [
            'title'        => $escrow->title,
            'amount'       => showAmount($escrow->amount, currencyFormat: false),
            'disputer'     => $disputer,
            'total_fund'   => showAmount($escrow->paid_amount, currencyFormat: false),
            'dispute_note' => $request->dispute_reason,
            'currency'     => $escrow->currency->currency_code,
        ]);

        $notify[] = ['success', 'Escrow disputed successfully'];
        return back()->withNotify($notify);
    }

    public function dispatchEscrow($id)
    {
        // Ownership + precondition gate (also preserves the 404-on-replay UX once
        // the escrow is no longer accepted).
        $escrow = Escrow::where('buyer_id', auth()->id())->accepted()->findOrFail($id);

        $seller = $escrow->seller;

        // Ensure the destination wallet exists BEFORE locking it (a brand-new
        // wallet row cannot be locked); the balance is mutated only under the lock.
        $wallet = Wallet::where('user_id', $seller->id)->where('currency_id', $escrow->currency_id)->where('user_type', 'USER')->first();
        if (!$wallet) {
            $wallet = createWallet($escrow->currency, $seller);
        }

        $trx         = getTrx();
        $dispatched  = false;
        $postBalance = null;

        // Atomicity (audit F1): wrap the status flip + seller credit + charge +
        // ledger in one transaction, lock the escrow row and re-check its status
        // under the lock so a concurrent/duplicate dispatch cannot double-credit,
        // and lock the seller wallet before mutating its balance.
        DB::transaction(function () use ($escrow, $wallet, $trx, &$dispatched, &$postBalance) {
            $lockedEscrow = Escrow::where('id', $escrow->id)->lockForUpdate()->first();
            if (!$lockedEscrow || $lockedEscrow->status != Status::ESCROW_ACCEPTED) {
                return; // already dispatched -> idempotent no-op
            }

            $lockedWallet = Wallet::where('id', $wallet->id)->lockForUpdate()->first();

            $amount                 = $lockedEscrow->amount;
            $lockedWallet->balance += $amount;
            $lockedWallet->save();

            $transaction                = new Transaction();
            $transaction->user_id       = $lockedEscrow->seller_id;
            $transaction->wallet_id     = $lockedWallet->id;
            $transaction->currency_id   = $lockedWallet->currency_id;
            $transaction->user_type     = 'USER';
            $transaction->before_charge = $amount;
            $transaction->amount        = $amount;
            $transaction->charge        = 0;
            $transaction->post_balance  = $lockedWallet->balance;
            $transaction->trx_type      = '+';
            $transaction->remark        = "escrow_payment_dispatched";
            $transaction->details       = 'Escrow payment dispatched';
            $transaction->trx           = $trx;
            $transaction->save();

            if ($lockedEscrow->seller_charge && $lockedEscrow->seller_charge > 0) {
                $lockedWallet->balance -= $lockedEscrow->seller_charge;
                $lockedWallet->save();

                $transaction                = new Transaction();
                $transaction->user_id       = $lockedEscrow->seller_id;
                $transaction->wallet_id     = $lockedWallet->id;
                $transaction->currency_id   = $lockedWallet->currency_id;
                $transaction->user_type     = 'USER';
                $transaction->before_charge = $lockedEscrow->seller_charge;
                $transaction->amount        = $lockedEscrow->seller_charge;
                $transaction->post_balance  = $lockedWallet->balance;
                $transaction->charge        = 0;
                $transaction->trx_type      = '-';
                $transaction->charge_type   = '+';
                $transaction->remark        = "escrow_charge";
                $transaction->details       = 'Deducted as escrow charge';
                $transaction->trx           = $trx;
                $transaction->save();
            }

            $lockedEscrow->status = Status::ESCROW_COMPLETED;
            $lockedEscrow->save();

            $dispatched  = true;
            $postBalance = $lockedWallet->balance;
        });

        if ($dispatched) {
            notify($seller, 'ESCROW_PAYMENT_DISPATCHED', [
                'title'         => $escrow->title,
                'amount'        => showAmount($escrow->amount, currencyFormat: false),
                'charge'        => showAmount($escrow->charge, currencyFormat: false),
                'seller_charge' => showAmount($escrow->seller_charge, currencyFormat: false),
                'trx'           => $trx,
                'post_balance'  => showAmount($postBalance, currencyFormat: false),
                'currency'      => $escrow->currency->currency_code,
            ]);
        }

        $notify[] = ['success', 'Escrow payment dispatched successfully'];
        return back()->withNotify($notify);
    }

    private function checkSessionData($email)
    {
        $user       = auth()->user();
        $escrowInfo = session('escrow_info');

        if (!$escrowInfo) {
            throw ValidationException::withMessages(['error' => 'Session invalidated']);
        }

        if ($user->email == $email) {
            throw ValidationException::withMessages(['error' => 'You can not create escrow with yourself']);
        }

        $category = Category::active()->where('id', $escrowInfo['category_id'])->first();

        if (!$category) {
            throw ValidationException::withMessages(['error' => 'Invalid escrow category']);
        }
    }

    public function showCharge(Request $request)
    {
        $currency = Currency::enable()->where('currency_code', $request->currency)->first();

        if (!$currency) {
            return response()->json(['charge' => getAmount(0)]);
        }

        $charge = $this->getCharge($request->amount, $currency->rate) ?? 0;
        $charge = $currency->currency_type == Status::FIAT_CURRENCY ? getAmount($charge, 2) : getAmount($charge, 8);

        return response()->json(['charge' => getAmount($charge)]);
    }

    private function getCharge($amount, $rate)
    {
        $escrowCharge = TransactionCharge::where('slug', 'escrow_charge')->first();

        $fixedCharge = currencyConverter($escrowCharge->fixed_charge, $rate);
        $totalCharge = chargeCalculator($amount, $escrowCharge->percent_charge, $fixedCharge);

        return $totalCharge;
    }
}
