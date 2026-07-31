<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $event_id
 * @property int|null $user_id
 * @property float $amount
 * @property float|null $approved_amount
 * @property string $category
 * @property string|null $description
 * @property string|null $image_path
 * @property string $status
 * @property bool $is_third_party
 * @property string|null $reviewer_notes
 * @property int|null $reviewed_by
 * @property Carbon|null $reviewed_at
 */
class TripReceipt extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'user_id',
        'amount',
        'approved_amount',
        'category',
        'description',
        'image_path',
        'status',
        'is_third_party',
        'reviewer_notes',
        'reviewed_by',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'approved_amount' => 'decimal:2',
            'is_third_party' => 'boolean',
            'reviewed_at' => 'datetime',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }
}
