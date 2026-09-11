<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One uploaded federation-licence scan going through the extract-and-assign
 * pipeline: rendered to an image, read by ProcessLicenceScan, then either
 * auto-applied to an exact single member match or left for manual review.
 *
 * @property int $id
 * @property int $federation_id
 * @property string $original_filename
 * @property string $file_path
 * @property string|null $image_path
 * @property string|null $extracted_name
 * @property string|null $extracted_number
 * @property string|null $extracted_year
 * @property int|null $matched_user_id
 * @property string $status
 * @property string|null $extraction_error
 * @property int|null $reviewed_by
 * @property Carbon|null $reviewed_at
 * @property int $uploaded_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class LicenceScan extends Model
{
    protected $fillable = [
        'federation_id', 'original_filename', 'file_path', 'image_path',
        'extracted_name', 'extracted_number', 'extracted_year',
        'matched_user_id', 'status', 'extraction_error',
        'reviewed_by', 'reviewed_at', 'uploaded_by',
    ];

    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime'];
    }

    public function isPendingReview(): bool
    {
        return $this->status === 'needs_review';
    }

    /** @return BelongsTo<Federation, $this> */
    public function federation(): BelongsTo
    {
        return $this->belongsTo(Federation::class);
    }

    /** @return BelongsTo<User, $this> */
    public function matchedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'matched_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** @return BelongsTo<User, $this> */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
