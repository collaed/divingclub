<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string|null $category
 * @property string|null $cert_type
 * @property string|null $file_path
 * @property string|null $original_filename
 * @property string|null $mime_type
 * @property string|null $size_bytes
 * @property Carbon|null $date_established
 * @property Carbon|null $expiry_date
 * @property bool $is_verified
 * @property string|null $verified_by
 * @property Carbon|null $verified_at
 * @property string|null $superseded_by
 * @property bool $is_current
 * @property bool $is_compliant
 * @property string|null $compliance_notes
 * @property Carbon|null $reminder_30_sent_at
 * @property Carbon|null $reminder_15_sent_at
 * @property Carbon|null $reminder_7_sent_at
 * @property Carbon|null $reminder_0_sent_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $user
 */
class Document extends Model
{
    use Auditable;
    use SoftDeletes;

    protected $fillable = ['user_id', 'category', 'cert_type', 'file_path', 'original_filename', 'mime_type', 'size_bytes', 'date_established', 'expiry_date', 'is_verified', 'verified_by', 'verified_at', 'superseded_by', 'is_current', 'is_compliant', 'compliance_notes', 'reminder_30_sent_at', 'reminder_15_sent_at', 'reminder_7_sent_at', 'reminder_0_sent_at'];

    protected function casts(): array
    {
        return [
            'date_established' => 'date',
            'expiry_date' => 'date',
            'verified_at' => 'datetime',
            'is_verified' => 'boolean',
            'is_current' => 'boolean',
            'is_compliant' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function supersededBy(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'superseded_by');
    }

    public function isExpired(): bool
    {
        return $this->expiry_date && $this->expiry_date->isPast();
    }

    public function daysUntilExpiry(): ?int
    {
        return $this->expiry_date ? (int) now()->startOfDay()->diffInDays($this->expiry_date->startOfDay(), false) : null;
    }
}
