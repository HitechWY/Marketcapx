<?php

namespace App\Http\Controllers\Api;

use App\Constants\Status;
use App\Http\Controllers\Api\Common;
use App\Http\Controllers\Controller;
use App\Models\UserAction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class OtpController extends Controller
{

    use Common;

    public function otpResend(Request $request)
    {

        $general    = gs();
        $userAction = $this->getAction($request->action_id);

        if (!$userAction) {
            $notify[] = 'Something went wrong';
            return responseError('validation_error', $notify);
        }

        $userAction->otp        = verificationCode(6);
        $userAction->send_at    = now();
        $userAction->expired_at = now()->addSeconds((float) $general->otp_expiration);
        $userAction->save();

        if ($userAction->type == '2fa') {
            $notify[] = 'Cannot sent 2FA verification';
            return responseError('validation_error', $notify);
        }

        notify(
            $this->guard()['user'],
            'OTP',
            ['code' => $userAction->otp],
            [$userAction->type]
        );

        $notify[] = 'Resend otp';
        return responseSuccess('resend_otp', $notify);
    }

    public function otpVerify(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'code'      => 'required',
            'action_id' => 'required',
        ]);

        if ($validator->fails()) {
            return responseError('validation_error', $validator->errors());
        }

        $code       = str_replace(' ', '', $request->code);
        $userAction = $this->getAction($request->action_id);
        $guard      = strtolower($this->guard()['user_type']);

        if (!$userAction) {
            $notify[] = 'Sorry! Unable to process';
            return responseError('validation_error', $notify);
        }

        if ($userAction->type == 'email' || $userAction->type == 'sms') {
            if ($userAction->otp != $code) {
                $notify[] = 'OTP doesn\'t match';
                return responseError('validation_error', $notify);
            }
            if ($userAction->expired_at < now()) {
                $notify[] = 'Your OTP has expired';
                return responseError('validation_error', $notify);
            }
        } else {
            $user     = $userAction->$guard;
            $response = verifyG2fa($user, $code);
            if (!$response) {
                $notify[] = 'Verification code doesn\'t match';
                return responseError('validation_error', $notify);
            }
        }

        $userAction->used_at = now();
        $userAction->save();

        return callApiMethod($userAction->details->done_route, $userAction->id);
    }

    protected function getAction($actionId)
    {
        $user     = $this->guard()['user'];
        $userType = $this->guard()['user_type'];

        return UserAction::where('user_id', $user->id)->where('is_otp', Status::YES)->where('is_api', Status::YES)->where('used', Status::NO)->where('user_type', $userType)
            ->where('id', $actionId)->first();
    }
}
