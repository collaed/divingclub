<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Document extends Model
{
    use \App\Traits\Auditable;
    protected $guarded = ['id'];

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

    public function user() { return $this->belongsTo(User::class); }
    public function verifier() { return $this->belongsTo(User::class, 'verified_by'); }
    public function supersededBy() { return $this->belongsTo(Document::class, 'superseded_by'); }

    public function isExpired(): bool
    {
        return $this->expiry_date && $this->expiry_date->isPast();
    }

    public function daysUntilExpiry(): ?int
    {
        return $this->expiry_date ? (int) now()->startOfDay()->diffInDays($this->expiry_date->startOfDay(), false) : null;
    }
}
