<?php

namespace App\Features;

/**
 * Wymuszanie glosariusza (terminy, których nie tłumaczymy).
 */
class glossary_enforcement
{
    public function resolve(mixed $scope): mixed
    {
        return false;
    }
}
