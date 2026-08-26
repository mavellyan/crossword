<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\User;

class UserController extends Controller
{
    public function index(Request $request) : JsonResponse
    {
        $creators = User::has('crosswords')->get(['id', 'username']);

        return response()->json([
            'success' => true,
            'creators' => $creators,
        ]);
    }
}
