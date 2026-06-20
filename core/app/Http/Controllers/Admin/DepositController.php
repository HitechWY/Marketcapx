<?php

namespace App\Http\Controllers\Admin;

use App\Constants\Status;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Gateway\PaymentController;
use App\Models\Currency;
use App\Models\Deposit;
use App\Models\Gateway;
use App\Traits\BuildsAdminReadonly;
use Illuminate\Http\Request;

class DepositController extends Controller
{
    use BuildsAdminReadonly;

    public function pending($userId = null)
    {
        $pageTitle = 'Pending Deposits';
        $deposits  = $this->depositData('pending', userId: $userId);
        return $this->depositInertia($pageTitle, $deposits);
    }

    public function approved($userId = null)
    {
        $pageTitle = 'Approved Deposits';
        $deposits  = $this->depositData('approved', userId: $userId);
        return $this->depositInertia($pageTitle, $deposits);
    }

    public function successful($userId = null)
    {
        $pageTitle = 'Successful Deposits';
        $deposits  = $this->depositData('successful', userId: $userId);
        return $this->depositInertia($pageTitle, $deposits);
    }

    public function rejected($userId = null)
    {
        $pageTitle = 'Rejected Deposits';
        $deposits  = $this->depositData('rejected', userId: $userId);
        return $this->depositInertia($pageTitle, $deposits);
    }

    public function initiated($userId = null)
    {
        $pageTitle = 'Initiated Deposits';
        $deposits  = $this->depositData('initiated', userId: $userId);
        return $this->depositInertia($pageTitle, $deposits);
    }

    public function deposit($userId = null)
    {
        $pageTitle   = 'Deposit History';
        $depositData = $this->depositData($scope = null, $summary = true, userId: $userId);
        $deposits    = $depositData['data'];
        $summary     = $depositData['summary'];
        $successful  = $summary['successful'];
        $pending     = $summary['pending'];
        $rejected    = $summary['rejected'];
        $initiated   = $summary['initiated'];
        return $this->depositInertia($pageTitle, $deposits, [
            'successful' => getAmount($successful),
            'pending'    => getAmount($pending),
            'rejected'   => getAmount($rejected),
            'initiated'  => getAmount($initiated),
        ]);
    }

    protected function depositData($scope = null, $summary = false, $userId = null)
    {
        $response = $this->typeFormat();
        $with     = array_merge($response['with'], ['gateway']);

        if ($scope) {
            $deposits = Deposit::$scope()->with($with);
        } else {
            $deposits = Deposit::with($with);
        }

        if ($response['type'] != '*') {
            $deposits = $deposits->where('user_type', $response['type']);
        }

        if ($userId) {
            $deposits = $deposits->where('user_id', $userId);
        }

        $request = request();
        $request->merge(['user_type' => strtoupper($request->user_type)]);

        if ($request->search) {
            $deposits = $deposits->where(function ($q) use ($request, $response) {
                foreach ($response['with'] as $relation) {
                    $q->orWhereHas($relation, function ($query) use ($request, $relation) {
                        $query->where('username', 'like', "%$request->search%")->where('user_type', strtoupper($relation));
                    });
                }
            })->orWhere('trx', 'LIKE', "%$request->search%");
        }

        $deposits = $deposits->filter(['user_type', 'currency:currency_id'])->dateFilter(table: 'deposits');

        if ($request->method) {
            if ($request->method != Status::GOOGLE_PAY) {
                $method   = Gateway::where('alias', $request->method)->firstOrFail();
                $deposits = $deposits->where('method_code', $method->code);
            } else {
                $deposits = $deposits->where('method_code', Status::GOOGLE_PAY);
            }
        }

        if (!$summary) {
            return $deposits->orderBy('id', 'desc')->paginate(getPaginate());
        } else {
            $successful = clone $deposits;
            $pending    = clone $deposits;
            $rejected   = clone $deposits;
            $initiated  = clone $deposits;

            $successfulSummary = $successful->where('deposits.status', Status::PAYMENT_SUCCESS)
                ->leftJoin('currencies', 'currencies.id', '=', 'deposits.currency_id')
                ->selectRaw("SUM(amount * currencies.rate) as amount")
                ->first('amount')->amount ?? 0;

            $pendingSummary = $pending->where('deposits.status', Status::PAYMENT_PENDING)
                ->leftJoin('currencies', 'currencies.id', '=', 'deposits.currency_id')
                ->selectRaw("SUM(amount * currencies.rate) as amount")
                ->first('amount')->amount ?? 0;

            $rejectedSummary = $rejected->where('deposits.status', Status::PAYMENT_REJECT)
                ->leftJoin('currencies', 'currencies.id', '=', 'deposits.currency_id')
                ->selectRaw("SUM(amount * currencies.rate) as amount")
                ->first('amount')->amount ?? 0;

            $initiatedSummary = $initiated->where('deposits.status', Status::PAYMENT_INITIATE)
                ->leftJoin('currencies', 'currencies.id', '=', 'deposits.currency_id')
                ->selectRaw("SUM(amount * currencies.rate) as amount")
                ->first('amount')->amount ?? 0;

            return [
                'data'    => $deposits->orderBy('id', 'desc')->paginate(getPaginate()),
                'summary' => [
                    'successful' => $successfulSummary,
                    'pending'    => $pendingSummary,
                    'rejected'   => $rejectedSummary,
                    'initiated'  => $initiatedSummary,
                ],
            ];
        }
    }

