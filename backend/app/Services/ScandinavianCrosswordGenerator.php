<?php

namespace App\Services;

/**
 * Skandináv keresztrejtvény generáló algoritmus
 *
 * Működési elv:
 * 1. A főmegoldás betűihez kulcsszavakat választunk ki (minden betűhöz egy szót,
 *    amely tartalmazza azt a betűt → ez lesz a kiemelt/megoldáscella).
 * 2. A kulcsszavakat vízszintesen helyezzük el úgy, hogy a kiemelt betűjük
 *    egy rögzített "megoldásoszlopba" essen – ezek a cellák felülről lefelé
 *    olvasva adják ki a főmegoldást.
 * 3. A fennmaradó szavakat klasszikus keresztrejtvény-logikával helyezzük el
 *    (meglévő szavakkal való kereszteződést keresünk, különben szabad helyre tesszük).
 *
 * Rács cellatípusok:
 *  - 'empty'    : fekete/kitöltetlen cella (a felhasználó nem ír ide)
 *  - 'clue'     : definíciós cella (fekete, szöveggel) – a szó előtt áll
 *  - 'letter'   : fehér cella, ahová a felhasználó betűt ír
 *  - 'solution' : fehér cella + a főmegoldás egy betűje (kiemelve jelenik meg)
 */
class ScandinavianCrosswordGenerator
{
    private const DIR_H = 'H'; // vízszintes
    private const DIR_V = 'V'; // függőleges

    private int $width;
    private int $height;
    private array $grid = [];
    private array $placedWords = [];
    private int $solutionCol; // a kiemelt cellák oszlopindexe

    // -------------------------------------------------------------------------
    // Publikus API
    // -------------------------------------------------------------------------

    /**
     * Generálja a rejtvényt.
     *
     * @param  string $mainSolution A főmegoldás szó (pl. "BUDAPEST")
     * @param  array  $wordPairs ['definíció' => 'MEGOLDÁS', ...] – asszociatív tömb
     * @return array  A kész rejtvény adatszerkezete (lásd exportPuzzle())
     *
     * @throws \RuntimeException ha nem sikerül minden főmegoldás-betűhöz szót rendelni
     */
    public function generate(string $mainSolution, array $wordPairs): array
    {
        $mainSolution = mb_strtoupper(trim($mainSolution));
        $solLen = mb_strlen($mainSolution);

        // Szavakat nagybetűsítjük
        $pairs = [];
        foreach ($wordPairs as $def => $word) {
            $pairs[trim($def)] = mb_strtoupper(trim($word));
        }

        // Rácsméret becslése
        $maxWordLen = max(array_map('mb_strlen', array_values($pairs)));
        $this->width = max(28, $maxWordLen + 8);
        $this->height = max(28, ($solLen + count($pairs)) * 2 + 6);

        $this->initGrid();

        // A megoldásoszlop kb. a rács közepe
        $this->solutionCol = (int) floor($this->width / 2);

        // 1. lépés: kulcsszavak kiválasztása
        [$keyWords, $remaining] = $this->selectKeyWords($pairs, $mainSolution);

        if (count($keyWords) < $solLen) {
            $missing = $solLen - count($keyWords);
            throw new \RuntimeException(
                "Nem sikerült {$missing} főmegoldás-betűhöz megfelelő szót találni. "
                . "Bővítsd a szólistát, vagy ellenőrizd, hogy minden betűre van-e szó."
            );
        }

        // 2. lépés: kulcsszavak elhelyezése (vízszintesen, megoldásoszlopra igazítva)
        $this->placeKeyWords($keyWords);

        // 3. lépés: fennmaradó szavak elhelyezése
        $this->placeRemainingWords($remaining);

        return $this->exportPuzzle($mainSolution);
    }

    // -------------------------------------------------------------------------
    // 1. Kulcsszó-kiválasztás
    // -------------------------------------------------------------------------

