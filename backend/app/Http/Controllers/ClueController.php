<?php

namespace App\Http\Controllers;

use App\Models\Clue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ClueController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $topicIds = $request->input('topic_ids');
        $query = Clue::query();

        if ($topicIds !== null) {
            $query->whereHas('topics', function ($q) use ($topicIds) {
                $q->whereIn('topics.id', $topicIds);
            });
        }

        if ($request->filled('letter')) {
            $letter = mb_strtoupper($request->input('letter'));

            $query->where('solution', 'like', '%' . $letter . '%');
        }

        if ($request->filled('search')) {
            $search = $request->input('search');

            $query->where(function ($q) use ($search) {
                $q->where('definition', 'like', '%' . $search . '%')
                    ->orWhere('solution', 'like', '%' . mb_strtoupper($search) . '%');
            });
        }

        if ($request->filled('length')) {
            $length = (int) $request->input('length');

            $query->whereRaw('CHAR_LENGTH(solution) = ?', [$length]);
        }

        $words = $query
            ->orderBy('solution')
            ->get()
            ->map(function (Clue $clue) {
                return [
                    'id' => $clue->id,
                    'solution' => mb_strtoupper($clue->solution),
                    'definition' => $clue->definition,
                    'length' => mb_strlen($clue->solution),
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'words' => $words,
        ]);
    }

    public function create(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'solution' => 'required|string|min:1|max:20|regex:/^[A-ZÁÉÍÓÖŐÚÜŰ]+$/u',
            'definition' => 'required|string|min:5|max:50',
            'topic_ids' => 'nullable|array',
            'topic_ids.*' => 'integer|exists:topics,id',
        ]);

        // Tranzakcióba rakjuk, hogy esetleges hibánál ne legyen félkész adat
        $clue = DB::transaction(function () use ($validated) {
            $clue = Clue::create([
                'solution' => mb_strtoupper($validated['solution']),
                'definition' => $validated['definition'],
            ]);

            if (!empty($validated['topic_ids'])) {
                $clue->topics()->attach($validated['topic_ids']);
            }

            return $clue;
        });

        return response()->json([
            'success' => true,
            'clue' => [
                'id' => $clue->id,
                'solution' => mb_strtoupper($clue->solution),
                'definition' => $clue->definition,
                'length' => mb_strlen($clue->solution),
            ],
        ]);
    }
}