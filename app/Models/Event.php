<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    use Auditable;

    protected $guarded = ['id'];

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

    public function confirmedRegistrations()
    {
        return $this->registrations()->where('status', 'confirmed');
    }

    public function waitingRegistrations()
    {
        return $this->registrations()->where('status', 'waiting')->orderBy('waiting_list_position');
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
        if ($this->inscriptions_closed) return false;
        if ($this->status !== 'scheduled') return false;
        if ($this->inscription_open_at && $this->inscription_open_at->isFuture()) return false;
        return true;
    }

    public function mapsUrl(): string
    {
        if (!$this->location) return '';
        $key = config('club.google_maps_key');
        if ($key) {
            return 'https://www.google.com/maps/embed/v1/search?key=' . $key . '&q=' . urlencode($this->location);
        }
        return 'https://www.google.com/maps/search/' . urlencode($this->location);
    }

    public function typeColor(): string
    {
        if ($this->color_hex) return $this->color_hex;
        return match($this->event_type) {
            'pool' => '#0077be',
            'dive' => '#003366',
            'training' => '#28a745',
            'theory' => '#6f42c1',
            'social' => '#ffc107',
            default => '#6c757d',
        };
    }
}
