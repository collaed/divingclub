<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LibraryFile extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_public' => 'boolean'];
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function scopePublic($q)
    {
        return $q->where('is_public', true);
    }

    public function scopeInFolder($q, string $folder)
    {
        return $q->where('folder', $folder);
    }

    public function humanSize(): string
    {
        $bytes = $this->size;
        if ($bytes < 1024) {
            return $bytes . ' B';
        }
        if ($bytes < 1048576) {
            return round($bytes / 1024, 1) . ' KB';
        }

        return round($bytes / 1048576, 1) . ' MB';
    }
}
