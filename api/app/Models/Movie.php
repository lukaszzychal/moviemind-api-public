<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Movie extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'release_year',
        'director',
        'genres',
        'default_description_id',
    ];

    protected $casts = [
        'genres' => 'array',
    ];

    public function descriptions(): HasMany
    {
        return $this->hasMany(MovieDescription::class);
    }

    public function defaultDescription(): HasOne
    {
        return $this->hasOne(MovieDescription::class, 'id', 'default_description_id');
    }

    public function genres(): BelongsToMany
    {
        return $this->belongsToMany(Genre::class, 'movie_genre');
    }

    public function people(): BelongsToMany
    {
        return $this->belongsToMany(Person::class, 'movie_person')
            ->withPivot(['role', 'character_name', 'job', 'billing_order']);
    }
}


