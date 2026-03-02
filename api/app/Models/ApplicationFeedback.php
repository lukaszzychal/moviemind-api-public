<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Anonymous application feedback (no personal data).
 *
 * @property string $id
 * @property string $message
 * @property string|null $category
 * @property string $status
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class ApplicationFeedback extends Model
{
    use HasUuids;

    protected $table = 'application_feedback';

    protected $fillable = [
        'message',
        'category',
        'status',
    ];

    public const STATUS_PENDING = 'pending';

    public const STATUS_READ = 'read';

    public const STATUS_ARCHIVED = 'archived';

    public static function statuses(): array
    {
        return [
            self::STATUS_PENDING,
            self::STATUS_READ,
            self::STATUS_ARCHIVED,
        ];
    }
}
