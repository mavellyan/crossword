<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CrosswordResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // $allWords = array_merge([$this->getMainSolution()], $this->getWords());

        return [
            'id' => 1, // Ez majd egy adatbázisban tárolt rejtvény esetén egyedi azonosító lesz
            'main_solution' => $this->getMainSolution()->getSolution(),
            'grid' => $this->generateGrid(),
            // 'words' => CrosswordClueResource::collection($allWords),
            'words' => CrosswordClueResource::collection($this->getWords()),
            'width' => $this->getWidth(),
            'height' => $this->getHeight(),
        ];
    }
}
