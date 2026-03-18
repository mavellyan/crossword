<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class AuthController extends Controller
{
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

        $user = User::create([
            'username' => $validatedData['username'],
            'email' => $validatedData['email'],
            'password' => Hash::make($validatedData['password']),
            'role' => 'user',
        ]);

        return response()->json([
            'message' => 'Sikeres regisztráció!',
        ], 201);
    }

    public function login(Request $request)
    {
        $validatedData = $request->validate([
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string',
        ]);

        if (Auth::attempt([
            'email' => $validatedData['email'],
            'password' => $validatedData['password']
        ])) {
            $user = Auth::user();
        } else {
            $user = null;
        }

        return response()->json([
            'message' => 'idk',
            'user' => $user,
        ], 201);
    }

    public function logout(Request $request)
    {
        // Logout logic will go here
    }
}
