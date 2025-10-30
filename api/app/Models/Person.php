<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Person extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'slug', 'birth_date', 'birthplace',
    ];

    protected $casts = [
        'birth_date' => 'date',
    ];

    public function movies(): BelongsToMany
    {
        return $this->belongsToMany(Movie::class, 'movie_person')
            ->withPivot(['role', 'character_name', 'job', 'billing_order']);
    }

    public function bios(): HasMany
    {
        return $this->hasMany(PersonBio::class);
    }

    public function defaultBio(): HasOne
    {
        return $this->hasOne(PersonBio::class, 'id', 'default_bio_id');
    }
}
