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
 * PersonReport model.
 *
 * @property string $id UUIDv7 primary key
 * @property string $person_id UUIDv7 foreign key
 * @property string|null $bio_id UUIDv7 foreign key
 * @property ReportType $type Report type enum
 * @property string $message Report message
 * @property string|null $suggested_fix Suggested fix
 * @property ReportStatus $status Report status enum
 * @property float $priority_score Priority score
 * @property string|null $verified_by User ID who verified
 * @property \Illuminate\Support\Carbon|null $verified_at Verification timestamp
 * @property \Illuminate\Support\Carbon|null $resolved_at Resolution timestamp
 */
class PersonReport extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'person_id',
        'bio_id',
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
     * Get the person that this report belongs to.
     */
    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    /**
     * Get the bio that this report refers to (if any).
     */
    public function bio(): BelongsTo
    {
        return $this->belongsTo(PersonBio::class, 'bio_id');
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
