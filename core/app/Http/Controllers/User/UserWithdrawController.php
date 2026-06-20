<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Traits\WithdrawProcess;
use App\Models\UserWithdrawMethod;
use App\Models\Withdrawal;
use App\Models\WithdrawMethod;
use Illuminate\Http\Request;

class UserWithdrawController extends Controller{
    
    use WithdrawProcess;

    public function withdraw(){
        $userMethods = UserWithdrawMethod::myWithdrawMethod()->with('withdrawMethod', 'currency')->get();
        $pageTitle = 'Withdraw Money';
        return view('Template::user.withdraw.withdraw_money', compact('pageTitle', 'userMethods'));
    }

    public function withdrawPreview(){
        $withdraw = Withdrawal::with('method', 'user')->where('trx', session()->get('wtrx'))->where('status', 0)->orderBy('id', 'desc')->firstOrFail();
        $pageTitle = 'Withdraw Preview';
        return view('Template::user.withdraw.preview', compact('pageTitle', 'withdraw'));
    }

    public function withdrawLog(Request $request){
        $pageTitle = "Withdraw Log";
        $withdraws = auth()->user()->withdrawals()->searchable(['trx'])->with(['method'])->orderBy('id','desc')->with('curr')->paginate(getPaginate());

        // F2b: read-only Inertia (history list only — no withdraw money/mutation logic touched).
        // Rollback (one line):
        //   return view('Template::user.withdraw.log', compact('pageTitle', 'withdraws'));
        $withdraws->through(fn ($w) => [
            'trx'           => $w->trx,
            'method'        => __(@$w->method->name),
            'date'          => showDateTime($w->created_at),
            'dateHuman'     => diffForHumans($w->created_at),
            'symbol'        => @$w->curr->currency_symbol,
            'code'          => @$w->curr->currency_code,
            'amount'        => showAmount($w->amount, currencyFormat: false),
            'charge'        => showAmount($w->charge, currencyFormat: false),
            'afterCharge'   => showAmount($w->amount - $w->charge, currencyFormat: false),
            'statusBadge'   => $w->statusBadge, // admin-trusted HTML
            'status'        => (int) $w->status,
            'information'   => $w->withdraw_information ?? [],
            'adminFeedback' => $w->status == 3 ? $w->admin_feedback : null,
        ]);

        return inertia('User/WithdrawHistory', [
            'withdraws' => $withdraws,
            'search'    => (string) $request->search,
        ]);
    }

    public function withdrawMethods(){
        $userMethods = UserWithdrawMethod::myWithdrawMethod()->whereHas('withdrawMethod')->with('withdrawMethod', 'currency')->paginate(getPaginate());
        $pageTitle = 'Withdraw Methods';
        return view('Template::user.withdraw.methods', compact('pageTitle', 'userMethods'));
    }

    public function fileDownload($fileHash){
        $filePath = decrypt($fileHash);
        $extension = pathinfo($filePath, PATHINFO_EXTENSION);
        $general = gs();
        $title = slug($general->site_name) . '- attachments.' . $extension;
        $mimetype = mime_content_type($filePath);
        header('Content-Disposition: attachment; filename="' . $title);
        header("Content-Type: " . $mimetype);
        return readfile($filePath);
    }
}
