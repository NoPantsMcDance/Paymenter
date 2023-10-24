<?php

namespace App\Addons\SocialRegistration;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Providers\RouteServiceProvider;
use Illuminate\Validation\ValidationException;

class NoPantsConfirmablePasswordController extends Controller
{
    /**
     * Show the confirm password view.
     *
     * @return \Illuminate\View\View
     */
    public function show(Request $request)
    {
        if (Auth::check() && Auth::user()->is_social_user) {
            // If user is a social user, bypass the password confirmation view
            $request->session()->put('auth.password_confirmed_at', time());
            return redirect()->intended(RouteServiceProvider::HOME);
        }

        return view('auth.passwords.confirm');
    }

    /**
     * Confirm the user's password.
     *
     * @return mixed
     */
}