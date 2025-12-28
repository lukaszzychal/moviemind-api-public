<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RelationshipType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * TvShow relationship model.
 *
 * @author MovieMind API Team
 *
 * @property string $id UUIDv7 primary key
 * @property string $tv_show_id UUIDv7 foreign key
 * @property string $related_tv_show_id UUIDv7 foreign key
 */
class TvShowRelationship extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'tv_show_id',
        'related_tv_show_id',
        'relationship_type',
        'order',
    ];

    protected $casts = [
        'relationship_type' => RelationshipType::class,
        'order' => 'integer',
    ];

    /**
     * Get the tv show that has this relationship.
     */
    public function tvShow(): BelongsTo
    {
        return $this->belongsTo(TvShow::class, 'tv_show_id');
    }

    /**
     * Get the related tv show.
     */
    public function relatedTvShow(): BelongsTo
    {
        return $this->belongsTo(TvShow::class, 'related_tv_show_id');
    }
}
