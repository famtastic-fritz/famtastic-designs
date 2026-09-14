<?php
namespace App\Http\Controllers;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
class AuthController extends Controller {
    public function login(Request $request) {
        $data=$request->validate(['email'=>'required|email|max:254','password'=>'required|string|max:1024']);
        $email=mb_strtolower(trim($data['email']));
        $key='owner-login:'.hash('sha256',$email.'|'.$request->ip());
        if (RateLimiter::tooManyAttempts($key,5)) throw ValidationException::withMessages(['email'=>'Too many attempts. Please wait a minute and try again.']);
        RateLimiter::hit($key,60);
        if (!Auth::attempt(['email'=>$email,'password'=>$data['password'],'is_owner'=>true])) throw ValidationException::withMessages(['email'=>'The email or password was not accepted.']);
        if (!$request->user()->email_verified_at) { Auth::logout(); throw ValidationException::withMessages(['email'=>'Use Set up or reset password to activate your owner access.']); }
        RateLimiter::clear($key);
        $request->session()->regenerate();
        return redirect('/admin');
    }
    public function logout(Request $request) {
        Auth::logout(); $request->session()->invalidate(); $request->session()->regenerateToken();
        return redirect('/admin/login');
    }
    public function requestReset(Request $request) {
        if(!config('locs.mail_enabled')) return back()->withErrors(['email'=>'Password setup is not available yet. Please contact Shay for help.']);
        $data=$request->validate(['email'=>'required|email|max:254']);
        $email=mb_strtolower(trim($data['email']));
        $user=User::where('email',$email)->where('is_owner',true)->first();
        if ($user) Password::sendResetLink(['email'=>$email,'is_owner'=>true]);
        return back()->with('status','If this is an owner email, a private password setup link will arrive shortly.');
    }
    public function reset(Request $request) {
        $data=$request->validate(['token'=>'required|string|max:512','email'=>'required|email|max:254','password'=>['required','confirmed',PasswordRule::min(12),'max:1024']]);
        $data['email']=mb_strtolower(trim($data['email']));
        $data['is_owner']=true;
        $status=Password::reset($data,function(User $user,string $password) {
            DB::transaction(function() use($user,$password) {
                $user->forceFill(['password'=>Hash::make($password),'remember_token'=>Str::random(60),'email_verified_at'=>now()])->save();
                DB::table('sessions')->where('user_id',$user->id)->delete();
            });
        });
        if ($status!==Password::PASSWORD_RESET) throw ValidationException::withMessages(['email'=>'This link is invalid or expired. Request a new password setup link.']);
        $request->session()->invalidate(); $request->session()->regenerateToken();
        return redirect('/admin/login')->with('status','Your password is saved. Sign in to your Locs admin.');
    }
}
