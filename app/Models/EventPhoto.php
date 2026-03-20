<?php

/**
 * Event photo with quality scoring, face detection, and GDPR consent tracking.
 *
 * Photos with detected faces are excluded from public/anonymous display
 * (homepage, unauthenticated pages) but shown to authenticated members.
 *
 * @author ClubCEP.eu
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventPhoto extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['approved' => 'boolean', 'gdpr_consent' => 'boolean', 'has_faces' => 'boolean'];
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function socialPublishLogs()
    {
        return $this->morphMany(SocialPublishLog::class, 'publishable');
    }

    /** Best approved photos safe for public/anonymous display (no faces, no banned uploaders). */
    public function scopeBestPublic($q, int $limit = 10)
    {
        return $q->where('approved', true)
            ->where('gdpr_consent', true)
            ->where(fn ($q) => $q->where('has_faces', false)->orWhereNull('has_faces'))
            ->whereDoesntHave('uploader', fn ($q) => $q->whereHas('detail', fn ($d) => $d->where('public_photos_banned', true)))
            ->orderByDesc('quality_score')
            ->limit($limit);
    }

    /** Weighted-random public photos — favours high quality_score. */
    public function scopeRandomPublic($q, int $limit = 10)
    {
        return $q->where('approved', true)
            ->where('gdpr_consent', true)
            ->where(fn ($q) => $q->where('has_faces', false)->orWhereNull('has_faces'))
            ->whereDoesntHave('uploader', fn ($q) => $q->whereHas('detail', fn ($d) => $d->where('public_photos_banned', true)))
            ->orderByRaw('-(quality_score * quality_score) * LOG(RAND())')
            ->limit($limit);
    }

    /** Best approved photos for authenticated members (faces allowed). */
    public function scopeBestForMembers($q, int $limit = 10)
    {
        return $q->where('approved', true)
            ->where('gdpr_consent', true)
            ->orderByDesc('quality_score')
            ->limit($limit);
    }

    /** Weighted-random photos for authenticated members (faces allowed). */
    public function scopeRandomForMembers($q, int $limit = 10)
    {
        return $q->where('approved', true)
            ->where('gdpr_consent', true)
            ->orderByRaw('-(quality_score * quality_score) * LOG(RAND())')
            ->limit($limit);
    }

    public function getUrlAttribute(): string
    {
        return asset('storage/'.$this->path);
    }

    public function getThumbUrlAttribute(): string
    {
        return $this->thumbnail_path
            ? asset('storage/'.$this->thumbnail_path)
            : $this->url;
    }
}
