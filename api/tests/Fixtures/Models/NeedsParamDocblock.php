<?php

declare(strict_types=1);

namespace Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;

class NeedsParamDocblock extends Model
{
    public function setRating($rating): void
    {
        $this->attributes['rating'] = $rating;
    }
}
