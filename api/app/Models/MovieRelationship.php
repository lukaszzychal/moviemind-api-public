<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RelationshipType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Movie relationship model.
 *
 * @author MovieMind API Team
 *
 * @property string $id UUIDv7 primary key
 * @property string $movie_id UUIDv7 foreign key
 * @property string $related_movie_id UUIDv7 foreign key
 */
class MovieRelationship extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'movie_id',
        'related_movie_id',
        'relationship_type',
        'order',
    ];

    protected $casts = [
        'relationship_type' => RelationshipType::class,
        'order' => 'integer',
    ];

    /**
     * Get the movie that has this relationship.
     */
    public function movie(): BelongsTo
    {
        return $this->belongsTo(Movie::class, 'movie_id');
    }

    /**
     * Get the related movie.
     */
    public function relatedMovie(): BelongsTo
    {
        return $this->belongsTo(Movie::class, 'related_movie_id');
    }
}
