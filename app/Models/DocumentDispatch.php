<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A one-off send of a library PDF to a chosen set of members, with a unique
 * tracked link per recipient. Opens are recorded and kept indefinitely
 * (bureau_master only).
 *
 * @property int $id
 * @property int $library_file_id
 * @property int|null $created_by
 * @property string $subject
 * @property string|null $message
 * @property string|null $recipient_summary
 */
class DocumentDispatch extends Model
{
    protected $fillable = ['library_file_id', 'created_by', 'subject', 'message', 'recipient_summary'];

    /** @return BelongsTo<LibraryFile, $this> */
    public function file(): BelongsTo
    {
        return $this->belongsTo(LibraryFile::class, 'library_file_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<DocumentDispatchRecipient, $this> */
    public function recipients(): HasMany
    {
        return $this->hasMany(DocumentDispatchRecipient::class, 'dispatch_id');
    }
}
