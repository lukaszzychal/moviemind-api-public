<?php

declare(strict_types=1);

namespace Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class HasCollectionProperty extends Model
{
    protected Collection $items;

    public function __construct()
    {
        $this->items = new Collection;
    }

    public function getItems(): Collection
    {
        return $this->items;
    }
}
