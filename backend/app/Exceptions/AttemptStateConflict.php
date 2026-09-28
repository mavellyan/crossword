<?php

namespace App\Exceptions;

use RuntimeException;
use Illuminate\Http\JsonResponse;

class AttemptStateConflict extends RuntimeException
{
    public function render(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $this->getMessage(),
        ], 409);
    }
}