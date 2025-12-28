<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RelationshipType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * TvSeries relationship model.
 *
 * @author MovieMind API Team
 *
 * @property string $id UUIDv7 primary key
 * @property string $tv_series_id UUIDv7 foreign key
 * @property string $related_tv_series_id UUIDv7 foreign key
 */
class TvSeriesRelationship extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'tv_series_id',
        'related_tv_series_id',
        'relationship_type',
        'order',
    ];

    protected $casts = [
        'relationship_type' => RelationshipType::class,
        'order' => 'integer',
    ];

    /**
     * Get the tv series that has this relationship.
     */
    public function tvSeries(): BelongsTo
    {
        return $this->belongsTo(TvSeries::class, 'tv_series_id');
    }

    /**
     * Get the related tv series.
     */
    public function relatedTvSeries(): BelongsTo
    {
        return $this->belongsTo(TvSeries::class, 'related_tv_series_id');
    }
}
