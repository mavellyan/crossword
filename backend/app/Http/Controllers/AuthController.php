<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $validatedData = $request->validate([
            'username' => 'required|string|min:5|max:255|unique:users',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6|confirmed',
        ]);

        $username = $validatedData['username'];
        $email = $validatedData['email'];
        $password = $validatedData['password'];

        $password_hash = Hash::make($password);

        $user = User::create([
            'username' => $username,
            'email' => $email,
            'password' => $password_hash,
            'role' => 'admin',
        ]);

        return response()->json([
            'message' => 'Registration successful',
            'user' => $user,
        ], 201);
    }

    public function login(Request $request)
    {
        // Login logic will go here
    }

    public function logout(Request $request)
    {
        // Logout logic will go here
    }
}
