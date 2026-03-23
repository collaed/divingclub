<?php

/**
 * Library file with role-based visibility (public, members, instructors, bureau).
 *
 * @author ClubCEP.eu
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LibraryFile extends Model
{
    protected $fillable = ['filename', 'original_name', 'path', 'mime_type', 'size', 'folder', 'visibility', 'description', 'uploaded_by'];

    // Visibility levels ordered from most to least restrictive
    const VISIBILITY_OPTIONS = ['public', 'members', 'instructors', 'bureau'];

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /** Files visible to the given user (or public if null). */
    public function scopeVisibleTo($q, ?User $user)
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

    public function scopePublic($q)
    {
        return $q->where('visibility', 'public');
    }

    public function scopeInFolder($q, string $folder)
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
