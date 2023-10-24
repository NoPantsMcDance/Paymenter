<?php

namespace App\Addons\SocialRegistration;

use App\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class NoPantsSocialLoginController extends Controller
{
    public function handleProviderCallback($provider)
    {
        $socialUser = Socialite::driver($provider)->user();
        $user = User::where('email', $socialUser->email)->first();

        switch ($provider) {
            case 'discord':
                if ($socialUser->user["verified"] == false) {
                    return redirect()->route('login')->with('error', 'Your Discord account is not verified.');
                }
                break;

            case 'google':
                // You can add any specific logic related to Google provider here
                break;

            case 'github':
                // You can add any specific logic related to GitHub provider here
                break;

            default:
                return redirect()->route('login');
        }

        if (!$user) {
            // Generate a random password
            $randomPassword = Str::random(12); // Adjust the length as needed

            // Hash the password
            $hashedPassword = Hash::make($randomPassword);

            // User doesn't exist, so register them
            $newUser = User::create([
                'first_name' => $socialUser->name,
                'email' => $socialUser->email,
                'password' => $hashedPassword, // Store the hashed password
                'last_name' => $provider,
                'is_social_user' => true, // Set the flag for social registration
                // Set other fields as needed
            ]);

            // Log in the newly registered user
            Auth::login($newUser, true);

            event(new Registered($newUser));

            return redirect()->route('index');
        } else {
            // User exists, log them in
            Auth::login($user, true);

            return redirect()->route('index');
        }
    }
}