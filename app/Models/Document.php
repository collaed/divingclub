<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

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
