<?php

namespace App\Http\Controllers;

use App\Models\Clue;
use App\Models\Crossword;
use App\Models\CrosswordAttempt;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    /**
     * Adminisztrátori dashboard statisztikák lekérése
     */
    public function dashboard(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'statistics' => [
                'users' => User::query()->count(),
                'active_users' => User::query()
                    ->where('is_active', true)
                    ->count(),
                'crosswords' => Crossword::query()->count(),
                'public_crosswords' => Crossword::query()
                    ->where('is_public', true)
                    ->count(),
                'clues' => Clue::query()->count(),
                'completed_attempts' => CrosswordAttempt::query()
                    ->where('status', 'completed')
                    ->count(),
            ],
        ]);
    }

    /**
     * Adminisztrátori felhasználói lista lekérése, keresési és lapozási lehetőséggel
     * A felhasználókhoz tartozó statisztikák is lekérdezhetők, mint a rejtvények száma és a próbálkozások száma
     * A keresés a felhasználónév és az email cím alapján történik
     */
    public function users(Request $request): JsonResponse
    {
        $search = trim((string) $request->input('search', ''));

        $users = User::query()
            ->withCount(['crosswords', 'attempts'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query
                        ->where('username', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('created_at')
            ->paginate(
                perPage: min($request->integer('per_page', 15), 50),
            );

        $users->through(fn (User $user): array => [
            'id' => $user->id,
            'username' => $user->username,
            'email' => $user->email,
            'role' => $user->role,
            'is_active' => (bool) $user->is_active,
            'crosswords_count' => $user->crosswords_count,
            'attempts_count' => $user->attempts_count,
            'created_at' => $user->created_at,
        ]);

        return response()->json([
            'success' => true,
            'users' => $users,
        ]);
    }

    /**
     * Adminisztrátori felhasználói státusz módosítása (aktiválás / letiltás)
     * A saját adminisztrátori fiók nem tiltható le
     */
    public function setUserStatus(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        if ($request->user()->is($user) && !$validated['is_active']) {
            return response()->json([
                'success' => false,
                'message' => 'A saját adminisztrátori fiókodat nem tilthatod le.',
            ], 409);
        }

        DB::transaction(function () use ($user, $validated): void {
            $user->is_active = $validated['is_active'];
            $user->save();

            if (!$user->is_active) {
                // A már kiadott tokeneket is érvényteleníti.
                $user->tokens()->delete();
            }
        });

        return response()->json([
            'success' => true,
            'message' => $user->is_active
                ? 'A felhasználó aktiválva lett.'
                : 'A felhasználó le lett tiltva.',
            'user' => [
                'id' => $user->id,
                'is_active' => (bool) $user->is_active,
            ],
        ]);
    }

    /**
     * Adminisztrátori rejtvény lista lekérése, keresési és lapozási lehetőséggel
     * A keresés a rejtvény cím alapján történik
     */
    public function crosswords(Request $request): JsonResponse
    {
        $search = trim((string) $request->input('search', ''));

        $crosswords = Crossword::query()
            ->with('creator:id,username')
            ->withCount([
                'crosswordClues as words_count' => fn ($query) =>
                    $query->where('is_main', false),
                'attempts',
            ])
            ->when($search !== '', function ($query) use ($search) {
                $query->where('title', 'like', "%{$search}%");
            })
            ->orderByDesc('created_at')
            ->paginate(
                perPage: min($request->integer('per_page', 15), 50),
            );

        $crosswords->through(fn (Crossword $crossword): array => [
            'id' => $crossword->id,
            'title' => $crossword->title,
            'creator' => $crossword->creator?->username,
            'is_public' => (bool) $crossword->is_public,
            'words_count' => $crossword->words_count,
            'attempts_count' => $crossword->attempts_count,
            'created_at' => $crossword->created_at,
        ]);

        return response()->json([
            'success' => true,
            'crosswords' => $crosswords,
        ]);
    }

    /**
     * Adminisztrátori rejtvény törlése
     * A törléshez a rejtvényhez tartozó próbálkozások is törlődnek, ezért a törlés végleges és nem visszavonható
     */
    public function deleteCrossword(Crossword $crossword): JsonResponse
    {
        $crossword->delete();

        return response()->json([
            'success' => true,
            'message' => 'A rejtvény törölve lett.',
        ]);
    }

    /**
     * Adminisztrátori szó lista lekérése, keresési és lapozási lehetőséggel
     * A keresés a szó megoldás és definíció alapján történik
     * A szavakhoz tartozó statisztikák is lekérdezhetők, mint a felhasználók száma, akik használják a szót a rejtvényeikben
     * A szavakhoz tartozó témák is lekérdezhetők
     */
    public function clues(Request $request): JsonResponse
    {
        $search = trim((string) $request->input('search', ''));

        $clues = Clue::query()
            ->with('topics:id,name')
            ->withCount('placements')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query
                        ->where('solution', 'like', '%' . mb_strtoupper($search) . '%')
                        ->orWhere('definition', 'like', "%{$search}%");
                });
            })
            ->orderBy('solution')
            ->paginate(
                perPage: min($request->integer('per_page', 15), 50),
            );

        $clues->through(fn (Clue $clue): array => [
            'id' => $clue->id,
            'solution' => $clue->solution,
            'definition' => $clue->definition,
            'topics' => $clue->topics->map(fn ($topic): array => [
                'id' => $topic->id,
                'name' => $topic->name,
            ])->values(),
            'placements_count' => $clue->placements_count,
            'created_at' => $clue->created_at,
        ]);

        return response()->json([
            'success' => true,
            'clues' => $clues,
        ]);
    }

    /**
     * Adminisztrátori szó törlése
     * A törléshez a szóhoz tartozó rejtvény próbálkozások is törlődnek, ezért a törlés végleges és nem visszavonható
     * A szó nem törölhető, ha legalább egy rejtvény használja a szót, ebben az esetben a kliensnek 409-es státuszkódot küld vissza a szerver
     */
    public function deleteClue(Clue $clue): JsonResponse
    {
        if ($clue->placements()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'A szó nem törölhető, mert legalább egy rejtvény használja.',
            ], 409);
        }

        DB::transaction(function () use ($clue): void {
            $clue->topics()->detach();
            $clue->delete();
        });

        return response()->json([
            'success' => true,
            'message' => 'A szó törölve lett.',
        ]);
    }
}