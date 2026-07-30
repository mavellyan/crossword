<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use Laravel\Sanctum\HasApiTokens;

class AuthController extends Controller
{
    /**
     * Regisztráció, validálja az input adatokat, hiba esetén megfelelő hibaüzenetet ad vissza
     * Amennyiben valid az összes adat, létrehozza a felhasználót
     */
    public function register(Request $request)
    {
        $validatedData = $request->validate([
            'username' => 'required|string|min:5|max:255|unique:users,username',
            'email' => 'required|email:rfc|max:255|unique:users,email',
            'password' => 'required|string|min:6|confirmed',
        ], [
            'username.required' => 'A felhasználónév megadása kötelező!',
            'username.min' => 'A felhasználónévnek legalább 5 karakterből kell állnia!',
            'username.max' => 'A felhasználónév legfeljebb 255 karakterből állhat!',
            'username.unique' => 'Ez a felhasználónév már foglalt!',

            'email.required' => 'Az email cím megadása kötelező!',
            'email.email' => 'Érvénytelen email cím!',
            'email.max' => 'Az email cím legfeljebb 255 karakterből állhat!',
            'email.unique' => 'Ez az email cím már foglalt!',

            'password.required' => 'A jelszó megadása kötelező!',
            'password.min' => 'A jelszónak legalább 6 karakterből kell állnia!',
            'password.confirmed' => 'A jelszavak nem egyeznek!',
        ]);

        User::create([
            'username' => $validatedData['username'],
            'email' => $validatedData['email'],
            'password' => Hash::make($validatedData['password']),
            'role' => 'user',
        ]);

        return response()->json([
            'message' => 'Sikeres regisztráció!',
        ], 201);
    }

    /**
     * Bejelentkeztető metódus, validálja az input adatokat, hiba esetén megfelelő hibaüzenetet küld vissza
     */
    public function login(Request $request)
    {
        $validatedData = $request->validate([
            'email' => 'required|string|email|max:255',
            'password' => 'required|string',
        ]);

        if (!Auth::attempt([
            'email' => $validatedData['email'],
            'password' => $validatedData['password']
        ])) {
            return response()->json([
                'message' => 'Hibás email cím vagy jelszó!',
            ], 401);
        }

        /** @var \App\Models\User $user */
        $user = Auth::user();
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Sikeres bejelentkezés!',
            'user_id' => $user->id,
            'token' => $token,
        ], 201);
    }

    /**
     * Kijelentkeztető metódus, törli a jelenlegi tokent, így érvénytelenítve a bejelentkezést
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Sikeres kijelentkezés!',
        ]);
    }
}
