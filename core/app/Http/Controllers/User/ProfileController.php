<?php

namespace App\Http\Controllers\User;

use Illuminate\Http\Request;
use App\Rules\FileTypeValidate;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
    public function profile()
    {
        $pageTitle = "Profile Setting";
        $user = auth()->user();

        // F2c: read GET via Inertia; the POST submitProfile() flow is UNCHANGED (native form).
        // Rollback (one line):
        //   return view('Template::user.profile_setting', compact('pageTitle','user'));
        return inertia('User/Profile', [
            'profile'   => [
                'fullname'  => $user->fullname,
                'username'  => $user->username,
                'email'     => $user->email,
                'mobile'    => $user->mobile,
                'country'   => $user->country_name,
                'image'     => $user->image ? getImage(getFilePath('userProfile') . '/' . $user->image, getFileSize('userProfile')) : null,
                'firstname' => $user->firstname,
                'lastname'  => $user->lastname,
                'address'   => $user->address,
                'state'     => $user->state,
                'zip'       => $user->zip,
                'city'      => $user->city,
            ],
            'actionUrl' => route('user.profile.setting'),
        ]);
    }

    public function submitProfile(Request $request)
    {
        $request->validate([
            'firstname' => 'required|string',
            'lastname' => 'required|string',
            'image' => ['nullable', 'image', new FileTypeValidate(['jpg', 'jpeg', 'png'])],
        ],[
            'firstname.required'=>'The first name field is required',
            'lastname.required'=>'The last name field is required'
        ]);

        $user = auth()->user();

        if ($request->hasFile('image')) {
            try {
                $old         = $user->image;
                $user->image = fileUploader($request->image, getFilePath('userProfile'), getFileSize('userProfile'), $old);
            } catch (\Exception $exp) {
                $notify[] = ['error', 'Couldn\'t upload your image'];
                return back()->withNotify($notify);
            }
        }

        $user->firstname = $request->firstname;
        $user->lastname = $request->lastname;

        $user->address = $request->address;
        $user->city = $request->city;
        $user->state = $request->state;
        $user->zip = $request->zip;

        $user->save();
        $notify[] = ['success', 'Profile updated successfully'];
        return back()->withNotify($notify);
    }

    public function changePassword()
    {
        $pageTitle = 'Change Password';

        // F2c: read GET via Inertia; the POST submitPassword() flow is UNCHANGED (native form).
        // Rollback (one line):
        //   return view('Template::user.password', compact('pageTitle'));
        return inertia('User/ChangePassword', [
            'securePassword' => (bool) gs('secure_password'),
            'actionUrl'      => route('user.change.password'),
        ]);
    }

    public function submitPassword(Request $request)
    {
        $passwordValidation = Password::min(6);
        if (gs('secure_password')) {
            $passwordValidation = $passwordValidation->mixedCase()->numbers()->symbols()->uncompromised();
        }

        $request->validate([
            'current_password' => 'required',
            'password' => ['required','confirmed',$passwordValidation]
        ]);

        $user = auth()->user();
        if (Hash::check($request->current_password, $user->password)) {
            $password = Hash::make($request->password);
            $user->password = $password;
            $user->save();
            $notify[] = ['success', 'Password changed successfully'];
            return back()->withNotify($notify);
        } else {
            $notify[] = ['error', 'The password doesn\'t match!'];
            return back()->withNotify($notify);
        }
    }
}
