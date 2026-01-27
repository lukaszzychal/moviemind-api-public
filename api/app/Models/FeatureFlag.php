<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FeatureFlag extends Model
{
    protected $table = 'features';

    // Pennant's default migration uses a composite unique key (name, scope).
    // Eloquent doesn't support composite primary keys natively, so we must override key handling.

    // We now have an 'id' column, so we can use standard Eloquent behavior.
    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    protected $fillable = [
        'name',
        'scope',
        'value',
    ];

    protected $casts = [
        'value' => 'json',
    ];
}
