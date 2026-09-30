<?php

namespace App\Services;

use App\Models\Crossword;
use App\Models\CrosswordClue;
use App\Domain\Crossword\Placement;
use App\Enums\Direction;
use App\Exceptions\InvalidCrosswordLayout;
use App\Enums\ValidationErrors;

final class CrosswordPublicationValidator
{
    public function __construct(
        private readonly PlacementValidator $placementValidator,
        private readonly CrosswordGenerator $crosswordGenerator,
        private readonly CrosswordTopicConsistencyValidator $topicConsistencyValidator,
    ) {
    }

    public function assertPublishable(Crossword $crossword): void
    {
        $crossword->loadMissing('crosswordClues.clue');

        if ($crossword->title === null || trim((string) $crossword->title) === '') {
            throw new InvalidCrosswordLayout([
                [
                    'code' => ValidationErrors::MISSING_TITLE,
                    'message' => 'A rejtvénynek rendelkeznie kell címmel a közzétételhez.',
                ],
            ]);
        }

        $placements = $crossword->crosswordClues
            ->where('is_main', false)
            ->map(function (CrosswordClue $entry) {
                if (!$entry->clue) {
                    throw new InvalidCrosswordLayout([
                        [
                            'code' => ValidationErrors::MISSING_CLUE,
                            'entry_id' => $entry->id,
                            'message' => 'Az egyik bejegyzéshez nem tartozik meghatározás.',
                        ],
                    ]);
                }

                if ($entry->clue->solution === null || trim((string) $entry->clue->solution) === '') {
                    throw new InvalidCrosswordLayout([
                        [
                            'code' => ValidationErrors::MISSING_SOLUTION,
                            'entry_id' => $entry->id,
                            'message' => 'Az egyik bejegyzéshez nem tartozik megoldás.',
                        ],
                    ]);
                }

                if ($entry->clue->definition === null || trim((string) $entry->clue->definition) === '') {
                    throw new InvalidCrosswordLayout([
                        [
                            'code' => ValidationErrors::MISSING_DEFINITION,
                            'entry_id' => $entry->id,
                            'message' => 'Az egyik bejegyzéshez nem tartozik meghatározás.',
                        ],
                    ]);
                }

                return new Placement(
                    id: $entry->id,
                    clueId: $entry->clue_id,
                    answer: mb_strtoupper($entry->clue->solution),
                    direction: Direction::from($entry->getDirection()),
                    startRow: $entry->start_row,
                    startCol: $entry->start_col,
                );
            })
            ->values()
            ->all();

        if (count($placements) < 2) {
            throw new InvalidCrosswordLayout([
                [
                    'code' => ValidationErrors::TOO_FEW_ENTRIES,
                    'message' => 'A rejtvénynek legalább két bejegyzést kell tartalmaznia a közzétételhez.',
                ],
            ]);
        }

        if (count(array_unique(array_map(fn ($placement) => $placement->clueId, $placements))) < count($placements)) {
            throw new InvalidCrosswordLayout([
                [
                    'code' => ValidationErrors::DUPLICATE_ENTRY,
                    'message' => 'Egy bejegyzés nem szerepelhet többször ugyanabban a rejtvényben.',
                ],
            ]);
        }

        if ($crossword->main_solution === null || trim((string) $crossword->main_solution) === '') {
            $result = $this->placementValidator->validateLayout($placements);
        } else {
            $result = $this->placementValidator->validateGuidedLayout($placements, mb_strtoupper((string) $crossword->main_solution));
        }

        if (!$result->valid) {
            throw new InvalidCrosswordLayout($result->errors);
        }

        $this->topicConsistencyValidator->assertValid(
            array_map(
                fn ($placement) => $placement->clueId,
                $placements
            ),
            $crossword->topics()->pluck('topics.id')->all(),
        );

        $grid = $this->crosswordGenerator->generateGrid($crossword->getWords());

        if ($grid['width'] > 20 || $grid['height'] > 20) {
            throw new InvalidCrosswordLayout([
                [
                    'code' => ValidationErrors::OUT_OF_BOUNDS,
                    'message' => 'A rejtvény rácsa nem lehet nagyobb, mint 20x20 mező.',
                ],
            ]);
        }
    }
}