    /**
     * Minden főmegoldás-betűhöz kiválaszt egy szót, amely tartalmazza azt a betűt.
     * Preferenciák: a betű a szó közepe felé essen (jobb vizuális elrendezés),
     * és a szó legyen minél hosszabb (több kereszteződési lehetőség).
     *
     * @return array [$keyWords, $remainingWords]
     *               $keyWords: [solutionIndex => ['definition','word','letter_pos','solution_letter'], ...]
     */
    private function selectKeyWords(array $pairs, string $mainSolution): array
    {
        $keyWords = [];
        $remaining = $pairs;
        $usedDefs = [];

        for ($i = 0; $i < mb_strlen($mainSolution); $i++) {
            $targetLetter = mb_substr($mainSolution, $i, 1);
            $bestCandidate = null;
            $bestScore = -1;

            foreach ($remaining as $def => $word) {
                if (in_array($def, $usedDefs, true)) {
                    continue;
                }

                $positions = $this->findLetterPositions($word, $targetLetter);
                if (empty($positions)) {
                    continue;
                }

                $wordLen = mb_strlen($word);
                $middle = ($wordLen - 1) / 2;

                // A középhez legközelebb eső pozíciót választjuk az adott szóból
                $bestPos = $positions[0];
                $minDist = abs($positions[0] - $middle);
                foreach ($positions as $pos) {
                    $dist = abs($pos - $middle);
                    if ($dist < $minDist) {
                        $minDist = $dist;
                        $bestPos = $pos;
                    }
                }

                // Pontszám: hosszabb szó + középhez közelebb → jobb
                $score = $wordLen * 2 - $minDist;
                if ($score > $bestScore) {
                    $bestScore = $score;
                    $bestCandidate = [
                        'definition' => $def,
                        'word' => $word,
                        'letter_pos' => $bestPos,   // hányadik betű a megoldáscella (0-alapú)
                        'solution_index' => $i,
                        'solution_letter' => $targetLetter,
                    ];
                }
            }

            if ($bestCandidate !== null) {
                $keyWords[$i] = $bestCandidate;
                $usedDefs[] = $bestCandidate['definition'];
                unset($remaining[$bestCandidate['definition']]);
            }
        }

        return [$keyWords, $remaining];
    }

    /** Megkeresi egy betű összes előfordulásának pozícióját egy szóban. */
    private function findLetterPositions(string $word, string $letter): array
    {
        $positions = [];
        $len = mb_strlen($word);
        for ($i = 0; $i < $len; $i++) {
            if (mb_substr($word, $i, 1) === $letter) {
                $positions[] = $i;
            }
        }
        return $positions;
    }

    // -------------------------------------------------------------------------
    // 2. Kulcsszavak elhelyezése
    // -------------------------------------------------------------------------