    protected function typeFormat()
    {

        $userType = @request()->user_type;
        $userType = strtolower(@$userType);

        $array = [
            'user'  => ['type' => 'USER', 'with' => ['user']],
            'agent' => ['type' => 'AGENT', 'with' => ['agent']],
            'all'   => ['type' => '*', 'with' => ['user', 'agent']],
        ];

        return @$array[$userType] ?? @$array['all'];
    }

    /**
     * F6b: read-only Inertia deposit list. Approve/reject stay on the (Blade) detail page.
     * Rollback (one line): return view('admin.deposit.log', compact('pageTitle', 'deposits'[, summary...]));
     *
     * @param  array<string, string>|null  $summary
     */
    protected function depositInertia(string $pageTitle, $deposits, ?array $summary = null)
    {
        $deposits->appends(request()->all());
        $deposits->through(fn ($d) => $this->mapDepositRow($d));

        return inertia('Admin/Deposits', [
            'pageTitle'      => $pageTitle,
            'deposits'       => $deposits,
            'summary'        => $summary,
            'filters'        => [
                'search'    => (string) request('search'),
                'user_type' => strtolower((string) request('user_type')),
                'currency'  => (string) request('currency'),
                'date'      => (string) request('date'),
            ],
            'currencies'     => $this->currencyOptions(),
            'currencySymbol' => gs('cur_sym'),
        ]);
    }

    /**
     * Explicit, secret-free deposit row (no gateway `detail` payload).
     *
     * @return array<string, mixed>
     */
    protected function mapDepositRow($d): array
    {
        $gateway = $d->method_code < 5000 ? __(@$d->gateway->name) : 'Google Pay';

        return [
            'id'             => $d->id,
            'trx'            => $d->trx,
            'gateway'        => $gateway,
            'initiated'      => showDateTime($d->created_at),
            'initiatedHuman' => diffForHumans($d->created_at),
            'userType'       => __($d->user_type),
            'fullname'       => @$d->getUser->fullname,
            'username'       => @$d->getUser->username,
            'amount'         => showAmount($d->amount, currencyFormat: false),
            'charge'         => showAmount($d->charge, currencyFormat: false),
            'total'          => showAmount($d->amount + $d->charge, currencyFormat: false),
            'currency'       => __($d->method_currency),
            'status'         => $this->paymentChip($d->status),
            'detailUrl'      => route('admin.deposit.details', $d->id),
        ];
    }

    public function details($id)
    {
        $deposit   = Deposit::where('id', $id)->with(['user', 'gateway'])->firstOrFail();
        $pageTitle = $deposit->user->username . ' requested ' . showAmount($deposit->amount, currencyFormat:false).' '.$deposit->method_currency;
        $details   = ($deposit->detail != null) ? json_encode($deposit->detail) : null;
        return view('admin.deposit.detail', compact('pageTitle', 'deposit', 'details'));
    }

    public function approve($id)
    {
        $deposit = Deposit::where('id', $id)->where('status', Status::PAYMENT_PENDING)->firstOrFail();

        PaymentController::userDataUpdate($deposit, true);

        $notify[] = ['success', 'Deposit request approved successfully'];

        return to_route('admin.deposit.pending')->withNotify($notify);
    }

    public function reject(Request $request)
    {
        $request->validate([
            'id'      => 'required|integer',
            'message' => 'required|string|max:255',
        ]);
        $deposit = Deposit::where('id', $request->id)->where('status', Status::PAYMENT_PENDING)->firstOrFail();

        $deposit->admin_feedback = $request->message;
        $deposit->status         = Status::PAYMENT_REJECT;
        $deposit->save();

        notify($deposit->user, 'DEPOSIT_REJECT', [
            'method_name'       => $deposit->methodName(),
            'method_currency'   => $deposit->method_currency,
            'method_amount'     => showAmount($deposit->final_amount, currencyFormat: false),
            'amount'            => showAmount($deposit->amount, currencyFormat: false),
            'charge'            => showAmount($deposit->charge, currencyFormat: false),
            'rate'              => showAmount($deposit->rate, currencyFormat: false),
            'trx'               => $deposit->trx,
            'rejection_message' => $request->message,
        ]);

        $notify[] = ['success', 'Deposit request rejected successfully'];
        return to_route('admin.deposit.pending')->withNotify($notify);

    }
}
