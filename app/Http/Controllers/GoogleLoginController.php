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

        try {
            $googleUser = Socialite::driver('google')->stateless()->user();
        } catch (\Throwable $e) {
            // Expired or reused codes and Google outages: let the user try again
            report($e);

            return redirect()->route('login');
        }

        $user = User::where('email', $googleUser->email)->first();
        if(!$user)
        {
            // Unusable random password: accounts only log in through Google
            $user = User::create(['name' => $googleUser->name ?: $googleUser->email, 'email' => $googleUser->email, 'password' => Str::random(64)]);
        }

        Auth::login($user);

        return redirect(RouteServiceProvider::HOME);
    }
}
