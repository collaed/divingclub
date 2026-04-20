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

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * @property int $id
 * @property int|null $event_id
 * @property string|null $uploaded_by
 * @property string|null $path
 * @property string|null $thumbnail_path
 * @property string|null $caption
 * @property string|null $quality_score
 * @property bool $has_faces
 * @property bool $approved
 * @property bool $gdpr_consent
 * @property string|null $file_hash
 * @property int $view_count
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class EventPhoto extends Model
{
    protected $fillable = ['event_id', 'uploaded_by', 'path', 'thumbnail_path', 'caption', 'quality_score', 'has_faces', 'approved', 'gdpr_consent', 'file_hash', 'view_count'];

    protected function casts(): array
    {
        return ['approved' => 'boolean', 'gdpr_consent' => 'boolean', 'has_faces' => 'boolean', 'view_count' => 'integer'];
    }

    /** @return BelongsTo<Event, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /** @return BelongsTo<User, $this> */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /** @return MorphMany<SocialPublishLog, $this> */
    public function socialPublishLogs(): MorphMany
    {
        return $this->morphMany(SocialPublishLog::class, 'publishable');
    }

    /** Best approved photos safe for public/anonymous display (no faces, no banned uploaders). */
    public function scopeBestPublic(Builder $q, int $limit = 10): Builder
    {
        return $q->where('approved', true)
            ->where('gdpr_consent', true)
            ->where(fn ($q) => $q->where('has_faces', false)->orWhereNull('has_faces'))
            ->whereDoesntHave('uploader', fn ($q) => $q->whereHas('detail', fn ($d) => $d->where('public_photos_banned', true)))
            ->orderByDesc('quality_score')
            ->limit($limit);
    }

    /** Weighted-random public photos — favours high quality_score. */
    public function scopeRandomPublic(Builder $q, int $limit = 10): Builder
    {
        return $q->where('approved', true)
            ->where('gdpr_consent', true)
            ->where(fn ($q) => $q->where('has_faces', false)->orWhereNull('has_faces'))
            ->whereDoesntHave('uploader', fn ($q) => $q->whereHas('detail', fn ($d) => $d->where('public_photos_banned', true)))
            ->orderByRaw('-(quality_score * quality_score) * LOG('.($this->getConnection()->getDriverName() === 'pgsql' ? 'RANDOM' : 'RAND').'())')
            ->limit($limit);
    }

    /** Best approved photos for authenticated members (faces allowed). */
    public function scopeBestForMembers(Builder $q, int $limit = 10): Builder
    {
        return $q->where('approved', true)
            ->where('gdpr_consent', true)
            ->orderByDesc('quality_score')
            ->limit($limit);
    }

    /** Weighted-random photos for authenticated members (faces allowed). */
    public function scopeRandomForMembers(Builder $q, int $limit = 10): Builder
    {
        return $q->where('approved', true)
            ->where('gdpr_consent', true)
            ->orderByRaw('-(quality_score * quality_score) * LOG('.($this->getConnection()->getDriverName() === 'pgsql' ? 'RANDOM' : 'RAND').'())')
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
