<?php

namespace App\Models;

use App\Enums\Difficulty;
use App\Enums\Direction;
use Exception;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Crossword extends Model
{
    use SoftDeletes, HasFactory;

    protected $fillable = [
        'title',
        'user_id',
        'main_solution',
        'difficulty',
        'is_public',
    ];

    protected $casts = [
        'difficulty' => Difficulty::class,
        'is_public' => 'boolean',
    ];

    private int $generatedWidth = 0;
    private int $generatedHeight = 0;

    public function crosswordClues(): HasMany
    {
        return $this->hasMany(CrosswordClue::class);
    }

    public function topics(): BelongsToMany
    {
        return $this->belongsToMany(Topic::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function getMainSolution(): ?string
    {
        return $this->main_solution;
    }

    public function getWords()
    {
        if (!$this->relationLoaded('crosswordClues')) {
            $this->load('crosswordClues.clue');
        }

        return $this->crosswordClues
            ->where('is_main', false)
            ->sortBy('start_row')
            ->values();
    }

    /**
     * Beállítja a rejtvény szavait, levizsgálva, hogy lehetséges-e belőlük rejtvényt alkotni
     *
     * @param array<Clue> $clues
     * @return void
     * @throws Exception
     */
    public function setWords(array $clues): void
    {
        $clues = array_values($clues);

        if (empty($clues)) {
            throw new Exception('Üres tömb átadva.');
        }

        $mainSolution = (string) $this->main_solution;
        $mainLength = mb_strlen($mainSolution);

        if (count($clues) !== $mainLength) {
            throw new Exception('Nem egyezik a szavak száma és a főmegoldás hossza.');
        }

        foreach ($clues as $clue) {
            if (!$clue instanceof Clue) {
                throw new Exception('A setWords csak Clue példányokat fogad.');
            }
        }

        $mainLetters = mb_str_split(mb_strtoupper($mainSolution));

        $assignment = $this->canAssignCluesToLetters($mainLetters, $clues);

        if (!$assignment['valid']) {
            throw new Exception('A megadott szavakból nem állítható össze rejtvény.');
        }

        // Kiszámoljuk a legnagyobb balra eső eltolást, hogy a főmegoldás betűi középre kerüljenek a rejtvényben
        // Ez alapján állítjuk be a főmegoldás vízszintes pozícióját, a többi szó pozíciója pedig ehhez képest lesz meghatározva
        $maxLeftOffset = 0;

        foreach ($assignment['horizontal_positions'] as $intersectionIndex) {
            if ($intersectionIndex > $maxLeftOffset) {
                $maxLeftOffset = $intersectionIndex;
            }
        }

        $mainWordXPos = $maxLeftOffset;

        $placements = [];

        foreach ($clues as $i => $clue) {
            $intersectionPos = $assignment['horizontal_positions'][$i];
            $yPos = $assignment['vertical_positions'][$i];

            // Legalább 0 kell legyen, abban az esetben, ha a főmegoldás egy betűjéhez van hozzárendelve a szó első betűje
            $startXPos = $mainWordXPos - $intersectionPos;

            $placement = new CrosswordClue([
                'clue_id' => $clue->id,
                'direction' => Direction::HORIZONTAL,
                'start_col' => $startXPos,
                'start_row' => $yPos,
                'intersection_index' => $intersectionPos,
                'is_main' => false,
            ]);

            $placement->setRelation('clue', $clue);

            $placements[] = $placement;
        }

        // Y pozíció szerint növekvő sorba rakjuk a betűket, hogy könnyebb legyen őket majd elhelyezni a rácsban
        usort($placements, function (CrosswordClue $a, CrosswordClue $b) {
            return $a->getYPos() <=> $b->getYPos();
        });

        $this->setRelation('crosswordClues', new EloquentCollection($placements));
    }

    public function saveWords(array $clues): void
    {
        if (!$this->exists) {
            throw new Exception('A Crossword modellt előbb el kell menteni, csak utána lehet szavakat kapcsolni hozzá.');
        }

        $this->setWords($clues);

        $placements = $this->getWords();

        $this->crosswordClues()->delete();

        foreach ($placements as $placement) {
            $clue = $placement->getRelation('clue');

            if (!$clue->exists) {
                $clue->save();
            }

            $placement->clue()->associate($clue);
            $this->crosswordClues()->save($placement);
        }

        $this->load('crosswordClues.clue');
    }

    /**
     * Megnézi, hogy a főmegoldás betűihez hozzárendelhetőek-e a megadott szavak
     * Azaz, minden főmegoldás betűhöz tartozik 1 megoldás, amik megfejtésével végül kijön majd a főmegoldás
     *
     * @param array $letters
     * @param array<Clue> $clues
     * @param int $letterIndex
     * @param array $usedClueIndexes
     * @param array $verticalPositions
     * @param array $horizontalPositions
     * 
     * @return array
     */
    private function canAssignCluesToLetters(
        array $letters,
        array $clues,
        int $letterIndex = 0,
        array $usedClueIndexes = [],
        array $verticalPositions = [],
        array $horizontalPositions = [],
    ): array
    {
        // Ha végigértünk a betűkön, akkor sikerült mindhez szót találni
        if ($letterIndex >= count($letters)) {
            return [
                'valid' => true,
                'vertical_positions' => $verticalPositions,
                'horizontal_positions' => $horizontalPositions,
            ];
        }

        $currentLetter = $letters[$letterIndex];

        foreach ($clues as $clueIndex => $clue) {
            // Ha egyszer már felhasználtuk a szót, átugorjuk
            if (in_array($clueIndex, $usedClueIndexes, true)) {
                continue;
            }

            $solution = mb_strtoupper($clue->getSolution());
            $pos = mb_strpos($solution, $currentLetter);

            // Ha megtaláljuk a keresett betűt a szóban, akkor eltároljuk az indexét mert felhasználtuk a szót
            // Majd újra meghívjuk a metódust
            if ($pos !== false) {
                $newUsed = [...$usedClueIndexes, $clueIndex];

                // Beállítjuk a szavak pozícióját a rejtvényen belül
                $newVertical = $verticalPositions;
                $newHorizontal = $horizontalPositions;

                $newVertical[$clueIndex] = $letterIndex;
                $newHorizontal[$clueIndex] = $pos;

                $result = $this->canAssignCluesToLetters(
                    $letters,
                    $clues,
                    $letterIndex + 1,
                    $newUsed,
                    $newVertical,
                    $newHorizontal
                );

                if ($result['valid']) {
                    return $result;
                }
            }
        }

        return [
            'valid' => false,
            'vertical_positions' => [],
            'horizontal_positions' => [],
        ];
    }

    /**
     * Elkészíti a 2D tömböt, benne elrendezve a rejtvény definícióit
     *
     * @return array
     */
    public function generateGrid(): array
    {
        $words = $this->getWords();

        $mainSolution = mb_strtoupper((string) $this->main_solution);
        $mainLetters = mb_str_split($mainSolution);

        $mainXPos = $this->getMainSolutionXPos($words);

        $height = count($mainLetters);
        $width = $mainXPos + 1;

        // Kiszámoljuk a rács szélességét, hogy elférjenek benne a szavak
        foreach ($words as $word) {
            foreach ($word->getCells() as $cell) {
                $height = max($height, $cell['row'] + 1);
                $width = max($width, $cell['col'] + 1);
            }
        }

        $this->generatedWidth = $width;
        $this->generatedHeight = $height;

        $grid = array_fill(0, $height, array_fill(0, $width, '#'));

        foreach ($words as $word) {
            foreach ($word->getCells() as $cell) {
                $grid[$cell['row']][$cell['col']] = $cell['letter'];
            }
        }

        foreach ($mainLetters as $row => $letter) {
            $grid[$row][$mainXPos] = $letter;
        }

        return $grid;
    }

    private function getMainSolutionXPos(Collection $words): int
    {
        $firstWordWithIntersection = $words->first(
            fn (CrosswordClue $word) => $word->getIntersectionPos() >= 0
        );

        if (!$firstWordWithIntersection) {
            return 0;
        }

        return $firstWordWithIntersection->getXPos()
            + $firstWordWithIntersection->getIntersectionPos();
    }

    public function getWidth(): int
    {
        return $this->generatedWidth;
    }

    public function getHeight(): int
    {
        return $this->generatedHeight;
    }

    public function toFrontendArray(): array
    {
        $this->loadMissing('crosswordClues.clue', 'topics', 'creator');

        $grid = $this->generateGrid();

        return [
            'id' => $this->id,
            'title' => $this->title,
            'user_id' => $this->user_id,
            'main_solution' => $this->main_solution,
            'difficulty' => $this->difficulty?->value ?? $this->difficulty,
            'is_public' => (bool) $this->is_public,
            'topics' => $this->topics,
            'words' => $this->getWords()->values(),
            'grid' => $grid,
            'width' => $this->getWidth(),
            'height' => $this->getHeight(),
        ];
    }

    /**
     * A rejtvényhez tartozó próbálkozások lekérése.
     */    
    public function attempts()
    {
        return $this->hasMany(CrosswordAttempt::class);
    }
}