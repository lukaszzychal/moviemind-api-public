<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

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
