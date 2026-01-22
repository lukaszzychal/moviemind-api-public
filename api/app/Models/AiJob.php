<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiJob extends Model
{
    protected $table = 'ai_jobs';

    protected $fillable = [
        'entity_type',
        'entity_id',
        'locale',
        'status',
        'context_tag',
        'payload_json',
    ];

    protected $casts = [
        'payload_json' => 'array',
    ];
}
