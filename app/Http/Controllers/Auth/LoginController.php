<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Services\LoginDestination;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class LoginController extends Controller
{
    public function index()
    {
        return view('auth.login');
    }

    public function register() { return view('auth.register'); }

    public function registerStore(Request $request)
    {
        $data=$request->validate(['name'=>'required|string|max:120','email'=>'required|email|max:255|unique:users,email','password'=>'required|string|min:8|confirmed']);
        DB::transaction(function() use($data){$user=User::create(['name'=>$data['name'],'email'=>$data['email'],'password'=>Hash::make($data['password']),'role'=>'Student']);$user->assignRole('Student');[$first,$last]=array_pad(explode(' ',trim($data['name']),2),2,'');Student::create(['user_id'=>$user->id,'first_name'=>$first,'last_name'=>$last,'father_name'=>'Pending verification','date_of_birth'=>'2000-01-01','gender'=>'Other','phone'=>'Pending verification','address'=>'Pending verification','admission_date'=>now()->toDateString(),'roll_number'=>'PENDING-'.$user->id,'admission_no'=>'PENDING-'.$user->id,'status'=>'Active','email'=>$user->email]);});
        return redirect()->route('login')->with('status','Registration complete. You can now sign in.');
    }

    public function store(LoginRequest $request)
    {
        // One limit per client IP also prevents bypassing the limit with different emails.
        $key = 'panel-login:'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['email' => 'Too many login attempts. Try again in '.RateLimiter::availableIn($key).' seconds.']);
        }
        RateLimiter::hit($key, 60);
        if (! Auth::attempt($request->only('email', 'password'), $request->boolean('remember'))) {
            throw ValidationException::withMessages(['email' => 'Invalid email or password.']);
        }
        if (! $request->user()->hasAnyRole(['Admin', 'Principal', 'Teacher','Student','Parent'])) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            throw ValidationException::withMessages(['email' => 'An authorized school account is required.']);
        }
        $request->session()->regenerate();
        $request->session()->forget('url.intended');
        RateLimiter::clear($key);
        return redirect()->to(LoginDestination::for($request->user()));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->forget('url.intended');
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login')->with('status', 'Logged out successfully.');
    }
}
