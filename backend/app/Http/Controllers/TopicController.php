<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Models\Topic;

class TopicController extends Controller
{
    public function index(Request $request) : JsonResponse
    {
        $topics = Topic::all();

        $topics = $topics->map(function ($topic) {
            return [
                'id' => $topic->id,
                'name' => $topic->name,
            ];
        });

        return response()->json([
            'success' => true,
            'topics' => $topics,
        ]);
    }
}