<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @deprecated This model is replaced by Person model.
 *
 * @todo REMOVE: This model and ActorBio should be removed after migration is complete.
 * Currently only used in ActorToPersonSyncSeeder for data migration.
 * All new code should use Person/PersonBio instead.
 */
class Actor extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'birth_date',
        'birthplace',
        'default_bio_id',
    ];

    protected $casts = [
        'birth_date' => 'date',
    ];

    public function bios(): HasMany
    {
        return $this->hasMany(ActorBio::class);
    }

    public function defaultBio(): HasOne
    {
        return $this->hasOne(ActorBio::class, 'id', 'default_bio_id');
    }
}