    /**
     * A kulcsszavakat vízszintesen helyezi el, úgy hogy a kiemelt betűjük
     * pontosan a $this->solutionCol oszlopba essen.
     * Soronként 2 sor távolság van köztük (hogy ne érjenek össze).
     */
    private function placeKeyWords(array $keyWords): void
    {
        $row = 2; // első sor, ahová írunk

        foreach ($keyWords as $info) {
            $word = $info['word'];
            $letterPos = $info['letter_pos'];
            $wordLen = mb_strlen($word);

            // Kívánt kezdőoszlop: megoldásoszlop - letterPos
            $startCol = $this->solutionCol - $letterPos;

            // Ha a szó balra kilógna, megpróbálunk másik előfordulást keresni
            if ($startCol < 1) {
                foreach ($this->findLetterPositions($word, $info['solution_letter']) as $altPos) {
                    $altStart = $this->solutionCol - $altPos;

                    if ($altStart >= 1 && $altStart + $wordLen <= $this->width - 2) {
                        $startCol = $altStart;
                        $letterPos = $altPos;
                        break;
                    }
                }

                // Ha még mindig nem jó, kényszermegoldás
                if ($startCol < 1) {
                    $startCol = 1;
                }
            }

            // Jobbra kilógás korrekció
            if ($startCol + $wordLen > $this->width - 2) {
                $startCol = $this->width - $wordLen - 2;
            }

            // Szabad sort keresünk (ha az aktuális foglalt lenne)
            while ($row < $this->height - 2 && !$this->isRowFreeForWord($row, $startCol, $wordLen)) {
                $row++;
            }

            if ($row >= $this->height - 2) {
                continue; // nem fért el – nagyon ritka
            }

            // Definíciós cella (a szó bal oldala előtt egy oszloppal)
            $this->setCell($row, $startCol - 1, [
                'type' => 'clue',
                'clue' => $info['definition'],
                'direction' => self::DIR_H,
            ]);

            // Betűk elhelyezése
            for ($j = 0; $j < $wordLen; $j++) {
                $col = $startCol + $j;
                $isSolutionCell = ($j === $letterPos);

                $this->setCell($row, $col, [
                    'type' => $isSolutionCell ? 'solution' : 'letter',
                    'letter' => mb_substr($word, $j, 1),
                    'solution_index' => $isSolutionCell ? $info['solution_index'] : null,
                ]);
            }

            $this->placedWords[] = [
                'word' => $word,
                'row' => $row,
                'col' => $startCol,
                'direction' => self::DIR_H,
                'definition' => $info['definition'],
                'is_key_word' => true,
                'solution_offset' => $letterPos,
            ];

            $row += 2; // következő kulcsszó 2 sorral lejjebb
        }
    }

    /** Ellenőrzi, hogy az adott sor adott tartományában el lehet-e helyezni egy szót. */
    private function isRowFreeForWord(int $row, int $startCol, int $len): bool
    {
        if ($startCol < 1 || $startCol + $len > $this->width - 1) {
            return false;
        }

        // A definíciós cella helye
        if ($this->getCell($row, $startCol - 1)['type'] !== 'empty') {
            return false;
        }

        // A szó cellái
        for ($c = $startCol; $c < $startCol + $len; $c++) {
            if ($this->getCell($row, $c)['type'] !== 'empty') {
                return false;
            }
        }

        // Szomszédos sorok ellenőrzése (ne legyenek közvetlen szomszédos párhuzamos szavak)
        for ($c = $startCol - 1; $c <= $startCol + $len; $c++) {
            if ($c < 0 || $c >= $this->width) continue;

            if ($row > 0 && in_array($this->getCell($row - 1, $c)['type'], ['letter', 'solution', 'clue'])) {
                return false;
            }

            if ($row < $this->height - 1 && in_array($this->getCell($row + 1, $c)['type'], ['letter', 'solution', 'clue'])) {
                return false;
            }
        }
        return true;
    }

    // -------------------------------------------------------------------------
    // 3. Fennmaradó szavak elhelyezése
    // -------------------------------------------------------------------------

    /**
     * A fennmaradó szavakat klasszikus keresztrejtvény-stílusban helyezi el:
     * először meglévő szóval való kereszteződést keres, majd szabad helyre tesz.
     */
    private function placeRemainingWords(array $remaining): void
    {
        // Hosszabb szavak először (jobb elhelyezési esély)
        uasort($remaining, static fn($a, $b) => mb_strlen($b) - mb_strlen($a));

        foreach ($remaining as $def => $word) {
            $placed = false;

            // Keressük a legjobb kereszteződési lehetőséget
            $candidates = $this->findCrossingCandidates($word);
            usort($candidates, static fn($a, $b) => $b['score'] - $a['score']);

            foreach ($candidates as $c) {
                if ($this->canPlaceWord($word, $c['row'], $c['col'], $c['direction'])) {
                    $this->doPlaceWord($word, $def, $c['row'], $c['col'], $c['direction'], false);
                    $placed = true;
                    break;
                }
            }

            // Ha nem találtunk kereszteződést, szabad helyre tesszük
            if (!$placed) {
                $this->placeWordFreeStyle($word, $def);
            }
        }
    }

