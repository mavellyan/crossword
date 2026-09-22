<?php

namespace App\Exceptions;

use RuntimeException;

class InvalidCrosswordLayout extends RuntimeException
{
    public function __construct(public readonly array $layoutErrors) {
        parent::__construct('A rejtvény elrendezése érvénytelen.');
    }
}