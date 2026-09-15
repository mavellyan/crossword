<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\User;

class UserController extends Controller
{
    public function index() : JsonResponse
    {
        $creators = User::has('crosswords')->get(['id', 'username']);

        return response()->json([
            'success' => true,
            'creators' => $creators,
        ]);
    }

    public function profile(Request $request) : JsonResponse
    {
        $user = $request->user('sanctum');
        $user->load('crosswords');
        $user->load('attempts');

        $crosswords = $user->crosswords()
            ->with('topics:id,name')
            ->withCount(['attempts' => function ($query) {
                $query->where('status', '!=', 'not_started');
            }])
            ->get(['crosswords.id', 'crosswords.title', 'crosswords.is_public', 'crosswords.created_at']);

        $crosswordsWithTopics = $crosswords->map(function ($crossword) {
            return [
                'id' => $crossword->id,
                'title' => $crossword->title,
                'is_public' => $crossword->is_public,
                'created_at' => $crossword->created_at,
                'attempts_count' => $crossword->attempts_count,
                'is_updating' => false,
                'topics' => $crossword->topics->map(function ($topic) {
                    return [
                        'id' => $topic->id,
                        'name' => $topic->name
                    ];
                }),
            ];
        });

        $attempts = $user->attempts()
            ->with('crossword:id,title,difficulty,user_id', 'crossword.creator:id,username')
            ->where('status', '!=', 'not_started')
            ->latest('id')
            ->get(['id', 'crossword_id', 'status', 'elapsed_time'])
            ->unique('crossword_id')
            ->values();

        $attemptsWithCrosswordData = $attempts->map(function ($attempt) {
            return [
                'id' => $attempt->id,
                'crossword_id' => $attempt->crossword_id,
                'status' => $attempt->status,
                'elapsed_time' => $attempt->elapsed_time,
                'crossword_title' => $attempt->crossword->title,
                'crossword_difficulty' => $attempt->crossword->difficulty,
                'creator_username' => $attempt->crossword?->creator?->username,
            ];
        });

        return response()->json([
            'success' => true,
            'user' => [
                'id' => $user->id,
                'username' => $user->username,
                'email' => $user->email,
                'role' => $user->role,
            ],
            'crosswords' => $crosswordsWithTopics,
            'attempts' => $attemptsWithCrosswordData,
        ]);
    }
}