    /**
     * Megkeresi az összes lehetséges kereszteződési pozíciót az újonnan
     * elhelyezendő szó és a már elhelyezett szavak között.
     */
    private function findCrossingCandidates(string $newWord): array
    {
        $candidates = [];
        $newLen = mb_strlen($newWord);

        foreach ($this->placedWords as $placed) {
            $placedWord = $placed['word'];
            $placedLen  = mb_strlen($placedWord);

            for ($ni = 0; $ni < $newLen; $ni++) {
                $nLetter = mb_substr($newWord, $ni, 1);
                for ($pi = 0; $pi < $placedLen; $pi++) {
                    if (mb_substr($placedWord, $pi, 1) !== $nLetter) {
                        continue;
                    }

                    // Merőleges elhelyezés
                    if ($placed['direction'] === self::DIR_H) {
                        // Új szó: függőleges; metszéspontja: (placed[row], placed[col]+pi)
                        $crossRow = $placed['row'];
                        $crossCol = $placed['col'] + $pi;

                        $candidates[] = [
                            'row' => $crossRow - $ni,
                            'col' => $crossCol,
                            'direction' => self::DIR_V,
                            // Belső metszés jobb (nem szélső betű)
                            'score' => ($pi > 0 && $pi < $placedLen - 1 ? 3 : 1) + ($ni > 0 && $ni < $newLen  - 1 ? 3 : 1),
                        ];
                    } else {
                        // Új szó: vízszintes
                        $crossRow = $placed['row'] + $pi;
                        $crossCol = $placed['col'];

                        $candidates[] = [
                            'row' => $crossRow,
                            'col' => $crossCol - $ni,
                            'direction' => self::DIR_H,
                            'score' => ($pi > 0 && $pi < $placedLen - 1 ? 3 : 1) + ($ni > 0 && $ni < $newLen  - 1 ? 3 : 1),
                        ];
                    }
                }
            }
        }

        return $candidates;
    }

