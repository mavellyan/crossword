<?php

namespace App\Services;

use App\Enums\ValidationErrors;
use App\Exceptions\InvalidCrosswordLayout;
use App\Models\Clue;

final class CrosswordTopicConsistencyValidator
{
    public function assertValid(array $clueIds, array $topicIds): void
    {
        $clueIds = array_values(array_unique(array_map('intval', $clueIds)));
        $topicIds = array_values(array_unique(array_map('intval', $topicIds)));

        // A topic nélküli crossword nincs témához korlátozva.
        if ($clueIds === [] || $topicIds === []) {
            return;
        }

        $invalidClueIds = Clue::query()
            ->whereIn('id', $clueIds)
            ->whereDoesntHave('topics', function ($query) use ($topicIds) {
                $query->whereIn('topics.id', $topicIds);
            })
            ->pluck('id')
            ->all();

        if ($invalidClueIds === []) {
            return;
        }

        throw new InvalidCrosswordLayout([
            [
                'code' => ValidationErrors::CLUE_TOPIC_MISMATCH,
                'clue_ids' => $invalidClueIds,
                'message' => 'A rejtvény egyik szava nem tartozik a kiválasztott témák egyikéhez.',
            ],
        ]);
    }
}