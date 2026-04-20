<?php

/**
 * Library file with role-based visibility (public, members, instructors, bureau).
 *
 * @author ClubCEP.eu
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string|null $filename
 * @property string|null $original_name
 * @property string|null $path
 * @property string|null $mime_type
 * @property string|null $size
 * @property string|null $folder
 * @property string|null $visibility
 * @property string|null $description
 * @property string|null $uploaded_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class LibraryFile extends Model
{
    protected $fillable = ['filename', 'original_name', 'path', 'mime_type', 'size', 'folder', 'visibility', 'description', 'uploaded_by'];

    // Visibility levels ordered from most to least restrictive
    const VISIBILITY_OPTIONS = ['public', 'members', 'instructors', 'bureau'];

    /** @return BelongsTo<User, $this> */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /** Files visible to the given user (or public if null). */
    public function scopeVisibleTo(Builder $q, ?User $user): Builder
    {
        if (! $user) {
            return $q->where('visibility', 'public');
        }
        if ($user->isBureau()) {
            return $q; // bureau sees everything
        }
        if ($user->hasAnyRole(['instructor'])) {
            return $q->whereIn('visibility', ['public', 'members', 'instructors']);
        }

        return $q->whereIn('visibility', ['public', 'members']);
    }

    public function scopePublic(Builder $q): Builder
    {
        return $q->where('visibility', 'public');
    }

    public function scopeInFolder(Builder $q, string $folder): Builder
    {
        return $q->where('folder', $folder);
    }

    public function humanSize(): string
    {
        $bytes = $this->size;
        if ($bytes < 1024) {
            return $bytes.' B';
        }
        if ($bytes < 1048576) {
            return round($bytes / 1024, 1).' KB';
        }

        return round($bytes / 1048576, 1).' MB';
    }

    public function hasThumb(): bool
    {
        return str_starts_with($this->mime_type, 'image/') || $this->mime_type === 'application/pdf';
    }

    /** Can the given user manage (upload/delete) files? Bureau + instructors. */
    public static function canManage(?User $user): bool
    {
        return $user && ($user->isBureau() || $user->hasAnyRole(['instructor']));
    }
}
