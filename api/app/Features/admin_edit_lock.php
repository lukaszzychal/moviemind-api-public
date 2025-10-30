<?php

namespace App\Features;

/**
 * Blokady edycji podczas operacji wsadowych/administracyjnych.
 */
class admin_edit_lock
{
    public function resolve(mixed $scope): mixed
    {
        return false;
    }
}


