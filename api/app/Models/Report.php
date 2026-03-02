<?php

namespace App\Models;

use App\Enums\ReportStatus;
use Illuminate\Database\Eloquent\Model;

class Report extends Model
{
    protected $table = 'all_reports';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $casts = [
        'priority_score' => 'float',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'verified_at' => 'datetime',
        'resolved_at' => 'datetime',
        'status' => ReportStatus::class,
    ];

    // Read-only model
    public function save(array $options = [])
    {
        return false;
    }

    public function update(array $attributes = [], array $options = [])
    {
        return false;
    }

    public function delete()
    {
        return false;
    }
}