    /**
     * Megvizsgálja, hogy egy szó elhelyezhető-e az adott pozícióba és irányba.
     * Szabályok:
     *  - A szó ne lógjon ki a rácsból.
     *  - Ahol a rácson már van betű, az pontosan egyezzen az elhelyezendő betűvel.
     *  - A szó előtt (H: bal oldal, V: felső oldal) legyen hely a definíciós cellának.
     *  - A szó után (H: jobb, V: lent) ne folytatódjon rögtön egy másik szó.
     *  - Párhuzamos szomszédos szavak ne érintkezzenek (üres cella elválasztó kell).
     */
    private function canPlaceWord(string $word, int $row, int $col, string $direction): bool
    {
        $len = mb_strlen($word);

        if ($direction === self::DIR_H) {
            // Rácshatár
            if ($col < 1 || $col + $len > $this->width - 1) return false;

            if ($row < 0 || $row >= $this->height) return false;

            // Definíciós cella
            $clueCell = $this->getCell($row, $col - 1);
            if (!in_array($clueCell['type'], ['empty', 'clue'])) return false;

            // Szó utáni cella
            $afterCell = $this->getCell($row, $col + $len);
            if (in_array($afterCell['type'], ['letter', 'solution'])) return false;

            // Szó cellái
            for ($i = 0; $i < $len; $i++) {
                $c = $col + $i;
                $cell = $this->getCell($row, $c);

                if ($cell['type'] === 'empty') {
                    // Szomszédos sorok: ne legyen párhuzamos szó közvetlen mellette
                    // (csak ha az adott cella EGYÁLTALÁN nem kapcsolódik egy merőleges szóhoz)
                    $above = ($row > 0) ? $this->getCell($row - 1, $c) : ['type' => 'empty'];
                    $below = ($row < $this->height - 1) ? $this->getCell($row + 1, $c) : ['type' => 'empty'];

                    // Párhuzamos szomszéd: az előző/következő cella is levél, ÉS az nincs kereszteződés
                    if (in_array($above['type'], ['letter', 'solution'])) return false;

                    if (in_array($below['type'], ['letter', 'solution'])) return false;

                } elseif (in_array($cell['type'], ['letter', 'solution'])) {
                    // Meglévő betűnek egyeznie kell
                    if ($cell['letter'] !== mb_substr($word, $i, 1)) return false;
                } else {
                    return false; // pl. 'clue' cellára írunk
                }
            }

        } else { // DIR_V
            if ($row < 1 || $row + $len > $this->height - 1) return false;
            if ($col < 0 || $col >= $this->width) return false;

            $clueCell = $this->getCell($row - 1, $col);
            if (!in_array($clueCell['type'], ['empty', 'clue'])) return false;

            $afterCell = $this->getCell($row + $len, $col);
            if (in_array($afterCell['type'], ['letter', 'solution'])) return false;

            for ($i = 0; $i < $len; $i++) {
                $r = $row + $i;
                $cell = $this->getCell($r, $col);

                if ($cell['type'] === 'empty') {
                    $left  = ($col > 0) ? $this->getCell($r, $col - 1) : ['type' => 'empty'];
                    $right = ($col < $this->width - 1) ? $this->getCell($r, $col + 1) : ['type' => 'empty'];

                    if (in_array($left['type'],  ['letter', 'solution'])) return false;

                    if (in_array($right['type'], ['letter', 'solution'])) return false;

                } elseif (in_array($cell['type'], ['letter', 'solution'])) {
                    if ($cell['letter'] !== mb_substr($word, $i, 1)) return false;
                } else {
                    return false;
                }
            }
        }

        return true;
    }

    /** Ténylegesen elhelyezi a szót a rácson. */
    private function doPlaceWord(
        string $word, string $def,
        int $row, int $col, string $direction,
        bool $isKeyWord
    ): void {
        $len = mb_strlen($word);

        if ($direction === self::DIR_H) {
            $this->setCell($row, $col - 1, ['type' => 'clue', 'clue' => $def, 'direction' => self::DIR_H]);

            for ($i = 0; $i < $len; $i++) {
                $c = $col + $i;

                if ($this->getCell($row, $c)['type'] === 'empty') {
                    $this->setCell($row, $c, ['type' => 'letter', 'letter' => mb_substr($word, $i, 1)]);
                }
                // Ha már van betű (kereszteződés), azt nem írjuk felül
            }
        } else {
            $this->setCell($row - 1, $col, ['type' => 'clue', 'clue' => $def, 'direction' => self::DIR_V]);

            for ($i = 0; $i < $len; $i++) {
                $r = $row + $i;

                if ($this->getCell($r, $col)['type'] === 'empty') {
                    $this->setCell($r, $col, ['type' => 'letter', 'letter' => mb_substr($word, $i, 1)]);
                }
            }
        }

        $this->placedWords[] = [
            'word' => $word,
            'row' => $row,
            'col' => $col,
            'direction' => $direction,
            'definition' => $def,
            'is_key_word' => $isKeyWord,
        ];
    }

