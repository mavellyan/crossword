<?php

namespace App\Http\Controllers;

class TopicController extends Controller
{
    public function index()
    {
        $topics = \App\Models\Topic::all();

        $topics = $topics->map(function ($topic) {
            return [
                'id' => $topic->id,
                'name' => $topic->name,
                'description' => $topic->description,
            ];
        });

        return response()->json([
            'success' => true,
            'topics' => $topics,
        ]);
    }
}