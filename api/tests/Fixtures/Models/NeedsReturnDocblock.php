<?php

declare(strict_types=1);

namespace Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;

class NeedsReturnDocblock extends Model
{
    public function getRating()
    {
        return $this->attributes['rating'] ?? null;
    }
}
