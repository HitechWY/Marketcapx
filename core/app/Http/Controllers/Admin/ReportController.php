<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NotificationLog;
use App\Models\Transaction;
use App\Models\UserLogin;
use App\Traits\BuildsAdminReadonly;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    use BuildsAdminReadonly;

    public function transaction(Request $request, $userId = null)
    {
        $pageTitle = 'Transaction Logs';
        $remarks   = Transaction::distinct('remark')->orderBy('remark')->get('remark');

        $response = $this->typeFormat();

        $transactions = Transaction::filter(['trx_type', 'remark', 'user_type', 'currency:currency_id'])->dateFilter();

        if ($request->search) {
            $transactions = $transactions->where(function ($q) use ($request, $response) {
                foreach ($response['with'] as $relation) {
                    $q->orWhereHas($relation, function ($query) use ($request, $relation) {
                        $query->where('username', 'like', "%$request->search%")->where('user_type', strtoupper($relation));
                    });
                }
            })->orWhere('trx', 'LIKE', "%$request->search%");
        }

        $transactions = $transactions->orderBy('id', 'desc')->with('wallet', 'currency', 'receiverUser', 'receiverAgent', 'receiverMerchant');

        if ($userId) {
            $transactions = $transactions->where('user_id', $userId);
        }

        $transactions = $transactions->paginate(getPaginate());

        // F6c: read-only Inertia transaction report. Rollback (one line):
        //   return view('admin.reports.transactions', compact('pageTitle', 'transactions', 'remarks'));
        $transactions->appends(request()->all());
        $transactions->through(fn ($t) => $this->mapAdminTransaction($t));

        return inertia('Admin/Transactions', [
            'pageTitle'    => $pageTitle,
            'transactions' => $transactions,
            'filters'      => [
                'search'    => (string) request('search'),
                'user_type' => strtolower((string) request('user_type')),
                'currency'  => (string) request('currency'),
                'trx_type'  => (string) request('trx_type'),
                'remark'    => (string) request('remark'),
                'date'      => (string) request('date'),
            ],
            'currencies'   => $this->currencyOptions(),
            'remarks'      => $remarks->map(fn ($r) => ['value' => $r->remark, 'label' => keyToTitle($r->remark)])->values()->all(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function mapAdminTransaction($t): array
    {
        return [
            'id'          => $t->id,
            'fullname'    => @$t->getUser->fullname,
            'username'    => @$t->getUser->username,
            'userType'    => $t->user_type,
            'trx'         => $t->trx,
            'date'        => showDateTime($t->created_at),
            'dateHuman'   => diffForHumans($t->created_at),
            'amount'      => showAmount($t->amount, currencyFormat: false),
            'trxType'     => $t->trx_type,
            'code'        => @$t->currency->currency_code,
            'postBalance' => showAmount($t->post_balance, currencyFormat: false),
            'details'     => __((string) $t->details),
            'receiver'    => $t->receiver ? @$t->receiver->username : null,
        ];
    }

    public function loginHistory(Request $request)
    {
        $request->merge(['user_type' => strtolower($request->user_type)]);
        $response = $this->typeFormat();

        $loginLogs = UserLogin::where($response['column'], '!=', 0)->with($response['with'])->dateFilter();

        if ($request->user_type == 'user' || $request->user_type == 'agent' || $request->user_type == 'merchant') {
            $loginLogs = $loginLogs->whereHas($request->user_type);
        }

        $pageTitle = 'Login History';

        if ($request->search) {
            $search = $request->search;
            $pageTitle .= ' - ' . $search;

            $loginLogs = $loginLogs->where(function ($query) use ($response, $request) {
                foreach ($response['with'] as $relation) {
                    $query->orWhereHas($relation, function ($query2) use ($request) {
                        $query2->where('username', 'like', "%$request->search%");
                    });
                }
            });
        }

        $loginLogs = $loginLogs->orderBy('id', 'desc')->paginate(getPaginate());

        // F6c: read-only Inertia login history. Rollback (one line):
        //   return view('admin.reports.logins', compact('pageTitle', 'loginLogs'));
        return $this->loginInertia($pageTitle, $loginLogs, true);
    }

    public function loginIpHistory($ip)
    {
        $pageTitle = 'Login by - ' . $ip;
        $loginLogs = UserLogin::where('user_ip', $ip)->orderBy('id', 'desc')->with('user')->paginate(getPaginate());

        // F6c: read-only Inertia login history (by IP). Rollback (one line):
        //   return view('admin.reports.logins', compact('pageTitle', 'loginLogs', 'ip'));
        return $this->loginInertia($pageTitle, $loginLogs, false, $ip);
    }

    /**
     * @return \Inertia\Response
     */
    protected function loginInertia(string $pageTitle, $loginLogs, bool $showFilter, ?string $ip = null)
    {
        $loginLogs->appends(request()->all());
        $loginLogs->through(fn ($l) => $this->mapLoginRow($l));

        return inertia('Admin/LoginHistory', [
            'pageTitle'   => $pageTitle,
            'logins'      => $loginLogs,
            'showFilter'  => $showFilter,
            'filters'     => [
                'search'    => (string) request('search'),
                'user_type' => strtolower((string) request('user_type')),
                'date'      => (string) request('date'),
            ],
            'ip'          => $ip,
            'ipLookupUrl' => $ip ? 'https://www.ip2location.com/' . $ip : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function mapLoginRow($l): array
    {
        $type = $l->user_id ? 'USER' : ($l->agent_id ? 'AGENT' : ($l->merchant_id ? 'MERCHANT' : '—'));

        return [
            'id'        => $l->id,
            'fullname'  => @$l->showUser->fullname,
            'username'  => @$l->showUser->username,
            'userType'  => $type,
            'date'      => showDateTime($l->created_at),
            'dateHuman' => diffForHumans($l->created_at),
            'ip'        => $l->user_ip,
            'ipUrl'     => route('admin.report.login.ip.history', [$l->user_ip]),
            'city'      => __((string) $l->city),
            'country'   => __((string) $l->country),
            'browser'   => __((string) $l->browser),
            'os'        => __((string) $l->os),
        ];
    }

    public function notificationHistory(Request $request)
    {

        $request->merge(['user_type' => strtoupper($request->user_type)]);

        $pageTitle = 'Notification History';
        $logs      = NotificationLog::orderBy('id', 'desc');

        $response = $this->typeFormat();
        $search   = $request->search;

        if ($search) {
            foreach ($response['with'] as $relation) {
                $logs = $logs->orWhereHas($relation, function ($query) use ($request, $relation) {
                    $query->where('username', 'like', "%$request->search%")->where('user_type', strtoupper($relation));
                });
            }
        }

        $logs = $logs->filter(['user_type'])->dateFilter()->with(['user', 'agent', 'merchant'])->paginate(getPaginate());
        return view('admin.reports.notification_history', compact('pageTitle', 'logs'));
    }

    public function emailDetails($id)
    {
        $pageTitle = 'Email Details';
        $email     = NotificationLog::findOrFail($id);
        return view('admin.reports.email_details', compact('pageTitle', 'email'));
    }

    protected function typeFormat()
    {

        $userType = @request()->user_type;
        $userType = strtolower(@$userType);

        $array = [
            'user'     => ['column' => 'user_id', 'with' => ['user']],
            'agent'    => ['column' => 'agent_id', 'with' => ['agent']],
            'merchant' => ['column' => 'merchant_id', 'with' => ['merchant']],
            'all'      => ['column' => 'id', 'with' => ['user', 'agent', 'merchant']],
        ];

        return @$array[@$userType] ?? @$array['all'];
    }

}
