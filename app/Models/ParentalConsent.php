<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $minor_user_id
 * @property string|null $granted_by
 * @property string|null $consent_type
 * @property bool $granted
 * @property string|null $document_path
 * @property Carbon|null $granted_at
 * @property Carbon|null $revoked_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ParentalConsent extends Model
{
    use Auditable;

    protected $fillable = ['minor_user_id', 'granted_by', 'consent_type', 'granted', 'document_path', 'granted_at', 'revoked_at'];

    protected function casts(): array
    {
        return ['granted' => 'boolean', 'granted_at' => 'datetime', 'revoked_at' => 'datetime'];
    }

    public function minor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'minor_user_id');
    }

    public function grantedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by');
    }
}
