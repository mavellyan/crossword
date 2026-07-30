<?php

namespace App\Http\Resources;

use App\Enums\Direction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CrosswordClueResource extends JsonResource
{
    /**
     * Visszaadja a szavak adatait egy tömb formátumban a frontendnek.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'placement_id' => (int) $this->id,
            'definition' => $this->clue?->definition,
            'solution' => $this->clue?->solution,
            'x_pos' => (int) $this->start_col,
            'y_pos' => (int) $this->start_row,
            'direction' => $this->getDirectionValue(),
            'cells' => $this->getCellsForFrontend(),
            'is_main' => (bool) $this->is_main,
        ];
    }

    private function getDirectionValue(): string
    {
        if ($this->direction instanceof Direction) {
            return $this->direction->value;
        }

        return (string) $this->direction;
    }

    private function getCellsForFrontend(): array
    {
        $cells = [];

        $solution = mb_strtoupper((string) $this->clue?->solution);
        $direction = $this->getDirectionValue();

        for ($i = 0; $i < mb_strlen($solution); $i++) {
            $cells[] = [
                'row' => (int) $this->start_row + ($direction === Direction::VERTICAL->value ? $i : 0),
                'col' => (int) $this->start_col + ($direction === Direction::HORIZONTAL->value ? $i : 0),
            ];
        }

        return $cells;
    }
}