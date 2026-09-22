<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\CrosswordClueResource;

class CrosswordResource extends JsonResource
{
    /**
     * Visszaadja a rejtvény adatait egy tömb formátumban a frontendnek.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $crossword = $this->resource['crossword'];

        return [
            'id' => $crossword->id,
            'title' => $crossword->title,
            'creator' => $crossword->creator?->username ?? 'Rendszer',
            'grid' => $this->resource['grid'],
            'words' => CrosswordClueResource::collection($crossword->getWords()),
            'width' => $this->resource['width'],
            'height' => $this->resource['height'],
            'best_time' => $this->resource['best_time'] ?? null,
        ];
    }
}