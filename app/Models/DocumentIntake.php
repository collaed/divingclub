<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One uploaded document dropped on the shared "Document Intake" screen —
 * classified (licence scan vs bank statement) and routed to whichever
 * existing pipeline handles that type, so the licence-scan and bank-
 * reconciliation review flows stay untouched. This row is just the record
 * of what was assumed and what happened, for a bureau member to check and
 * correct if the guess was wrong.
 *
 * @property int $id
 * @property string $original_filename
 * @property string $file_path
 * @property string|null $detected_type
 * @property string|null $detection_reason
 * @property int|null $federation_id
 * @property string $status
 * @property string|null $error
 * @property int|null $licence_scan_id
 * @property int|null $transactions_created
 * @property int|null $uploaded_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class DocumentIntake extends Model
{
    protected $fillable = [
        'original_filename', 'file_path', 'detected_type', 'detection_reason',
        'federation_id', 'status', 'error', 'licence_scan_id', 'transactions_created', 'uploaded_by',
    ];

    public const TYPES = ['licence_scan', 'bank_statement'];

    /** @return BelongsTo<Federation, $this> */
    public function federation(): BelongsTo
    {
        return $this->belongsTo(Federation::class);
    }

    /** @return BelongsTo<LicenceScan, $this> */
    public function licenceScan(): BelongsTo
    {
        return $this->belongsTo(LicenceScan::class);
    }

    /** @return BelongsTo<User, $this> */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
