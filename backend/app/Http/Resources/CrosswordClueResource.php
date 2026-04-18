<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CrosswordClueResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return  [
                'definition' => $this->getDefinition(),
                'solution' => $this->getSolution(),
                'x_pos' => $this->getXPos(),
                'y_pos' => $this->getYPos(),
                'direction' => $this->getDirection(),
                'cells' => $this->getCells(),
                'is_main' => $this->isMain(),
            ];
    }
}