    /** Ha nincs kereszteződési lehetőség, szabad helyre teszi a szót. */
    private function placeWordFreeStyle(string $word, string $def): bool
    {
        $len = mb_strlen($word);

        // Vízszintes próba (soronként, 2-es lépésközzel a sűrűség elkerüléséhez)
        for ($row = 2; $row < $this->height - 2; $row += 2) {
            for ($col = 1; $col + $len <= $this->width - 2; $col++) {
                if ($this->canPlaceWord($word, $row, $col, self::DIR_H)) {
                    $this->doPlaceWord($word, $def, $row, $col, self::DIR_H, false);
                    return true;
                }
            }
        }

        // Függőleges próba
        for ($col = 2; $col < $this->width - 2; $col += 2) {
            for ($row = 1; $row + $len <= $this->height - 2; $row++) {
                if ($this->canPlaceWord($word, $row, $col, self::DIR_V)) {
                    $this->doPlaceWord($word, $def, $row, $col, self::DIR_V, false);
                    return true;
                }
            }
        }

        return false; // nem fért el (hibajelzésként logolni ajánlott)
    }

    // -------------------------------------------------------------------------
    // Segédfüggvények – rács kezelés
    // -------------------------------------------------------------------------

    private function initGrid(): void
    {
        $this->grid = [];
        for ($r = 0; $r < $this->height; $r++) {
            for ($c = 0; $c < $this->width; $c++) {
                $this->grid[$r][$c] = ['type' => 'empty'];
            }
        }
    }

    private function setCell(int $row, int $col, array $data): void
    {
        if ($row >= 0 && $row < $this->height && $col >= 0 && $col < $this->width) {
            $this->grid[$row][$col] = $data;
        }
    }

    private function getCell(int $row, int $col): array
    {
        if ($row < 0 || $row >= $this->height || $col < 0 || $col >= $this->width) {
            return ['type' => 'wall']; // rácshatáron kívül
        }
        return $this->grid[$row][$col];
    }

    // -------------------------------------------------------------------------
    // Export
    // -------------------------------------------------------------------------

    /**
     * Visszaadja a kész rejtvény adatszerkezetét.
     * A rács a ténylegesen használt területre van vágva (padding: 1 cella).
     *
     * Visszatérési érték:
     * [
     *   'grid' => 2D tömb cellaadatokkal,
     *   'width' => oszlopszám,
     *   'height' => sorszám,
     *   'main_solution' => a főmegoldás szó,
     *   'solution_col' => a megoldásoszlop indexe a vágott rácsban,
     *   'placed_words' => elhelyezett szavak listája,
     *   'unplaced_words' => esetleg el nem helyezett szavak (hibakereséshez),
     * ]
     */
    private function exportPuzzle(string $mainSolution): array
    {
        // Határoló doboz meghatározása
        $minR = $this->height; $maxR = 0;
        $minC = $this->width;  $maxC = 0;

        for ($r = 0; $r < $this->height; $r++) {
            for ($c = 0; $c < $this->width; $c++) {
                if ($this->grid[$r][$c]['type'] !== 'empty') {
                    $minR = min($minR, $r);
                    $maxR = max($maxR, $r);
                    $minC = min($minC, $c);
                    $maxC = max($maxC, $c);
                }
            }
        }

        // 1 cella padding
        $minR = max(0, $minR - 1);
        $minC = max(0, $minC - 1);
        $maxR = min($this->height - 1, $maxR + 1);
        $maxC = min($this->width  - 1, $maxC + 1);

        // Rács exportálása
        $exportGrid = [];
        for ($r = $minR; $r <= $maxR; $r++) {
            $row = [];

            for ($c = $minC; $c <= $maxC; $c++) {
                $row[] = $this->grid[$r][$c];
            }

            $exportGrid[] = $row;
        }

        // Elhelyezett szavak koordinátáinak korrekciója
        $adjustedWords = [];
        foreach ($this->placedWords as $pw) {
            $adjustedWords[] = array_merge($pw, [
                'row' => $pw['row'] - $minR,
                'col' => $pw['col'] - $minC,
            ]);
        }

        return [
            'grid' => $exportGrid,
            'width' => $maxC - $minC + 1,
            'height' => $maxR - $minR + 1,
            'main_solution' => $mainSolution,
            'solution_col' => $this->solutionCol - $minC,
            'placed_words' => $adjustedWords,
        ];
    }
}