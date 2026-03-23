<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Event extends Model
{
    use Auditable;
    use SoftDeletes;

    protected $fillable = ['title', 'color_hex', 'event_type', 'event_date', 'event_time', 'end_time', 'end_date', 'location', 'description', 'responsible_id', 'max_participants', 'waiting_list_enabled', 'inscription_open_at', 'inscriptions_closed', 'levels_display', 'confirmation_required', 'estimated_cost', 'deposit_1_date', 'deposit_1_amount', 'deposit_2_date', 'deposit_2_amount', 'deposit_3_date', 'deposit_3_amount', 'instructor_id', 'assistant_ids', 'created_by', 'permissions_expire_date', 'status', 'is_federated', 'external_slots', 'season_id', 'participant_email', 'whatsapp_group_url', 'dive_site_id'];

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

    public function season()
    {
        return $this->belongsTo(Season::class);
    }

    public function responsible()
    {
        return $this->belongsTo(User::class, 'responsible_id');
    }

    public function instructor()
    {
        return $this->belongsTo(User::class, 'instructor_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function registrations()
    {
        return $this->hasMany(EventRegistration::class);
    }

    public function photos()
    {
        return $this->hasMany(EventPhoto::class)->orderByDesc('quality_score');
    }

    public function diveSite()
    {
        return $this->belongsTo(DiveSite::class);
    }

    public function diveGroups()
    {
        return $this->hasMany(DiveGroup::class);
    }

    public function confirmedRegistrations()
    {
        return $this->registrations()->where('status', 'confirmed');
    }

    public function waitingRegistrations()
    {
        return $this->registrations()->where('status', 'waiting')->orderBy('waiting_list_position');
    }

    public function externalRegistrations()
    {
        return $this->hasMany(ExternalRegistration::class);
    }

    public function confirmedCount(): int
    {
        return $this->confirmedRegistrations()->count();
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
            'theory' => '#6f42c1',
            'social' => '#ffc107',
            default => '#6c757d',
        };
    }
}
