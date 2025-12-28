<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ReportStatus;
use App\Enums\ReportType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * TvSeriesReport model.
 *
 * @property string $id UUIDv7 primary key
 * @property string $tv_series_id UUIDv7 foreign key
 * @property string|null $description_id UUIDv7 foreign key
 * @property ReportType $type Report type enum
 * @property string $message Report message
 * @property string|null $suggested_fix Suggested fix
 * @property ReportStatus $status Report status enum
 * @property float $priority_score Priority score
 * @property string|null $verified_by User ID who verified
 * @property \Illuminate\Support\Carbon|null $verified_at Verification timestamp
 * @property \Illuminate\Support\Carbon|null $resolved_at Resolution timestamp
 */
class TvSeriesReport extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'tv_series_id',
        'description_id',
        'type',
        'message',
        'suggested_fix',
        'status',
        'priority_score',
        'verified_by',
        'verified_at',
        'resolved_at',
    ];

    protected $casts = [
        'type' => ReportType::class,
        'status' => ReportStatus::class,
        'priority_score' => 'decimal:2',
        'verified_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    /**
     * Get the tv series that this report belongs to.
     */
    public function tvSeries(): BelongsTo
    {
        return $this->belongsTo(TvSeries::class);
    }

    /**
     * Get the description that this report refers to (if any).
     */
    public function description(): BelongsTo
    {
        return $this->belongsTo(TvSeriesDescription::class, 'description_id');
    }

    /**
     * Check if report is pending.
     */
    public function isPending(): bool
    {
        return $this->status === ReportStatus::PENDING;
    }

    /**
     * Check if report is verified.
     */
    public function isVerified(): bool
    {
        return $this->status === ReportStatus::VERIFIED;
    }

    /**
     * Check if report is resolved.
     */
    public function isResolved(): bool
    {
        return $this->status === ReportStatus::RESOLVED;
    }
}
