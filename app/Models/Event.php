<?php

namespace App\Models;

use App\Traits\Auditable;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int|null $joomla_sortie_id
 * @property string|null $title
 * @property string|null $color_hex
 * @property string|null $event_type
 * @property Carbon|null $event_date
 * @property string|null $event_time
 * @property string|null $end_time
 * @property Carbon|null $end_date
 * @property string|null $location
 * @property string|null $description
 * @property int|null $responsible_id
 * @property string|null $max_participants
 * @property bool $waiting_list_enabled
 * @property Carbon|null $inscription_open_at
 * @property bool $inscriptions_closed
 * @property bool $levels_display
 * @property bool $confirmation_required
 * @property string|null $estimated_cost
 * @property Carbon|null $deposit_1_date
 * @property string|null $deposit_1_amount
 * @property Carbon|null $deposit_2_date
 * @property string|null $deposit_2_amount
 * @property Carbon|null $deposit_3_date
 * @property string|null $deposit_3_amount
 * @property int|null $instructor_id
 * @property array $assistant_ids
 * @property string|null $created_by
 * @property Carbon|null $permissions_expire_date
 * @property string|null $status
 * @property bool $is_federated
 * @property string|null $external_slots
 * @property int|null $season_id
 * @property string|null $participant_email
 * @property string|null $whatsapp_group_url
 * @property int|null $dive_site_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection $registrations
 * @property-read User|null $instructor
 * @property-read User|null $responsible
 * @property-read DiveSite|null $diveSite
 * @property-read Season|null $season
 */
class Event extends Model
{
    use Auditable;
    use SoftDeletes;

    protected $fillable = ['joomla_sortie_id', 'title', 'color_hex', 'event_type', 'event_date', 'event_time', 'end_time', 'end_date', 'location', 'description', 'responsible_id', 'max_participants', 'waiting_list_enabled', 'inscription_open_at', 'inscriptions_closed', 'levels_display', 'confirmation_required', 'estimated_cost', 'deposit_1_date', 'deposit_1_amount', 'deposit_2_date', 'deposit_2_amount', 'deposit_3_date', 'deposit_3_amount', 'instructor_id', 'assistant_ids', 'created_by', 'permissions_expire_date', 'status', 'is_federated', 'external_slots', 'season_id', 'participant_email', 'whatsapp_group_url', 'dive_site_id'];

    protected function casts(): array
    {
        return [
            'event_date' => 'date',
            'end_date' => 'date',
            'inscription_open_at' => 'datetime',
            'inscriptions_closed' => 'boolean',
            'waiting_list_enabled' => 'boolean',
            'levels_display' => 'boolean',
            'confirmation_required' => 'boolean',
            'assistant_ids' => 'array',
            'deposit_1_date' => 'date',
            'deposit_2_date' => 'date',
            'deposit_3_date' => 'date',
            'permissions_expire_date' => 'date',
        ];
    }

    /** @return BelongsTo<Season, $this> */
    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    /** @return BelongsTo<User, $this> */
    public function responsible(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_id');
    }

    /** @return BelongsTo<User, $this> */
    public function instructor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'instructor_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<EventRegistration, $this> */
    public function registrations(): HasMany
    {
        return $this->hasMany(EventRegistration::class);
    }

    /** @return HasMany<EventPhoto, $this> */
    public function photos(): HasMany
    {
        return $this->hasMany(EventPhoto::class)->orderByDesc('quality_score');
    }

    /** @return BelongsTo<DiveSite, $this> */
    public function diveSite(): BelongsTo
    {
        return $this->belongsTo(DiveSite::class);
    }

    /** @return HasMany<DiveGroup, $this> */
    public function diveGroups(): HasMany
    {
        return $this->hasMany(DiveGroup::class);
    }

    public function confirmedRegistrations(): HasMany
    {
        return $this->registrations()->where('status', 'confirmed');
    }

    public function waitingRegistrations(): HasMany
    {
        return $this->registrations()->where('status', 'waiting')->orderBy('waiting_list_position');
    }

    /** @return HasMany<ExternalRegistration, $this> */
    public function externalRegistrations(): HasMany
    {
        return $this->hasMany(ExternalRegistration::class);
    }

    public function confirmedCount(): int
    {
        return $this->confirmed_count ?? $this->confirmedRegistrations()->count();
    }

    public function isFull(): bool
    {
        return $this->max_participants && $this->confirmedCount() >= $this->max_participants;
    }

    public function isRegistrationOpen(): bool
    {
        if ($this->inscriptions_closed) {
            return false;
        }
        if ($this->status !== 'scheduled') {
            return false;
        }
        if ($this->inscription_open_at && $this->inscription_open_at->isFuture()) {
            return false;
        }

        return true;
    }

    public function mapsUrl(): string
    {
        if (! $this->location) {
            return '';
        }
        $key = config('club.google_maps_key');
        if ($key) {
            return 'https://www.google.com/maps/embed/v1/search?key='.$key.'&q='.urlencode($this->location);
        }

        return 'https://www.google.com/maps/search/'.urlencode($this->location);
    }

    public function typeColor(): string
    {
        if ($this->color_hex) {
            return $this->color_hex;
        }

        return match ($this->event_type) {
            'pool' => '#0077be',
            'dive' => '#003366',
            'training' => '#28a745',
            'apnea' => '#00bcd4',
            'theory' => '#6f42c1',
            'social' => '#ffc107',
            default => '#6c757d',
        };
    }
}
