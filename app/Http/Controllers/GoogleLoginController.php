<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use App\Providers\RouteServiceProvider;

class GoogleLoginController extends Controller
{
    public function redirectToGoogle()
    {
        return Socialite::driver('google')->redirect();
    }


    public function handleGoogleCallback(Request $request)
    {

        if($request->error)
        {
            return redirect(RouteServiceProvider::HOME);
        }

        $googleUser = Socialite::driver('google')->stateless()->user();
        $user = User::where('email', $googleUser->email)->first();
        if(!$user)
        {
            // Unusable random password: accounts only log in through Google
            $user = User::create(['name' => $googleUser->name, 'email' => $googleUser->email, 'password' => Str::random(64)]);
        }

        Auth::login($user);

        return redirect(RouteServiceProvider::HOME);
    }
}
