<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id UUIDv7 primary key
 * @property string $entity_type
 * @property string $entity_id UUIDv7 foreign key (references movies.id or people.id)
 * @property int $tmdb_id
 * @property string $tmdb_type
 * @property array $raw_data
 * @property \Illuminate\Support\Carbon $fetched_at
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class TmdbSnapshot extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'entity_type',
        'entity_id',
        'tmdb_id',
        'tmdb_type',
        'raw_data',
        'fetched_at',
    ];

    protected $casts = [
        'entity_id' => 'string', // Changed from integer to string (UUID)
        'tmdb_id' => 'integer',
        'raw_data' => 'array',
        'fetched_at' => 'datetime',
    ];
}
