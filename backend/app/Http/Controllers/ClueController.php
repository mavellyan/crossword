<?php

namespace App\Http\Controllers;

use App\Models\Clue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClueController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Clue::query();

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
}