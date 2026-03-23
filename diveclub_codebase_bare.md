# CEP Diving Club (Laravel) - Codebase Snapshot
Generated on: Mon Mar 23 05:09:04 PM CET 2026
---

## Directory: app/Models

### File: app/Models/DiveGroupMember.php
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DiveGroupMember extends Model
{
    protected $fillable = ['dive_group_id', 'user_id', 'role'];

    public function group()
    {
        return $this->belongsTo(DiveGroup::class, 'dive_group_id');
    }

    public function diveGroup()
    {
        return $this->group();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

```

### File: app/Models/Season.php
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Season extends Model
{
    protected $fillable = ['year', 'name', 'start_date', 'end_date', 'is_active'];

    protected function casts(): array
    {
        return ['start_date' => 'date', 'end_date' => 'date', 'is_active' => 'boolean'];
    }

    public function holidays()
    {
        return $this->hasMany(SeasonHoliday::class);
    }

    public function patterns()
    {
        return $this->hasMany(SeasonPattern::class);
    }

    public function events()
    {
        return $this->hasMany(Event::class);
    }
}

```

### File: app/Models/ArticleComment.php
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ArticleComment extends Model
{
    protected $fillable = ['article_id', 'user_id', 'parent_id', 'body'];

    public function article() { return $this->belongsTo(Article::class); }
    public function user() { return $this->belongsTo(User::class); }
    public function parent() { return $this->belongsTo(self::class, 'parent_id'); }
    public function replies() { return $this->hasMany(self::class, 'parent_id')->orderBy('created_at'); }
}

```

### File: app/Models/EquipmentLoan.php
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EquipmentLoan extends Model
{
    protected $fillable = ['equipment_id', 'user_id', 'loaned_at', 'expected_return_date', 'returned_at', 'loaned_by', 'returned_by', 'reminder_sent_at'];

    protected function casts(): array
    {
        return ['loaned_at' => 'date', 'returned_at' => 'date', 'expected_return_date' => 'date', 'reminder_sent_at' => 'date'];
    }

    public function equipment()
    {
        return $this->belongsTo(Equipment::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

```

### File: app/Models/LibraryFile.php
```php
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

```

### File: app/Models/UserSocialAccount.php
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserSocialAccount extends Model
{
    protected $fillable = ['user_id', 'provider', 'provider_user_id', 'email', 'token', 'refresh_token'];

    protected function casts(): array
    {
        return ['token' => 'encrypted', 'refresh_token' => 'encrypted'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

```

### File: app/Models/Event.php
```php
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

```

### File: app/Models/InstructorAvailability.php
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InstructorAvailability extends Model
{
    protected $fillable = ['user_id', 'date', 'slot', 'activity_type', 'note'];

    protected $casts = ['date' => 'date'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

```

### File: app/Models/CertificationLevel.php
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CertificationLevel extends Model
{
    protected $fillable = ['federation_id', 'code', 'name', 'category', 'rank', 'equivalence_group'];

    public function federation()
    {
        return $this->belongsTo(Federation::class);
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'user_certification_levels')->withPivot('obtained_date', 'is_primary', 'display_priority')->withTimestamps();
    }

    // Get equivalent certs across federations
    public function equivalents()
    {
        if (! $this->equivalence_group) {
            return collect();
        }

        return static::where('equivalence_group', $this->equivalence_group)->where('id', '!=', $this->id)->with('federation')->get();
    }

    public function label(): string
    {
        return $this->code.' ('.$this->federation?->acronym.')';
    }
}

```

### File: app/Models/VoteToken.php
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VoteToken extends Model
{
    protected $fillable = ['vote_id', 'user_id', 'token', 'is_consumed', 'consumed_at'];

    protected function casts(): array
    {
        return ['is_consumed' => 'boolean', 'consumed_at' => 'datetime'];
    }

    public function vote()
    {
        return $this->belongsTo(Vote::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

```

### File: app/Models/PushSubscription.php
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PushSubscription extends Model
{
    protected $fillable = ['user_id', 'endpoint', 'p256dh', 'auth'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

```

### File: app/Models/EmailTemplate.php
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailTemplate extends Model
{
    protected $fillable = ['name', 'slug', 'subject', 'body', 'locale'];
}

```

### File: app/Models/UserEmail.php
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserEmail extends Model
{
    use \App\Traits\Auditable;
    protected $fillable = ['user_id', 'email', 'is_primary', 'is_verified', 'label', 'verification_token', 'verification_sent_at'];

    protected function casts(): array
    {
        return ['is_primary' => 'boolean', 'is_verified' => 'boolean', 'verification_sent_at' => 'datetime'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

```

### File: app/Models/Article.php
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Article extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'title', 'slug', 'body', 'article_type', 'featured_image',
        'is_published', 'is_public', 'author_id', 'vote_id', 'expires_at', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'is_public' => 'boolean',
            'expires_at' => 'datetime',
        ];
    }

    public const TYPES = [
        'news' => ['icon' => '📰', 'color' => '#0d6efd', 'label' => 'News'],
        'history' => ['icon' => '🏛️', 'color' => '#6f42c1', 'label' => 'Club History'],
        'safety' => ['icon' => '🛟', 'color' => '#dc3545', 'label' => 'Safety'],
        'training' => ['icon' => '🎓', 'color' => '#198754', 'label' => 'Training'],
        'regulation' => ['icon' => '📋', 'color' => '#6c757d', 'label' => 'Regulation'],
        'trip_report' => ['icon' => '🌊', 'color' => '#0dcaf0', 'label' => 'Trip Report'],
        'trip_proposal' => ['icon' => '🗺️', 'color' => '#fd7e14', 'label' => 'Trip Proposal'],
        'environment' => ['icon' => '🌿', 'color' => '#20c997', 'label' => 'Environment'],
        'gear' => ['icon' => '🤿', 'color' => '#0077be', 'label' => 'Gear'],
        'classified' => ['icon' => '🏷️', 'color' => '#ffc107', 'label' => 'Classified'],
        'faq' => ['icon' => '❓', 'color' => '#adb5bd', 'label' => 'FAQ'],
        'newsletter' => ['icon' => '📬', 'color' => '#e83e8c', 'label' => 'Newsletter'],
        'video' => ['icon' => '🎬', 'color' => '#e74c3c', 'label' => 'Video'],
    ];

    public const MEMBER_TYPES = ['classified'];

    public function typeMeta(): array
    {
        return self::TYPES[$this->article_type] ?? self::TYPES['news'];
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * Render body with auto-embedded YouTube/Vimeo links.
     */
    public function renderedBody(): string
    {
        $body = $this->body ?? '';
        // YouTube: convert bare URLs to responsive embeds
        $body = preg_replace(
            '#(?:<a[^>]*>)?\s*(?:https?://)?(?:www\.)?(?:youtube\.com/watch\?v=|youtu\.be/)([\w-]+)[^\s<]*\s*(?:</a>)?#i',
            '<div class="ratio ratio-16x9 mb-3"><iframe src="https://www.youtube.com/embed/$1" allowfullscreen loading="lazy"></iframe></div>',
            $body
        );
        // Vimeo
        $body = preg_replace(
            '#(?:<a[^>]*>)?\s*(?:https?://)?(?:www\.)?vimeo\.com/(\d+)[^\s<]*\s*(?:</a>)?#i',
            '<div class="ratio ratio-16x9 mb-3"><iframe src="https://player.vimeo.com/video/$1" allowfullscreen loading="lazy"></iframe></div>',
            $body
        );

        return $body;
    }

    public function canBeEditedBy($user): bool
    {
        if ($user->isBureauMaster()) {
            return true;
        }
        if (in_array($this->article_type, self::MEMBER_TYPES) && $this->author_id === $user->id) {
            return true;
        }

        return false;
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function vote()
    {
        return $this->belongsTo(Vote::class);
    }

    public function images()
    {
        return $this->hasMany(ArticleImage::class)->orderBy('sort_order');
    }

    public function comments()
    {
        return $this->hasMany(ArticleComment::class);
    }

    public function translations()
    {
        return $this->hasMany(ArticleTranslation::class);
    }

    /**
     * Get translated title/body for a locale, falling back to original.
     */
    public function translated(?string $locale = null): array
    {
        $locale = $locale ?? app()->getLocale();
        $t = $this->translations->firstWhere('locale', $locale);

        return [
            'title' => $t?->title ?? $this->title,
            'body' => $t?->body ?? $this->body,
            'locale' => $t ? $locale : null,
            'auto' => $t?->auto_translated ?? false,
        ];
    }

    public function rootComments()
    {
        return $this->hasMany(ArticleComment::class)->whereNull('parent_id')->orderBy('created_at');
    }

    public function previousInType()
    {
        return self::where('article_type', $this->article_type)
            ->active()->where('created_at', '<', $this->created_at)
            ->orderByDesc('created_at')->first();
    }

    public function nextInType()
    {
        return self::where('article_type', $this->article_type)
            ->active()->where('created_at', '>', $this->created_at)
            ->orderBy('created_at')->first();
    }

    public function previousOverall()
    {
        return self::active()->where('created_at', '<', $this->created_at)
            ->orderByDesc('created_at')->first();
    }

    public function nextOverall()
    {
        return self::active()->where('created_at', '>', $this->created_at)
            ->orderBy('created_at')->first();
    }

    public function scopeActive($query)
    {
        return $query->where('is_published', true)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }
}

```

### File: app/Models/DiveGroup.php
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DiveGroup extends Model
{
    protected $fillable = ['event_id', 'name', 'dive_mode', 'purpose', 'planned_depth', 'planned_duration', 'gas_mix', 'line_number', 'planned_entry_time', 'planned_exit_time', 'notes', 'created_by'];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function members()
    {
        return $this->hasMany(DiveGroupMember::class);
    }

    public function leader()
    {
        return $this->members()->where('role', 'leader')->first();
    }

    public function divers()
    {
        return $this->members()->where('role', 'diver');
    }

    public const DIVE_MODES = ['supervised', 'autonomous', 'training', 'certification'];

    public const GAS_MIXES = [
        'air' => 'Air',
        'nitrox32' => 'Nitrox 32%',
        'nitrox36' => 'Nitrox 36%',
        'nitrox40' => 'Nitrox 40%',
        'trimix' => 'Trimix',
        'O2' => 'O₂ (deco)',
    ];
}

```

### File: app/Models/Role.php
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    protected $fillable = ['name', 'slug', 'description'];

    public function users()
    {
        return $this->hasMany(User::class);
    }
}

```

### File: app/Models/MedicalComplianceRule.php
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MedicalComplianceRule extends Model
{
    protected $fillable = ['federation_id', 'age_bracket_low', 'age_bracket_high', 'cert_type', 'validity_months'];

    public function federation()
    {
        return $this->belongsTo(Federation::class);
    }
}

```

### File: app/Models/MemberLicence.php
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MemberLicence extends Model
{
    protected $fillable = ['user_id', 'federation_id', 'licence_number', 'federation_key', 'licence_request_date', 'licence_request_pending', 'insurance_type', 'medical_cert_expiry', 'season', 'registration_date'];

    protected function casts(): array
    {
        return ['licence_request_date' => 'date', 'licence_request_pending' => 'boolean', 'medical_cert_expiry' => 'date', 'registration_date' => 'date'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function federation()
    {
        return $this->belongsTo(Federation::class);
    }
}

```

### File: app/Models/EquipmentMaintenanceRule.php
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EquipmentMaintenanceRule extends Model
{
    protected $fillable = ['equipment_type', 'maintenance_name', 'interval_months', 'is_mandatory', 'regulation_reference'];

    protected function casts(): array
    {
        return ['is_mandatory' => 'boolean'];
    }
}

```

### File: app/Models/EventRegistration.php
```php
<?php

/**
 * Event registration model with full audit trail.
 *
 * Tracks who registered, who cancelled, and why — supporting both self-registration
 * and proxy registration (bureau/instructor registering on behalf of a member).
 * Includes waiting list positioning and check-in/check-out timestamps for events.
 *
 * @author  ClubCEP.eu
 *
 * @see     \App\Http\Controllers\EventController  — registration/cancellation logic
 */

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;

class EventRegistration extends Model
{
    use Auditable;

    protected $fillable = [
        'event_id', 'user_id', 'status', 'comment',
        'registered_by', 'waiting_list_position',
        'cancelled_at', 'cancelled_by', 'cancel_comment',
        'checked_in_at', 'checked_out_at', 'checked_in_by',
    ];

    protected function casts(): array
    {
        return [
            'checked_in_at' => 'datetime',
            'checked_out_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    // ─── Relationships ────────────────────────────────────────

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /** Who performed the registration (null = self-registration). */
    public function registeredByUser()
    {
        return $this->belongsTo(User::class, 'registered_by');
    }

    /** Who cancelled the registration (null = self-cancellation). */
    public function cancelledByUser()
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }
}

```

### File: app/Models/SeasonHoliday.php
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SeasonHoliday extends Model
{
    protected $fillable = ['season_id', 'name', 'start_date', 'end_date', 'is_adhoc'];

    protected function casts(): array
    {
        return ['start_date' => 'date', 'end_date' => 'date', 'is_adhoc' => 'boolean'];
    }

    public function season()
    {
        return $this->belongsTo(Season::class);
    }
}

```

### File: app/Models/Vote.php
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vote extends Model
{
    use SoftDeletes;

    protected $fillable = ['title', 'description', 'mode', 'allow_multiple', 'allow_change', 'num_positions', 'min_vote_pct', 'is_public', 'status', 'opens_at', 'closes_at', 'created_by'];

    protected function casts(): array
    {
        return [
            'opens_at' => 'datetime',
            'closes_at' => 'datetime',
            'allow_multiple' => 'boolean',
            'allow_change' => 'boolean',
            'is_public' => 'boolean',
        ];
    }

    public function options()
    {
        return $this->hasMany(VoteOption::class);
    }

    public function tokens()
    {
        return $this->hasMany(VoteToken::class);
    }

    public function ballots()
    {
        return $this->hasMany(VoteBallot::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isOpen(): bool
    {
        return $this->status === 'open'
            && (! $this->opens_at || $this->opens_at->isPast())
            && (! $this->closes_at || $this->closes_at->isFuture());
    }
}

```

### File: app/Models/EquipmentMaintenance.php
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EquipmentMaintenance extends Model
{
    protected $table = 'equipment_maintenance';

    protected $fillable = ['equipment_id', 'maintenance_name', 'due_date', 'completed_at', 'completed_by', 'notes', 'is_mandatory'];

    protected function casts(): array
    {
        return ['due_date' => 'date', 'completed_at' => 'date', 'is_mandatory' => 'boolean'];
    }

    public function equipment()
    {
        return $this->belongsTo(Equipment::class);
    }

    public function completedByUser()
    {
        return $this->belongsTo(User::class, 'completed_by');
    }
}

```

### File: app/Models/Link.php
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Link extends Model
{
    protected $fillable = ['title', 'url', 'description', 'is_public', 'sort_order'];

    protected function casts(): array
    {
        return ['is_public' => 'boolean'];
    }
}

```

### File: app/Models/EventPhoto.php
```php
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
    protected $fillable = ['event_id', 'uploaded_by', 'path', 'thumbnail_path', 'caption', 'quality_score', 'has_faces', 'approved', 'gdpr_consent'];

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
            ->orderByRaw('-(quality_score * quality_score) * LOG('.($this->getConnection()->getDriverName() === 'pgsql' ? 'RANDOM' : 'RAND').'())')
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

```

### File: app/Models/Document.php
```php
<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Document extends Model
{
    use Auditable;
    use SoftDeletes;

    protected $fillable = ['user_id', 'category', 'cert_type', 'file_path', 'original_filename', 'mime_type', 'size_bytes', 'date_established', 'expiry_date', 'is_verified', 'verified_by', 'verified_at', 'superseded_by', 'is_current', 'is_compliant', 'compliance_notes', 'reminder_30_sent_at', 'reminder_15_sent_at', 'reminder_7_sent_at', 'reminder_0_sent_at'];

    protected function casts(): array
    {
        return [
            'date_established' => 'date',
            'expiry_date' => 'date',
            'verified_at' => 'datetime',
            'is_verified' => 'boolean',
            'is_current' => 'boolean',
            'is_compliant' => 'boolean',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function verifier()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function supersededBy()
    {
        return $this->belongsTo(Document::class, 'superseded_by');
    }

    public function isExpired(): bool
    {
        return $this->expiry_date && $this->expiry_date->isPast();
    }

    public function daysUntilExpiry(): ?int
    {
        return $this->expiry_date ? (int) now()->startOfDay()->diffInDays($this->expiry_date->startOfDay(), false) : null;
    }
}

```

### File: app/Models/MembershipFeeComponent.php
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MembershipFeeComponent extends Model
{
    protected $fillable = ['season_id', 'name', 'slug', 'amount', 'is_base', 'is_optional', 'description', 'sort_order'];

    protected function casts(): array
    {
        return ['is_base' => 'boolean', 'is_optional' => 'boolean'];
    }

    public function season()
    {
        return $this->belongsTo(Season::class);
    }
}

```

### File: app/Models/BuddyResponse.php
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BuddyResponse extends Model
{
    protected $fillable = ['buddy_request_id', 'user_id', 'message', 'status'];

    public function buddyRequest()
    {
        return $this->belongsTo(BuddyRequest::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

```

### File: app/Models/ExternalRegistration.php
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExternalRegistration extends Model
{
    protected $fillable = ['event_id', 'partnership_id', 'external_member_name', 'external_member_email', 'external_member_phone', 'external_member_federation', 'external_member_licence_no', 'external_member_emergency_contact', 'external_member_iban', 'external_cert_level', 'external_medical_valid_until', 'status', 'notes', 'external_ref'];

    protected $casts = [
        'external_medical_valid_until' => 'date',
    ];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function partnership()
    {
        return $this->belongsTo(ClubPartnership::class, 'partnership_id');
    }
}

```

### File: app/Models/SocialPublishLog.php
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SocialPublishLog extends Model
{
    protected $fillable = ['platform', 'publishable_type', 'publishable_id', 'external_post_id', 'status', 'error_message', 'published_at'];

    protected function casts(): array
    {
        return ['published_at' => 'datetime'];
    }

    public function publishable()
    {
        return $this->morphTo();
    }
}

```

### File: app/Models/ParentalConsent.php
```php
<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;

class ParentalConsent extends Model
{
    use Auditable;

    protected $fillable = ['minor_user_id', 'granted_by', 'consent_type', 'granted', 'document_path', 'granted_at', 'revoked_at'];

    protected function casts(): array
    {
        return ['granted' => 'boolean', 'granted_at' => 'datetime', 'revoked_at' => 'datetime'];
    }

    public function minor()
    {
        return $this->belongsTo(User::class, 'minor_user_id');
    }

    public function grantedBy()
    {
        return $this->belongsTo(User::class, 'granted_by');
    }
}

```

### File: app/Models/SeasonPattern.php
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SeasonPattern extends Model
{
    protected $fillable = ['season_id', 'day_of_week', 'start_time', 'end_time', 'event_type', 'title', 'location', 'max_participants', 'registration_opens_days_before', 'color_hex'];

    public function season()
    {
        return $this->belongsTo(Season::class);
    }

    public function dayName(): string
    {
        return ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'][$this->day_of_week] ?? '?';
    }
}

```

### File: app/Models/ClubPartnership.php
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ClubPartnership extends Model
{
    protected $fillable = ['name', 'base_url', 'api_key_id', 'is_active', 'last_sync_at'];

    protected $casts = [
        'is_active' => 'boolean',
        'last_sync_at' => 'datetime',
    ];

    public static function generateKeyPair(): array
    {
        return [
            'key_id' => 'dc_'.Str::random(32),
            'secret' => Str::random(64),
        ];
    }

    public function externalRegistrations()
    {
        return $this->hasMany(ExternalRegistration::class, 'partnership_id');
    }
}

```

### File: app/Models/DiveGroupRule.php
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class DiveGroupRule extends Model
{
    protected $fillable = ['name', 'scope', 'diver_condition', 'dive_mode', 'min_leader_rank', 'leader_category', 'max_depth', 'max_group_size', 'description', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }

    public function matchesDiver(?int $diverRank): bool
    {
        if ($this->diver_condition === 'no_cert') {
            return $diverRank === null || $diverRank === 0;
        }
        if ($this->diver_condition === 'any') {
            return true;
        }
        if (Str::startsWith($this->diver_condition, 'max_rank:')) {
            return ($diverRank ?? 0) <= (int) Str::after($this->diver_condition, 'max_rank:');
        }
        if (Str::startsWith($this->diver_condition, 'min_rank:')) {
            return ($diverRank ?? 0) >= (int) Str::after($this->diver_condition, 'min_rank:');
        }

        return false;
    }

    public function leaderSatisfied(?int $leaderRank, ?string $leaderCategory): bool
    {
        if (! $leaderRank) {
            return false;
        }
        if ($this->leader_category === 'instructor' && $leaderCategory !== 'instructor') {
            return false;
        }

        return $leaderRank >= $this->min_leader_rank;
    }

    public const DIVE_MODES = ['supervised', 'autonomous', 'training', 'certification'];
}

```

### File: app/Models/MemberStatus.php
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MemberStatus extends Model
{
    protected $fillable = ['name', 'slug', 'fee_multiplier', 'description'];

    protected function casts(): array
    {
        return ['fee_multiplier' => 'decimal:2'];
    }

    public function users()
    {
        return $this->hasMany(User::class, 'status_id');
    }
}

```

### File: app/Models/GuardianLink.php
```php
<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;

class GuardianLink extends Model
{
    use Auditable;

    protected $fillable = ['guardian_user_id', 'minor_user_id', 'relationship'];

    public function guardian()
    {
        return $this->belongsTo(User::class, 'guardian_user_id');
    }

    public function minor()
    {
        return $this->belongsTo(User::class, 'minor_user_id');
    }
}

```

### File: app/Models/Federation.php
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Federation extends Model
{
    protected $fillable = ['acronym', 'full_name', 'visibility'];

    public function scopeVisible($q)
    {
        return $q->whereIn('visibility', ['active', 'recognized']);
    }

    public function scopeActive($q)
    {
        return $q->where('visibility', 'active');
    }

    public function licences()
    {
        return $this->hasMany(MemberLicence::class);
    }

    public function certificationLevels()
    {
        return $this->hasMany(CertificationLevel::class);
    }
}

```

### File: app/Models/ArticleTranslation.php
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ArticleTranslation extends Model
{
    protected $fillable = ['article_id', 'locale', 'title', 'body', 'auto_translated', 'stale'];

    public function article()
    {
        return $this->belongsTo(Article::class);
    }
}

```

### File: app/Models/BuddyRequest.php
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BuddyRequest extends Model
{
    protected $fillable = ['user_id', 'dive_site_id', 'location_text', 'dive_date', 'dive_time', 'need_type', 'description', 'max_depth', 'desired_cert_level', 'max_buddies', 'is_active'];

    protected function casts(): array
    {
        return ['dive_date' => 'date', 'is_active' => 'boolean'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function diveSite()
    {
        return $this->belongsTo(DiveSite::class);
    }

    public function responses()
    {
        return $this->hasMany(BuddyResponse::class);
    }

    public function scopeActive($q)
    {
        return $q->where('is_active', true)->where('dive_date', '>=', today());
    }

    public function locationLabel(): string
    {
        return $this->diveSite?->name ?? $this->location_text ?? '—';
    }

    public const NEED_TYPES = [
        'buddy' => '🤝 Buddy',
        'guide' => '👑 Guide de Palanquée / Divemaster',
        'dp' => '📋 Directeur de Plongée',
    ];
}

```

### File: app/Models/GdprConsent.php
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GdprConsent extends Model
{
    protected $fillable = ['user_id', 'consent_type', 'granted', 'granted_at', 'revoked_at'];

    protected function casts(): array
    {
        return ['granted' => 'boolean', 'granted_at' => 'datetime', 'revoked_at' => 'datetime'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

```

### File: app/Models/AuditLog.php
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = ['user_id', 'impersonated_user_id', 'action', 'model_type', 'model_id', 'old_values', 'new_values', 'ip_address', 'user_agent', 'created_at'];

    protected function casts(): array
    {
        return ['old_values' => 'array', 'new_values' => 'array', 'created_at' => 'datetime'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

```

### File: app/Models/EmailLog.php
```php
<?php

/**
 * Email communication log linked to events.
 *
 * Stores inbound and outbound emails associated with events (via the per-event
 * participant email address). Used to display communication history on the
 * event detail page.
 *
 * @author  ClubCEP.eu
 *
 * @see     \App\Http\Controllers\EventController::show()
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailLog extends Model
{
    protected $table = 'email_log';

    protected $fillable = ['event_id', 'user_id', 'to_email', 'alias', 'from_name', 'from_email', 'subject', 'body', 'template_slug', 'status', 'direction', 'authorized', 'attempts', 'error'];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

```

### File: app/Models/PaymentExpected.php
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentExpected extends Model
{
    protected $table = 'payment_expected';

    protected $fillable = ['user_id', 'type', 'event_id', 'season_year', 'amount_due', 'communication', 'components', 'status', 'refund_review_needed', 'amount_paid', 'paid_at', 'reconciled_by', 'reconciled_at', 'bank_statement_ref', 'bank_statement_date'];

    protected function casts(): array
    {
        return ['components' => 'array', 'paid_at' => 'date', 'reconciled_at' => 'datetime'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }
}

```

### File: app/Models/VoteOption.php
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VoteOption extends Model
{
    protected $fillable = ['vote_id', 'label', 'sort_order'];

    public function vote()
    {
        return $this->belongsTo(Vote::class);
    }

    public function ballots()
    {
        return $this->hasMany(VoteBallot::class);
    }
}

```

### File: app/Models/MemberDetail.php
```php
<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MemberDetail extends Model
{
    use Auditable;
    use SoftDeletes;

    protected $fillable = ['user_id', 'avatar_path', 'first_name', 'last_name', 'birth_name', 'nationality', 'phone_private', 'phone_office', 'phone_mobile', 'sex', 'adhesion_year', 'bureau_member', 'active_instructor', 'instructor_bio', 'instructor_specialties', 'instructor_motivation', 'show_on_public_site', 'public_photos_banned', 'club_email', 'date_of_birth', 'place_of_birth', 'address_line1', 'address_line2', 'city', 'postal_code', 'country', 'iban', 'emergency_contact_name', 'emergency_contact_phone', 'emergency_contact_relationship', 'brevet_date', 'dive_count', 'air_consumption', 'ease_level', 'primary_intent', 'is_photographer', 'total_dives', 'last_dive_date', 'certification_level', 'other_certifications', 'training_enrollments', 'preferred_language', 'show_icons', 'cotisation_years', 'bcd_size', 'bcd_notes'];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'brevet_date' => 'date',
            'last_dive_date' => 'date',
            'other_certifications' => 'array',
            'training_enrollments' => 'array',
            'cotisation_years' => 'array',
            'bureau_member' => 'boolean',
            'active_instructor' => 'boolean',
            'is_photographer' => 'boolean',
            'air_consumption' => 'float',
            'ease_level' => 'float',
            'total_dives' => 'integer',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

```

### File: app/Models/ThemeSetting.php
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ThemeSetting extends Model
{
    protected $fillable = ['key', 'value'];

    public static function get(string $key, mixed $default = null): mixed
    {
        return static::where('key', $key)->value('value') ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
    }

    public static function all_settings(): array
    {
        return static::pluck('value', 'key')->toArray();
    }
}

```

### File: app/Models/DiveSite.php
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DiveSite extends Model
{
    protected $fillable = ['name', 'country', 'region', 'latitude', 'longitude', 'max_depth', 'water_type', 'conditions', 'marine_life', 'safety_notes', 'access_notes', 'facilities', 'food_options', 'nearest_hospital', 'emergency_phone', 'vhf_channel', 'required_safety_equipment', 'nearest_hyperbaric_chamber', 'hyperbaric_phone', 'hospital_distance_km', 'hyperbaric_distance_km', 'website_url', 'entry_fee', 'booking_url', 'image_path', 'map_image_path', 'site_plan_path', 'safety_docs_folder', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'latitude' => 'decimal:7', 'longitude' => 'decimal:7'];
    }

    public function events()
    {
        return $this->hasMany(Event::class);
    }

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }

    public function mapsUrl(): string
    {
        if ($this->latitude && $this->longitude) {
            return 'https://www.google.com/maps/search/?api=1&query='.$this->latitude.','.$this->longitude;
        }

        return 'https://www.google.com/maps/search/'.urlencode($this->name.' '.($this->region ?? '').' '.($this->country ?? ''));
    }

    public const WATER_TYPES = ['sea', 'lake', 'quarry', 'river', 'pool', 'cenote'];
}

```

### File: app/Models/Equipment.php
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Equipment extends Model
{
    use SoftDeletes;

    protected $table = 'equipment';

    protected $fillable = ['club_id', 'name', 'brand', 'manufacturer', 'threading', 'manufacture_date', 'weight_kg', 'volume', 'material', 'test_pressure_bar', 'working_pressure_bar', 'last_retest_date', 'next_retest_date', 'last_inventory_date', 'type', 'serial_number', 'purchase_date', 'condition', 'status', 'notes'];

    protected function casts(): array
    {
        return [
            'purchase_date' => 'date',
            'manufacture_date' => 'date',
            'last_retest_date' => 'date',
            'next_retest_date' => 'date',
            'last_inventory_date' => 'date',
            'weight_kg' => 'decimal:1',
        ];
    }

    public function maintenanceTasks()
    {
        return $this->hasMany(EquipmentMaintenance::class);
    }

    public function loans()
    {
        return $this->hasMany(EquipmentLoan::class);
    }

    public function currentLoan()
    {
        return $this->hasOne(EquipmentLoan::class)->whereNull('returned_at');
    }

    public function hasOverdueMaintenance(): bool
    {
        return $this->maintenanceTasks()->where('is_mandatory', true)->whereNull('completed_at')->where('due_date', '<', now())->exists();
    }

    public function isAvailable(): bool
    {
        return $this->status === 'available';
    }

    public function needsRetest(): bool
    {
        return $this->next_retest_date && $this->next_retest_date->isPast();
    }
}

```

### File: app/Models/User.php
```php
<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmail
{
    use Auditable, HasFactory, Notifiable;
    use SoftDeletes;

    protected $fillable = [
        'username', 'primary_email', 'password', 'role_id', 'status_id', 'email_verified_at', 'preferred_locale',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    // Auth uses primary_email
    public function getEmailAttribute(): string
    {
        return $this->primary_email;
    }

    public function getEmailForPasswordReset(): string
    {
        return $this->primary_email;
    }

    public function getEmailForVerification(): string
    {
        return $this->primary_email;
    }

    public function getNameAttribute(): string
    {
        $detail = $this->detail;
        if ($detail && $detail->first_name) {
            return trim($detail->first_name.' '.$detail->last_name);
        }

        return $this->username ?? $this->primary_email;
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function status()
    {
        return $this->belongsTo(MemberStatus::class, 'status_id');
    }

    public function emails()
    {
        return $this->hasMany(UserEmail::class);
    }

    public function primaryEmailRecord()
    {
        return $this->hasOne(UserEmail::class)->where('is_primary', true);
    }

    public function socialAccounts()
    {
        return $this->hasMany(UserSocialAccount::class);
    }

    public function detail()
    {
        return $this->hasOne(MemberDetail::class);
    }

    public function licences()
    {
        return $this->hasMany(MemberLicence::class);
    }

    public function documents()
    {
        return $this->hasMany(Document::class);
    }

    public function paymentsExpected()
    {
        return $this->hasMany(PaymentExpected::class);
    }

    public function eventRegistrations()
    {
        return $this->hasMany(EventRegistration::class);
    }

    public function equipmentLoans()
    {
        return $this->hasMany(EquipmentLoan::class);
    }

    public function gdprConsents()
    {
        return $this->hasMany(GdprConsent::class);
    }

    public function certificationLevels()
    {
        return $this->belongsToMany(CertificationLevel::class, 'user_certification_levels')
            ->withPivot('obtained_date', 'is_primary', 'display_priority')->withTimestamps();
    }

    public function primaryCertification()
    {
        return $this->certificationLevels()->wherePivot('is_primary', true)->first();
    }

    public function hasRole(string $slug): bool
    {
        return $this->role && $this->role->slug === $slug;
    }

    public function hasAnyRole(array $slugs): bool
    {
        return $this->role && in_array($this->role->slug, $slugs);
    }

    public function isBureau(): bool
    {
        return $this->hasAnyRole(['bureau_master', 'bureau_finance', 'bureau_technical']);
    }

    public function isBureauMaster(): bool
    {
        return $this->hasRole('bureau_master');
    }

    // Guardian / minor relationships
    public function guardians()
    {
        return $this->belongsToMany(User::class, 'guardian_links', 'minor_user_id', 'guardian_user_id')
            ->withPivot('relationship')->withTimestamps();
    }

    public function minors()
    {
        return $this->belongsToMany(User::class, 'guardian_links', 'guardian_user_id', 'minor_user_id')
            ->withPivot('relationship')->withTimestamps();
    }

    public function isMinor(): bool
    {
        $dob = $this->detail?->date_of_birth;

        return $dob && $dob->age < 18;
    }

    /** Minors always banned; others check the explicit flag. */
    public function hasPublicPhotosBanned(): bool
    {
        if ($this->isMinor()) {
            return true;
        }

        return (bool) ($this->detail?->public_photos_banned ?? false);
    }

    public function parentalConsents()
    {
        return $this->hasMany(ParentalConsent::class, 'minor_user_id');
    }

    /** Check if profile has the minimum fields needed for dive/pool/training registration. */
    public function hasDiveProfile(): bool
    {
        $d = $this->detail;

        return $d
            && $d->date_of_birth
            && $d->sex
            && $d->phone_mobile
            && $d->emergency_contact_name
            && $d->emergency_contact_phone;
    }

    /** List which required profile fields are still missing. */
    public function missingDiveProfileFields(): array
    {
        $d = $this->detail;
        $missing = [];

        $checks = [
            'date_of_birth' => __('Date of Birth'),
            'sex' => __('Sex'),
            'phone_mobile' => __('Mobile Phone'),
            'emergency_contact_name' => __('Emergency Contact Name'),
            'emergency_contact_phone' => __('Emergency Contact Phone'),
        ];

        foreach ($checks as $field => $label) {
            if (! $d || ! $d->$field) {
                $missing[] = $label;
            }
        }

        return $missing;
    }

    public function pushSubscriptions(): HasMany
    {
        return $this->hasMany(PushSubscription::class);
    }
}

```

### File: app/Models/TrialRequest.php
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrialRequest extends Model
{
    protected $fillable = ['first_name', 'last_name', 'email', 'phone', 'preferred_date', 'message', 'status', 'confirmed_by', 'confirmed_date', 'admin_notes'];

    protected function casts(): array
    {
        return ['preferred_date' => 'date', 'confirmed_date' => 'date'];
    }

    public function confirmedBy()
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function scopePending($q)
    {
        return $q->where('status', 'pending');
    }
}

```

### File: app/Models/VoteBallot.php
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VoteBallot extends Model
{
    protected $fillable = ['vote_id', 'vote_option_id', 'token_hash'];

    public function vote()
    {
        return $this->belongsTo(Vote::class);
    }

    public function option()
    {
        return $this->belongsTo(VoteOption::class, 'vote_option_id');
    }
}

```

### File: app/Models/ArticleImage.php
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ArticleImage extends Model
{
    protected $fillable = ['article_id', 'file_path', 'alt_text', 'sort_order'];

    public function article()
    {
        return $this->belongsTo(Article::class);
    }
}

```

### File: app/Models/MembershipFee.php
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MembershipFee extends Model
{
    protected $fillable = ['season_year', 'status_id', 'amount', 'label', 'notes'];

    public function status()
    {
        return $this->belongsTo(MemberStatus::class, 'status_id');
    }
}

```

### File: app/Models/BankTransaction.php
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BankTransaction extends Model
{
    protected $fillable = ['transaction_date', 'amount', 'communication', 'counterparty', 'matched_payment_id', 'match_score', 'status', 'statement_ref', 'confirmed_by'];

    protected function casts(): array
    {
        return ['transaction_date' => 'date'];
    }

    public function matchedPayment()
    {
        return $this->belongsTo(PaymentExpected::class, 'matched_payment_id');
    }

    public function confirmedByUser()
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }
}

```


## Directory: app/Http/Controllers

### File: app/Http/Controllers/ClassifiedController.php
```php
<?php

namespace App\Http\Controllers;

use App\Helpers\HtmlSanitizer;
use App\Http\Controllers\Concerns\PaginatesFromRequest;
use App\Models\Article;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ClassifiedController extends Controller
{
    use PaginatesFromRequest;

    public function index()
    {
        $classifieds = Article::where('article_type', 'classified')
            ->active()->where('is_published', true)
            ->with('author.detail')
            ->orderByDesc('created_at')->paginate($this->perPage(20));
        $mine = Article::where('article_type', 'classified')
            ->where('author_id', auth()->id())
            ->orderByDesc('created_at')->get();

        return view('classifieds.index', compact('classifieds', 'mine'));
    }

    public function create()
    {
        return view('classifieds.form', ['article' => new Article(['article_type' => 'classified'])]);
    }

    public function store(Request $request)
    {
        $v = $request->validate([
            'title' => 'required|string|max:255',
            'body' => 'required|string',
            'featured_image' => 'nullable|image|max:5120',
        ]);

        $v['slug'] = Str::slug($v['title']).'-'.Str::random(5);
        $v['article_type'] = 'classified';
        $v['author_id'] = auth()->id();
        $v['is_published'] = true;
        $v['is_public'] = false;
        $v['expires_at'] = now()->addDays(30);
        $v['body'] = HtmlSanitizer::clean($v['body'], 'basic');

        if ($request->hasFile('featured_image')) {
            $v['featured_image'] = $request->file('featured_image')->store('classifieds', 'public');
        }

        Article::create($v);

        return redirect()->route('classifieds.index')->with('success', __('Classified posted. It will expire in 30 days.'));
    }

    public function edit(Article $article)
    {
        abort_unless($article->article_type === 'classified' && $article->author_id === auth()->id(), 403);

        return view('classifieds.form', compact('article'));
    }

    public function update(Request $request, Article $article)
    {
        abort_unless($article->article_type === 'classified' && $article->author_id === auth()->id(), 403);

        $v = $request->validate([
            'title' => 'required|string|max:255',
            'body' => 'required|string',
            'featured_image' => 'nullable|image|max:5120',
        ]);

        $v['body'] = HtmlSanitizer::clean($v['body'], 'basic');

        if ($request->hasFile('featured_image')) {
            $v['featured_image'] = $request->file('featured_image')->store('classifieds', 'public');
        }

        $article->update($v);

        return redirect()->route('classifieds.index')->with('success', __('Classified updated.'));
    }

    public function extend(Article $article)
    {
        abort_unless($article->article_type === 'classified' && $article->author_id === auth()->id(), 403);
        $article->update(['expires_at' => now()->addDays(30)]);

        return back()->with('success', __('Extended for 30 more days.'));
    }

    public function destroy(Article $article)
    {
        abort_unless(
            $article->article_type === 'classified' && ($article->author_id === auth()->id() || auth()->user()->isBureauMaster()),
            403
        );
        $article->delete();

        return redirect()->route('classifieds.index')->with('success', __('Classified deleted.'));
    }
}

```

### File: app/Http/Controllers/CommentController.php
```php
<?php

namespace App\Http\Controllers;

use App\Helpers\HtmlSanitizer;
use App\Http\Requests\StoreCommentRequest;
use App\Models\Article;
use App\Models\ArticleComment;

class CommentController extends Controller
{
    public function store(StoreCommentRequest $request, Article $article)
    {
        ArticleComment::create([
            'article_id' => $article->id,
            'user_id' => auth()->id(),
            'parent_id' => $request->parent_id,
            'body' => HtmlSanitizer::clean($request->body, 'comment'),
        ]);

        return back()->with('success', __('Comment posted.'));
    }

    public function destroy(ArticleComment $comment)
    {
        abort_unless($comment->user_id === auth()->id() || auth()->user()->isBureauMaster(), 403);
        $comment->delete();

        return back()->with('success', __('Comment deleted.'));
    }
}

```

### File: app/Http/Controllers/InstallController.php
```php
<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\ThemeSetting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class InstallController extends Controller
{
    public function index()
    {
        // If already installed, redirect home
        try {
            if (file_exists(storage_path('installed.lock'))) {
                return redirect('/');
            }
            if (Schema::hasTable('users') && User::count() > 0) {
                return redirect('/');
            }
        } catch (\Exception $e) {
            // DB not ready yet — show wizard
        }

        $currentDriver = config('database.default');
        $envExists = file_exists(base_path('.env'));

        return view('install.index', compact('currentDriver', 'envExists'));
    }

    public function run(Request $request)
    {
        $request->validate([
            'db_driver' => 'required|in:sqlite,mysql',
            'app_name' => 'required|string|max:100',
            'admin_email' => 'required|email',
            'admin_password' => 'required|min:8',
            'locales' => 'required|array|min:1',
            'locales.*' => 'string|in:'.implode(',', array_keys(config('languages', []))),
            // MySQL fields (conditional)
            'db_host' => 'required_if:db_driver,mysql',
            'db_port' => 'required_if:db_driver,mysql|nullable|integer',
            'db_database' => 'required_if:db_driver,mysql',
            'db_username' => 'required_if:db_driver,mysql',
            'db_password' => 'nullable|string',
        ]);

        $driver = $request->input('db_driver');

        // Update .env
        $envPath = base_path('.env');
        if (! file_exists($envPath)) {
            copy(base_path('.env.example'), $envPath);
        }

        $replacements = [
            'APP_NAME' => $request->input('app_name'),
            'DB_CONNECTION' => $driver,
        ];

        if ($driver === 'sqlite') {
            $sqlitePath = database_path('database.sqlite');
            if (! file_exists($sqlitePath)) {
                touch($sqlitePath);
            }
            $replacements['DB_DATABASE'] = $sqlitePath;
            $replacements['DB_HOST'] = '127.0.0.1';
            $replacements['DB_PORT'] = '3306';
            $replacements['DB_USERNAME'] = 'root';
            $replacements['DB_PASSWORD'] = '';
        } else {
            $replacements['DB_HOST'] = $request->input('db_host', '127.0.0.1');
            $replacements['DB_PORT'] = $request->input('db_port', '3306');
            $replacements['DB_DATABASE'] = $request->input('db_database');
            $replacements['DB_USERNAME'] = $request->input('db_username');
            $replacements['DB_PASSWORD'] = $request->input('db_password', '');
        }

        $this->updateEnv($envPath, $replacements);

        // Reconfigure database at runtime
        if ($driver === 'sqlite') {
            config(['database.default' => 'sqlite']);
            config(['database.connections.sqlite.database' => database_path('database.sqlite')]);
        } else {
            config(['database.default' => 'mysql']);
            config(['database.connections.mysql.host' => $replacements['DB_HOST']]);
            config(['database.connections.mysql.port' => $replacements['DB_PORT']]);
            config(['database.connections.mysql.database' => $replacements['DB_DATABASE']]);
            config(['database.connections.mysql.username' => $replacements['DB_USERNAME']]);
            config(['database.connections.mysql.password' => $replacements['DB_PASSWORD']]);
        }

        DB::purge();
        DB::reconnect();

        // Test connection
        try {
            DB::connection()->getPdo();
        } catch (\Exception $e) {
            return back()->withInput()->withErrors([
                'db_driver' => 'Database connection failed: '.$e->getMessage(),
            ]);
        }

        // Run migrations and seed (standard package: roles, federations, certifications, dive rules)
        Artisan::call('migrate', ['--force' => true]);
        Artisan::call('db:seed', ['--force' => true]);

        // Save enabled locales and club name
        ThemeSetting::set('enabled_locales', json_encode($request->input('locales')));
        ThemeSetting::set('club_full_name', $request->input('app_name'));

        // Create admin user
        $admin = User::create([
            'name' => 'Administrator',
            'email' => $request->input('admin_email'),
            'password' => Hash::make($request->input('admin_password')),
            'email_verified_at' => now(),
        ]);

        // Assign bureau_master role if roles table exists
        $masterRole = Role::where('slug', 'bureau_master')->first();
        if ($masterRole) {
            $admin->role_id = $masterRole->id;
            $admin->save();
        }

        Artisan::call('key:generate', ['--force' => true]);

        file_put_contents(storage_path('installed.lock'), now()->toIso8601String());

        return redirect('/')->with('success', 'Installation complete! Log in with your admin credentials.');
    }

    private function updateEnv(string $path, array $values): void
    {
        $content = file_get_contents($path);

        foreach ($values as $key => $value) {
            $escaped = str_contains($value, ' ') || str_contains($value, '#')
                ? '"'.$value.'"'
                : $value;

            if (preg_match("/^{$key}=.*/m", $content)) {
                $content = preg_replace("/^{$key}=.*/m", "{$key}={$escaped}", $content);
            } else {
                $content .= "\n{$key}={$escaped}";
            }
        }

        file_put_contents($path, $content);
    }
}

```

### File: app/Http/Controllers/ContactMemberController.php
```php
<?php

namespace App\Http\Controllers;

use App\Models\EmailLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class ContactMemberController extends Controller
{
    public function create(User $user): View
    {
        abort_if($user->id === auth()->id(), 403);

        return view('contact-member', ['target' => $user]);
    }

    public function store(Request $request, User $user): RedirectResponse
    {
        abort_if($user->id === auth()->id(), 403);

        $data = $request->validate([
            'subject' => 'required|string|max:200',
            'message' => 'required|string|max:5000',
        ]);

        $sender = auth()->user();
        $replyTo = $sender->primary_email;
        $senderName = $sender->username ?? $sender->primary_email;

        Mail::raw($data['message'], function ($mail) use ($user, $replyTo, $senderName, $data) {
            $mail->to($user->primary_email)
                ->replyTo($replyTo, $senderName)
                ->subject($data['subject']);
        });

        EmailLog::create([
            'user_id' => $sender->id,
            'to_email' => $user->primary_email,
            'from_email' => $replyTo,
            'from_name' => $senderName,
            'subject' => $data['subject'],
            'body' => $data['message'],
            'status' => 'sent',
            'direction' => 'contact',
        ]);

        return back()->with('success', __('Message sent to :name.', ['name' => $user->username]));
    }
}

```

### File: app/Http/Controllers/HomeController.php
```php
<?php

/**
 * Public-facing homepage and CMS article rendering.
 *
 * index() loads the configurable widget layout (hero, articles, events, etc.)
 * with per-widget visibility filtering. showArticle() renders CMS pages with
 * auto-translation, stale refresh, and live member statistics for the
 * member-figures slug.
 *
 * @author ClubCEP.eu
 */

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\MemberDetail;
use App\Services\ArticleTranslationService;

class HomeController extends Controller
{
    public function index()
    {
        $layout = HomepageLayoutController::getLayout();
        $widgetTypes = HomepageLayoutController::widgetTypes();
        $user = auth()->user();

        // Load data for each enabled + visible widget
        $widgets = collect($layout)->map(function ($w) use ($user) {
            if (! ($w['enabled'] ?? false)) {
                return $w;
            }
            if (! HomepageLayoutController::isVisibleTo($w, $user)) {
                $w['hidden_by_role'] = true;

                return $w;
            }
            $w['data'] = HomepageLayoutController::loadWidgetData($w);

            return $w;
        });

        $isAdmin = $user?->isBureauMaster();

        return view('home', compact('widgets', 'widgetTypes', 'isAdmin'));
    }

    public function showArticle(string $slug)
    {
        $article = Article::where('slug', $slug)->active()->with('translations')->firstOrFail();

        if (! $article->is_public && ! auth()->check()) {
            return redirect()->route('login');
        }

        $extra = [];

        // Dynamic instructor list for the instructors page
        if ($slug === 'instructors') {
            $extra['instructors'] = MemberDetail::whereNotNull('instructor_bio')
                ->where('instructor_bio', '!=', '')
                ->where('show_on_public_site', true)
                ->with('user')
                ->get();
        }

        // Live member statistics charts
        if ($slug === 'member-figures') {
            $details = MemberDetail::whereHas('user', fn ($q) => $q->whereNotNull('status_id'))->get();

            $extra['memberStats'] = [
                'gender' => $details->groupBy('sex')->map->count()->sortDesc(),
                'age' => $details->filter(fn ($d) => $d->date_of_birth)
                    ->groupBy(fn ($d) => (int) floor($d->date_of_birth->age / 10) * 10)
                    ->map->count()->sortKeys()
                    ->mapWithKeys(fn ($v, $k) => [$k.'-'.($k + 9) => $v]),
                'certification' => $details->filter(fn ($d) => $d->certification_level)
                    ->groupBy('certification_level')->map->count()->sortDesc()->take(12),
                'nationality' => $details->filter(fn ($d) => $d->nationality)
                    ->groupBy('nationality')->map->count()->sortDesc()->take(15),
                'language' => $details->filter(fn ($d) => $d->preferred_language)
                    ->groupBy('preferred_language')->map->count()->sortDesc(),
                'total' => $details->count(),
            ];
        }

        // Auto-translate: generate user's locale if missing, and refresh any stale translations
        $locale = app()->getLocale();
        $svc = app(ArticleTranslationService::class);
        try {
            if ($locale !== 'fr' && ! $article->translations->contains('locale', $locale)) {
                $svc->translate($article, $locale);
            }
            foreach ($article->translations->where('stale', true) as $stale) {
                $svc->translate($article, $stale->locale);
            }
            $article->load('translations');
        } catch (\Throwable) {
            // Translation API unavailable — show existing/original
        }

        // Available translation locales for tab UI
        $extra['translatedLocales'] = $article->translations->pluck('locale')->toArray();

        return view('cms.article', compact('article') + $extra);
    }
}

```

### File: app/Http/Controllers/DiveGroupController.php
```php
<?php

/**
 * Dive group (palanquée) management with Trello-style board UI.
 *
 * Manages dive group composition for events: manual CRUD, drag-drop member
 * assignment, rule validation against FFESSM/CMAS federation rules, and
 * auto-proposal of groups for the fiche de sécurité (safety sheet).
 *
 * @author  ClubCEP.eu
 *
 * @see     \App\Services\DiveGroupProposalService  — auto-proposal algorithm
 * @see     \App\Models\DiveGroupRule               — federation rule definitions
 * @see     resources/views/events/dive-groups.blade.php
 */

namespace App\Http\Controllers;

use App\Models\DiveGroup;
use App\Models\DiveGroupMember;
use App\Models\DiveGroupRule;
use App\Models\Event;
use App\Models\User;
use App\Services\DiveGroupProposalService;
use App\Services\Homogeneity\DiveContext;
use App\Services\Homogeneity\HomogeneityAssessmentService;
use App\Services\SwapSuggestionService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class DiveGroupController extends Controller
{
    // ─── Board View & CRUD ─────────────────────────────────────

    public function index(Event $event)
    {
        abort_unless($this->canView($event), 403);
        $event->load(['diveGroups.members.user.certificationLevels.federation', 'diveGroups.members.user.detail', 'registrations.user.certificationLevels.federation', 'registrations.user.detail']);
        $rules = DiveGroupRule::active()->orderBy('scope')->orderBy('min_leader_rank')->get();

        // Participants not yet assigned to any group
        $assignedIds = $event->diveGroups->flatMap(fn ($g) => $g->members->pluck('user_id'))->toArray();
        $unassigned = $event->registrations->where('status', 'confirmed')
            ->filter(fn ($r) => ! in_array($r->user_id, $assignedIds));

        // Stale detection: groups may be invalid if registrations changed after last group edit
        $groupsStale = false;
        if ($event->diveGroups->count()) {
            $lastGroupEdit = $event->diveGroups->max('updated_at');
            $confirmedRegs = $event->registrations->where('status', 'confirmed');
            $lastRegChange = $confirmedRegs->max('updated_at');
            // Also check for cancelled registrations whose user is still in a group
            $cancelledInGroup = $event->registrations->where('status', 'cancelled')
                ->whereIn('user_id', $assignedIds)->count();
            $groupsStale = ($lastRegChange && $lastRegChange > $lastGroupEdit) || $cancelledInGroup > 0 || $unassigned->count() > 0;
        }

        return view('events.dive-groups', compact('event', 'rules', 'unassigned', 'groupsStale'));
    }

    public function store(Request $request, Event $event)
    {
        abort_unless($this->canManage($event), 403);

        $request->validate([
            'name' => 'nullable|string|max:100',
            'dive_mode' => 'required|in:supervised,autonomous,training,certification',
            'planned_depth' => 'nullable|integer|min:1|max:300',
            'planned_duration' => 'nullable|integer|min:1|max:300',
            'gas_mix' => 'nullable|in:'.implode(',', array_keys(DiveGroup::GAS_MIXES)),
            'line_number' => 'nullable|integer|min:1|max:4',
            'planned_entry_time' => 'nullable|date_format:H:i',
            'planned_exit_time' => 'nullable|date_format:H:i',
            'notes' => 'nullable|string|max:500',
            'purpose' => 'nullable|string|max:50',
        ]);

        $group = DiveGroup::create([
            'event_id' => $event->id,
            'name' => $request->name ?: __('Group').' '.($event->diveGroups()->count() + 1),
            'dive_mode' => $request->dive_mode,
            'planned_depth' => $request->planned_depth,
            'planned_duration' => $request->planned_duration,
            'gas_mix' => $request->gas_mix ?? 'air',
            'line_number' => $request->line_number,
            'planned_entry_time' => $request->planned_entry_time,
            'planned_exit_time' => $request->planned_exit_time,
            'purpose' => $request->purpose,
            'notes' => $request->notes,
            'created_by' => auth()->id(),
        ]);

        return back()->with('success', __('Dive group created.'));
    }

    public function addMember(Request $request, DiveGroup $group)
    {
        abort_unless($this->canManage($group->event), 403);

        $request->validate([
            'user_id' => 'required|exists:users,id',
            'role' => 'required|in:leader,diver',
        ]);

        // Prevent duplicate
        if ($group->members()->where('user_id', $request->user_id)->exists()) {
            return back()->with('error', __('Already in this group.'));
        }

        DiveGroupMember::create([
            'dive_group_id' => $group->id,
            'user_id' => $request->user_id,
            'role' => $request->role,
        ]);

        return back()->with('success', __('Member added to group.'));
    }

    public function removeMember(DiveGroupMember $member)
    {
        abort_unless($this->canManage($member->group->event), 403);
        $member->delete();

        return back()->with('success', __('Member removed from group.'));
    }

    public function destroy(DiveGroup $group)
    {
        abort_unless($this->canManage($group->event), 403);
        $group->delete();

        return back()->with('success', __('Dive group deleted.'));
    }

    // ─── Rule Validation ──────────────────────────────────────

    /**
     * Validate all groups for an event against rules. Returns JSON.
     */
    public function validate_groups(Event $event)
    {
        $event->load(['diveGroups.members.user.certificationLevels', 'diveGroups.members.user.detail', 'diveSite']);
        $rules = DiveGroupRule::active()->get();
        $violations = [];
        $homogeneity = [];
        $assessor = new HomogeneityAssessmentService;

        foreach ($event->diveGroups as $group) {
            $groupKey = $group->name ?? 'Group '.$group->id;

            $groupViolations = $this->checkGroup($group, $rules);
            if ($groupViolations) {
                $violations[$groupKey] = $groupViolations;
            }

            // Homogeneity assessment
            $diverProfiles = $group->members->map(fn ($m) => $this->buildDiverProfile($m->user))->toArray();
            $ctx = new DiveContext(
                plannedDepth: $group->planned_depth ?? $event->diveSite?->max_depth ?? 20,
                waterTempCelsius: $event->diveSite?->water_temp ?? 15.0,
            );
            $result = $assessor->assess($diverProfiles, $ctx);
            $homogeneity[$groupKey] = [
                'score' => $result->score,
                'status' => $result->status->value,
                'factors' => array_map(fn ($f) => [
                    'type' => $f->type->value,
                    'impact' => $f->scoreImpact,
                    'label' => $f->label,
                    'detail' => $f->detail,
                ], $result->factors),
                'recommendations' => $result->recommendations,
            ];
        }

        return response()->json([
            'valid' => empty($violations),
            'violations' => $violations,
            'homogeneity' => $homogeneity,
        ]);
    }

    /** Build a diver profile array for the homogeneity service. */
    private function buildDiverProfile(User $user): array
    {
        $detail = $user->detail;
        $cert = $this->getHighestCert($user);

        return [
            'name' => $user->name,
            'airConsumption' => (float) ($detail?->air_consumption ?? 0.5),
            'easeLevel' => (float) ($detail?->ease_level ?? 0.5),
            'primaryIntent' => $detail?->primary_intent ?? 'exploration',
            'isPhotographer' => (bool) ($detail?->is_photographer ?? false),
            'certRank' => $cert?->rank ?? 0,
            'totalDives' => (int) ($detail?->total_dives ?? $detail?->dive_count ?? 50),
            'lastDiveWeeksAgo' => $detail?->last_dive_date
                ? (int) now()->diffInWeeks($detail->last_dive_date)
                : 12,
            'age' => $detail?->date_of_birth?->age ?? 30,
            'isFragile' => ($detail?->date_of_birth?->age ?? 30) >= 65 || ($detail?->date_of_birth?->age ?? 30) < 16,
        ];
    }

    // ─── Auto-Proposal (Fiche de Sécurité) ───────────────────

    /**
     * Auto-propose dive groups based on federation rules (fiche de sécurité).
     * Returns JSON with proposed groups for preview before applying.
     */
    public function propose(Request $request, Event $event)
    {
        abort_unless($this->canManage($event), 403);

        $maxDepth = $request->input('max_depth', $event->diveSite?->max_depth ?? 20);
        $proposal = app(DiveGroupProposalService::class)->propose($event, (int) $maxDepth);

        return response()->json($proposal);
    }

    /**
     * Apply a proposed group configuration: clears existing groups and creates
     * new ones from the proposal. Saves the configuration so it can be reused
     * as a starting point if registrations change.
     */
    public function applyProposal(Request $request, Event $event)
    {
        abort_unless($this->canManage($event), 403);

        $request->validate([
            'groups' => 'required|array',
            'groups.*.name' => 'required|string|max:100',
            'groups.*.dive_mode' => 'required|in:supervised,autonomous,training,certification',
            'groups.*.planned_depth' => 'nullable|integer|min:1',
            'groups.*.leader_id' => 'required|exists:users,id',
            'groups.*.member_ids' => 'array',
            'groups.*.member_ids.*' => 'exists:users,id',
        ]);

        // Clear existing groups for this event
        $event->diveGroups()->delete();

        foreach ($request->groups as $g) {
            $group = DiveGroup::create([
                'event_id' => $event->id,
                'name' => $g['name'],
                'dive_mode' => $g['dive_mode'],
                'planned_depth' => $g['planned_depth'] ?? null,
                'created_by' => auth()->id(),
            ]);

            // Add leader
            DiveGroupMember::create([
                'dive_group_id' => $group->id,
                'user_id' => $g['leader_id'],
                'role' => 'leader',
            ]);

            // Add divers
            foreach ($g['member_ids'] ?? [] as $uid) {
                DiveGroupMember::create([
                    'dive_group_id' => $group->id,
                    'user_id' => $uid,
                    'role' => 'diver',
                ]);
            }
        }

        return back()->with('success', __('Dive groups applied from proposal.'));
    }

    // ─── Rule Checking Engine ──────────────────────────────────

    /**
     * Suggest member swaps between groups to improve homogeneity scores.
     */
    public function suggestSwaps(Event $event)
    {
        abort_unless($this->canManage($event), 403);

        return response()->json(app(SwapSuggestionService::class)->suggest($event));
    }

    // Validates leader qualification, depth limits, and group size against
    // active DiveGroupRules. Federation-specific rules take priority over global.

    private function checkGroup(DiveGroup $group, $rules): array
    {
        $violations = [];
        $members = $group->members;

        if ($members->isEmpty()) {
            return [__('Group is empty.')];
        }

        // Find leader
        $leaderMember = $members->firstWhere('role', 'leader');
        if (! $leaderMember) {
            $violations[] = __('No group leader assigned.');

            return $violations;
        }

        $leaderCert = $this->getHighestCert($leaderMember->user);
        $leaderRank = $leaderCert?->rank ?? 0;
        $leaderCategory = $leaderCert?->category;

        // Check group size
        if ($members->count() > 4) {
            $violations[] = __('Group exceeds maximum size of 4.');
        }

        // Check each diver against applicable rules
        $diverMembers = $members->where('role', 'diver');
        foreach ($diverMembers as $dm) {
            $diverCert = $this->getHighestCert($dm->user);
            $diverRank = $diverCert?->rank ?? 0;
            $diverFed = $diverCert?->federation?->acronym;

            // Find applicable rules (federation-specific first, then global)
            $applicable = $rules->filter(function ($rule) use ($diverRank, $group, $diverFed) {
                if ($rule->dive_mode !== $group->dive_mode) {
                    return false;
                }
                if (! $rule->matchesDiver($diverRank)) {
                    return false;
                }

                // Prefer federation-specific rules
                return $rule->scope === 'global' || $rule->scope === $diverFed;
            })->sortByDesc(fn ($r) => $r->scope !== 'global' ? 1 : 0);

            $rule = $applicable->first();
            if (! $rule) {
                continue;
            } // No rule applies — allowed

            // Check leader qualification
            if (! $rule->leaderSatisfied($leaderRank, $leaderCategory)) {
                $violations[] = __(':diver requires a leader with at least rank :rank (:cat) — current leader: :leader', [
                    'diver' => $dm->user->name,
                    'rank' => $rule->min_leader_rank,
                    'cat' => $rule->leader_category,
                    'leader' => $leaderMember->user->name.' (rank '.$leaderRank.')',
                ]);
            }

            // Check depth
            if ($rule->max_depth && $group->planned_depth && $group->planned_depth > $rule->max_depth) {
                $violations[] = __(':diver max depth :max m (planned: :planned m)', [
                    'diver' => $dm->user->name,
                    'max' => $rule->max_depth,
                    'planned' => $group->planned_depth,
                ]);
            }

            // Check group size from rule
            if ($rule->max_group_size && $members->count() > $rule->max_group_size) {
                $violations[] = __(':rule limits group to :max members', [
                    'rule' => $rule->name,
                    'max' => $rule->max_group_size,
                ]);
            }
        }

        return array_unique($violations);
    }

    // ─── PDF Export (Fiche de Sécurité) ────────────────────────

    /** Generate a printable fiche de sécurité PDF for the event's dive groups. */
    public function printFiche(Event $event)
    {
        abort_unless($this->canView($event), 403);
        $event->load(['diveGroups.members.user.certificationLevels.federation', 'diveGroups.members.user.detail', 'diveSite', 'registrations']);

        $pdf = Pdf::loadView('events.fiche-securite-pdf', compact('event'))
            ->setPaper('a4', 'landscape');

        $filename = 'fiche-securite-'.$event->event_date->format('Y-m-d').'-'.\Str::slug($event->title).'.pdf';

        return $pdf->download($filename);
    }

    // ─── Authorization ────────────────────────────────────────

    private function getHighestCert($user): ?object
    {
        return $user->certificationLevels
            ->where('category', '!=', 'specialty')
            ->sortByDesc('rank')
            ->first();
    }

    /** Bureau, event instructor, or event assistants can manage groups. */
    private function canManage(Event $event): bool
    {
        $user = auth()->user();

        return $user->isBureau() || $event->instructor_id === $user->id || in_array($user->id, $event->assistant_ids ?? []);
    }

    private function canView(Event $event): bool
    {
        $user = auth()->user();
        if ($this->canManage($event)) {
            return true;
        }

        // Instructors can always view dive groups
        return $user->hasAnyRole(['instructor']);
    }
}

```

### File: app/Http/Controllers/DiveDataController.php
```php
<?php

/**
 * Dive data import/export: UDDF upload, UDDF download, DAN DL7 export.
 *
 * Members can upload UDDF files from their dive computers to populate
 * dive logs. Bureau can export all club dive data as UDDF or DAN DL7
 * for research submission.
 *
 * @author ClubCEP.eu
 */

namespace App\Http\Controllers;

use App\Models\DiveGroupMember;
use App\Models\Event;
use App\Services\DanExportService;
use App\Services\UddfService;
use Illuminate\Http\Request;

class DiveDataController extends Controller
{
    /**
     * Upload a UDDF file and match dives to events by date.
     * Members upload their own; bureau can upload for any member.
     */
    public function importUddf(Request $request)
    {
        $request->validate(['uddf_file' => 'required|file|mimes:xml,uddf|max:10240']);

        $xml = file_get_contents($request->file('uddf_file')->getRealPath());
        $service = new UddfService;
        $parsed = $service->parse($xml);

        $matched = 0;
        foreach ($parsed['dives'] as $dive) {
            // Try to match to an event on the same date
            $event = Event::whereDate('event_date', $dive['datetime']->toDateString())->first();
            if (! $event) {
                continue;
            }

            // Find user's dive group in this event
            $dgm = DiveGroupMember::whereHas('diveGroup', fn ($q) => $q->where('event_id', $event->id))
                ->where('user_id', auth()->id())
                ->first();

            if (! $dgm) {
                continue;
            }

            // Update the dive group with actual data
            $group = $dgm->diveGroup;
            $group->update(array_filter([
                'actual_depth' => $dive['max_depth'] ?: null,
                'actual_duration' => $dive['duration_minutes'] ?: null,
            ]));

            $matched++;
        }

        return back()->with('success', __(':count dive(s) matched and updated from UDDF file.', ['count' => $matched]));
    }

    /** Export a user's dive history as UDDF XML. */
    public function exportUddf(Request $request)
    {
        $user = $request->user();
        $memberships = DiveGroupMember::where('user_id', $user->id)
            ->with(['diveGroup.event.diveSite'])
            ->get();

        $xml = (new UddfService)->export($user, $memberships);

        return response($xml, 200, [
            'Content-Type' => 'application/xml',
            'Content-Disposition' => 'attachment; filename="divelog_'.$user->id.'.uddf"',
        ]);
    }

    /** Export all club dive data as DAN DL7 (bureau only). */
    public function exportDan(Request $request)
    {
        $year = $request->input('year', now()->year);

        $memberships = DiveGroupMember::with(['user.detail', 'diveGroup.event.diveSite'])
            ->whereHas('diveGroup.event', fn ($q) => $q->whereYear('event_date', $year))
            ->get();

        $dl7 = (new DanExportService)->export($memberships);

        return response($dl7, 200, [
            'Content-Type' => 'text/plain',
            'Content-Disposition' => 'attachment; filename="dan_export_'.$year.'.dl7"',
        ]);
    }
}

```

### File: app/Http/Controllers/BuddyController.php
```php
<?php

namespace App\Http\Controllers;

use App\Models\BuddyRequest;
use App\Models\BuddyResponse;
use App\Models\DiveSite;
use Illuminate\Http\Request;

class BuddyController extends Controller
{
    public function index()
    {
        $requests = BuddyRequest::active()
            ->with(['user.detail', 'user.certificationLevels.federation', 'diveSite', 'responses.user.detail'])
            ->orderBy('dive_date')
            ->get();
        $sites = DiveSite::active()->orderBy('name')->get();

        return view('buddies.index', compact('requests', 'sites'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'dive_site_id' => 'nullable|exists:dive_sites,id',
            'location_text' => 'nullable|string|max:255',
            'dive_date' => 'required|date|after_or_equal:today',
            'dive_time' => 'nullable|string|max:50',
            'need_type' => 'required|in:buddy,guide,dp',
            'description' => 'nullable|string|max:1000',
            'max_depth' => 'nullable|integer|min:1|max:300',
            'desired_cert_level' => 'nullable|string|max:50',
            'max_buddies' => 'nullable|integer|min:1|max:10',
        ]);
        $data['user_id'] = auth()->id();
        BuddyRequest::create($data);

        return back()->with('success', __('Buddy request posted.'));
    }

    public function respond(Request $request, BuddyRequest $buddyRequest)
    {
        $request->validate(['message' => 'nullable|string|max:500']);

        if ($buddyRequest->user_id === auth()->id()) {
            return back()->with('error', __('Cannot respond to your own request.'));
        }

        BuddyResponse::updateOrCreate(
            ['buddy_request_id' => $buddyRequest->id, 'user_id' => auth()->id()],
            ['message' => $request->message, 'status' => 'interested']
        );

        return back()->with('success', __('Response sent.'));
    }

    public function close(BuddyRequest $buddyRequest)
    {
        abort_unless($buddyRequest->user_id === auth()->id() || auth()->user()->isBureau(), 403);
        $buddyRequest->update(['is_active' => false]);

        return back()->with('success', __('Request closed.'));
    }
}

```

### File: app/Http/Controllers/VotePublicController.php
```php
<?php

namespace App\Http\Controllers;

use App\Models\VoteBallot;
use App\Models\VoteToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VotePublicController extends Controller
{
    public function show(string $token)
    {
        $voteToken = VoteToken::where('token', $token)->with(['vote.options', 'user'])->firstOrFail();
        $vote = $voteToken->vote;

        if (! $vote->isOpen()) {
            return view('vote.closed', compact('vote'));
        }

        $tokenHash = hash('sha256', $token);
        $currentBallots = VoteBallot::where('vote_id', $vote->id)
            ->where('token_hash', $tokenHash)->pluck('vote_option_id')->toArray();

        return view('vote.show', compact('vote', 'voteToken', 'currentBallots'));
    }

    public function cast(Request $request, string $token)
    {
        $voteToken = VoteToken::where('token', $token)->with('vote.options')->firstOrFail();
        $vote = $voteToken->vote;

        if (! $vote->isOpen()) {
            return back()->with('error', __('This vote is no longer open.'));
        }

        $tokenHash = hash('sha256', $token);

        // Election mode: anonymous, irreversible, multi-position
        if ($vote->mode === 'election') {
            if ($voteToken->is_consumed) {
                return back()->with('error', __('You have already voted. Election votes cannot be changed.'));
            }

            $maxSelections = $vote->num_positions ?? 1;

            if ($maxSelections > 1) {
                $request->validate([
                    'option_ids' => 'required|array|min:1|max:'.$maxSelections,
                    'option_ids.*' => 'exists:vote_options,id',
                ]);
                $selectedIds = $request->option_ids;
            } else {
                $request->validate(['option_id' => 'required|exists:vote_options,id']);
                $selectedIds = [$request->option_id];
            }

            DB::transaction(function () use ($vote, $voteToken, $selectedIds) {
                foreach ($selectedIds as $optId) {
                    VoteBallot::create(['vote_id' => $vote->id, 'vote_option_id' => $optId, 'token_hash' => null]);
                }
                $voteToken->update(['is_consumed' => true, 'consumed_at' => now()]);
            });

            return view('vote.thankyou', compact('vote'));
        }

        // Simple/public mode: changeable, optionally multi-select
        if (! $vote->allow_change) {
            $existing = VoteBallot::where('vote_id', $vote->id)->where('token_hash', $tokenHash)->exists();
            if ($existing) {
                return back()->with('error', __('You have already voted and this vote does not allow changes.'));
            }
        }

        if ($vote->allow_multiple) {
            $request->validate(['option_ids' => 'required|array|min:1', 'option_ids.*' => 'exists:vote_options,id']);
            DB::transaction(function () use ($vote, $tokenHash, $request) {
                VoteBallot::where('vote_id', $vote->id)->where('token_hash', $tokenHash)->delete();
                foreach ($request->option_ids as $optId) {
                    VoteBallot::create(['vote_id' => $vote->id, 'vote_option_id' => $optId, 'token_hash' => $tokenHash]);
                }
            });
        } else {
            $request->validate(['option_id' => 'required|exists:vote_options,id']);
            VoteBallot::updateOrCreate(
                ['vote_id' => $vote->id, 'token_hash' => $tokenHash],
                ['vote_option_id' => $request->option_id]
            );
        }

        return back()->with('success', $vote->allow_change
            ? __('Your vote has been recorded. You can change it until the vote closes.')
            : __('Your vote has been recorded.'));
    }
}

```

### File: app/Http/Controllers/InstructorAvailabilityController.php
```php
<?php

namespace App\Http\Controllers;

use App\Models\InstructorAvailability;
use App\Models\Event;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;

class InstructorAvailabilityController extends Controller
{
    /**
     * Activity types with colors matching the old CEP Google Sheet planning.
     */
    public const ACTIVITY_COLORS = [
        'pool'       => ['color' => '#c9daf8', 'text' => '#000', 'icon' => '🏊', 'label' => 'Pool'],
        'pool_kids'  => ['color' => '#6d9eeb', 'text' => '#fff', 'icon' => '👶', 'label' => 'Kids'],
        'pool_pn1'   => ['color' => '#1155cc', 'text' => '#fff', 'icon' => '1️⃣', 'label' => 'PN1'],
        'pool_pn23'  => ['color' => '#c9daf8', 'text' => '#f00', 'icon' => '🔴', 'label' => 'PN2-3'],
        'apnea'      => ['color' => '#00ff00', 'text' => '#000', 'icon' => '🫁', 'label' => 'Apnea'],
        'fosse'      => ['color' => '#93c47d', 'text' => '#000', 'icon' => '🕳️', 'label' => 'Fosse'],
        'quarry'     => ['color' => '#ff00ff', 'text' => '#000', 'icon' => '🪨', 'label' => 'Quarry/Lake'],
        'long_trip'  => ['color' => '#ffe599', 'text' => '#000', 'icon' => '✈️', 'label' => 'Long Trip'],
        'theory'     => ['color' => '#d9d9d9', 'text' => '#000', 'icon' => '📖', 'label' => 'Theory'],
        'steinfort'  => ['color' => '#ff9900', 'text' => '#000', 'icon' => '🟠', 'label' => 'Steinfort'],
    ];

    public function index(Request $request)
    {
        $user = auth()->user();
        $isInstructor = $user->hasAnyRole(['instructor', 'bureau_master', 'bureau_technical', 'assistant']);

        $month = $request->query('month', now()->format('Y-m'));
        $start = \Carbon\Carbon::parse($month . '-01')->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $availabilities = InstructorAvailability::with('user.detail')
            ->whereBetween('date', [$start, $end])
            ->get()
            ->groupBy(fn($a) => $a->date->format('Y-m-d'));

        // Events this month for context
        $events = Event::whereBetween('event_date', [$start, $end])
            ->orderBy('event_date')
            ->get()
            ->groupBy(fn($e) => $e->event_date->format('Y-m-d'));

        $instructorRoleIds = Role::whereIn('slug', ['instructor', 'bureau_master', 'bureau_technical', 'assistant'])->pluck('id');
        $instructors = User::whereIn('role_id', $instructorRoleIds)->with('detail')->get()
            ->sortBy(fn($u) => $u->detail?->first_name);

        $colors = self::ACTIVITY_COLORS;

        return view('availability.index', compact('availabilities', 'events', 'start', 'end', 'isInstructor', 'instructors', 'month', 'colors'));
    }

    public function toggle(Request $request)
    {
        $user = auth()->user();
        if (!$user->hasAnyRole(['instructor', 'bureau_master', 'bureau_technical', 'assistant'])) {
            abort(403);
        }

        $request->validate([
            'date' => 'required|date|after_or_equal:today',
            'slot' => 'required|in:morning,afternoon,evening,full_day',
            'activity_type' => 'required|in:' . implode(',', array_keys(self::ACTIVITY_COLORS)),
        ]);

        $existing = InstructorAvailability::where('user_id', $user->id)
            ->where('date', $request->date)
            ->where('slot', $request->slot)
            ->where('activity_type', $request->activity_type)
            ->first();

        if ($existing) {
            $existing->delete();
            return response()->json(['status' => 'removed']);
        }

        InstructorAvailability::create([
            'user_id' => $user->id,
            'date' => $request->date,
            'slot' => $request->slot,
            'activity_type' => $request->activity_type,
        ]);

        return response()->json(['status' => 'added']);
    }
}

```

### File: app/Http/Controllers/Api/FederationApiController.php
```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ClubPartnership;
use App\Models\Event;
use App\Models\ExternalRegistration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class FederationApiController extends Controller
{
    /**
     * Authenticate inbound API request from a partner club.
     */
    private function authenticate(Request $request): ?ClubPartnership
    {
        $keyId = $request->header('X-Club-Key-Id');
        $secret = $request->header('X-Club-Secret');

        if (! $keyId || ! $secret) {
            return null;
        }

        $partner = ClubPartnership::where('api_key_id', $keyId)->where('is_active', true)->first();
        if (! $partner || ! Hash::check($secret, $partner->api_secret_hash)) {
            return null;
        }

        return $partner;
    }

    /**
     * GET /api/federation/events — list federated events visible to partners.
     */
    public function events(Request $request): JsonResponse
    {
        $partner = $this->authenticate($request);
        if (! $partner) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $events = Event::where('is_federated', true)
            ->where('event_date', '>=', now()->toDateString())
            ->where('status', 'published')
            ->orderBy('event_date')
            ->get()
            ->map(fn (Event $e) => [
                'id' => $e->id,
                'title' => $e->title,
                'event_date' => $e->event_date,
                'event_time' => $e->event_time,
                'end_date' => $e->end_date,
                'location' => $e->location,
                'description' => $e->description,
                'event_type' => $e->event_type,
                'external_slots' => $e->external_slots,
                'slots_taken' => $e->externalRegistrations()->whereIn('status', ['pending', 'approved'])->count(),
                'estimated_cost' => $e->estimated_cost,
                'levels_display' => $e->levels_display,
            ]);

        $partner->update(['last_sync_at' => now()]);

        return response()->json(['events' => $events]);
    }

    /**
     * POST /api/federation/register — register an external member for a federated event.
     */
    public function register(Request $request): JsonResponse
    {
        $partner = $this->authenticate($request);
        if (! $partner) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $data = $request->validate([
            'event_id' => 'required|integer',
            'member_name' => 'required|string|max:200',
            'member_email' => 'nullable|email',
            'member_iban' => 'nullable|string|max:34',
            'cert_level' => 'nullable|string|max:100',
            'medical_valid_until' => 'nullable|date',
            'external_ref' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:500',
        ]);

        $event = Event::where('id', $data['event_id'])->where('is_federated', true)->first();
        if (! $event) {
            return response()->json(['error' => 'Event not found or not federated'], 404);
        }

        // Check slots
        $taken = $event->externalRegistrations()->whereIn('status', ['pending', 'approved'])->count();
        if ($event->external_slots > 0 && $taken >= $event->external_slots) {
            return response()->json(['error' => 'No external slots available'], 409);
        }

        $reg = ExternalRegistration::create([
            'event_id' => $event->id,
            'partnership_id' => $partner->id,
            'external_member_name' => $data['member_name'],
            'external_member_email' => $data['member_email'] ?? null,
            'external_member_iban' => $data['member_iban'] ?? null,
            'external_member_phone' => $data['member_phone'] ?? null,
            'external_member_federation' => $data['member_federation'] ?? null,
            'external_member_licence_no' => $data['member_licence_no'] ?? null,
            'external_member_emergency_contact' => $data['member_emergency_contact'] ?? null,
            'external_cert_level' => $data['cert_level'] ?? null,
            'external_medical_valid_until' => $data['medical_valid_until'] ?? null,
            'external_ref' => $data['external_ref'] ?? null,
            'notes' => $data['notes'] ?? null,
            'status' => 'pending',
        ]);

        return response()->json([
            'registration_id' => $reg->id,
            'status' => 'pending',
            'message' => 'Registration submitted, awaiting approval by organizing club.',
        ], 201);
    }

    /**
     * DELETE /api/federation/register/{id} — cancel an external registration.
     */
    public function cancel(Request $request, int $id): JsonResponse
    {
        $partner = $this->authenticate($request);
        if (! $partner) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $reg = ExternalRegistration::where('id', $id)->where('partnership_id', $partner->id)->first();
        if (! $reg) {
            return response()->json(['error' => 'Registration not found'], 404);
        }

        $reg->update(['status' => 'cancelled']);

        return response()->json(['status' => 'cancelled']);
    }

    /**
     * GET /api/federation/register/{id} — check registration status.
     */
    public function status(Request $request, int $id): JsonResponse
    {
        $partner = $this->authenticate($request);
        if (! $partner) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $reg = ExternalRegistration::where('id', $id)->where('partnership_id', $partner->id)->first();
        if (! $reg) {
            return response()->json(['error' => 'Registration not found'], 404);
        }

        return response()->json([
            'registration_id' => $reg->id,
            'status' => $reg->status,
            'event_title' => $reg->event->title,
            'event_date' => $reg->event->event_date,
        ]);
    }
}

```

### File: app/Http/Controllers/DocumentBrowserController.php
```php
<?php

/**
 * Member-facing document browser with upload for instructors/bureau.
 *
 * Replaces the old read-only document browser. Files are filtered by the
 * user's role: bureau sees everything, instructors see public+members+instructors,
 * regular members see public+members, guests see public only.
 *
 * @author ClubCEP.eu
 */

namespace App\Http\Controllers;

use App\Models\EventPhoto;
use App\Models\LibraryFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DocumentBrowserController extends Controller
{
    /** Photo gallery — all approved photos grouped by event. */
    public function gallery()
    {
        $user = auth()->user();
        $query = $user ? EventPhoto::bestForMembers(200) : EventPhoto::bestPublic(200);

        $photos = $query->with('event:id,title,event_date')->get()
            ->groupBy(fn ($p) => $p->event?->title ?? __('Other'));

        return view('gallery', compact('photos'));
    }

    public function index(Request $request)
    {
        $user = auth()->user();
        $folder = $request->get('folder', '/');

        $files = LibraryFile::visibleTo($user)->inFolder($folder)
            ->where('original_name', '!=', '.folder')
            ->orderBy('original_name')->get();

        // Build folder tree from all visible files
        $folders = LibraryFile::visibleTo($user)
            ->selectRaw('DISTINCT folder')->orderBy('folder')->pluck('folder')
            ->flatMap(fn ($f) => collect(explode('/', trim($f, '/')))->filter()->reduce(function ($carry, $part) {
                $carry[] = ($carry->last() ?? '').'/'.$part;

                return $carry;
            }, collect()))
            ->prepend('/')
            ->unique()
            ->sort()
            ->values();

        $canManage = LibraryFile::canManage($user);

        // Subfolders of current folder (direct children only)
        $subfolders = $folders->filter(function ($f) use ($folder) {
            if ($f === $folder) {
                return false;
            }
            $parent = dirname($f) === '.' ? '/' : dirname($f);

            return $parent === rtrim($folder, '/') || ($folder === '/' && $parent === '');
        })->values();

        return view('documents.index', compact('files', 'folder', 'folders', 'subfolders', 'canManage'));
    }

    public function upload(Request $request)
    {
        abort_unless(LibraryFile::canManage(auth()->user()), 403);

        $request->validate([
            'files' => 'required|array|min:1',
            'files.*' => 'file|max:51200',
            'folder' => 'required|string',
            'visibility' => 'required|in:public,members,instructors,bureau',
            'description' => 'nullable|string|max:500',
        ]);

        foreach ($request->file('files') as $file) {
            $path = $file->store('library', 'local');
            LibraryFile::create([
                'filename' => basename($path),
                'original_name' => $file->getClientOriginalName(),
                'path' => $path,
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
                'folder' => $request->input('folder'),
                'visibility' => $request->input('visibility'),
                'description' => $request->input('description'),
                'uploaded_by' => auth()->id(),
            ]);
        }

        // Remove folder placeholder now that real files exist
        LibraryFile::where('folder', $request->input('folder'))
            ->where('original_name', '.folder')->delete();

        return back()->with('success', __(':count file(s) uploaded.', ['count' => count($request->file('files'))]));
    }

    public function createFolder(Request $request)
    {
        abort_unless(LibraryFile::canManage(auth()->user()), 403);
        $request->validate(['name' => 'required|string|max:100|regex:/^[a-zA-Z0-9_\- ]+$/']);

        $parent = $request->input('parent', '/');
        $newFolder = rtrim($parent, '/').'/'.$request->input('name');

        // Check if folder already has files
        if (LibraryFile::where('folder', $newFolder)->exists()) {
            return redirect()->route('documents.index', ['folder' => $newFolder]);
        }

        // Create a hidden placeholder so the folder appears in the sidebar
        LibraryFile::create([
            'filename' => '.folder',
            'original_name' => '.folder',
            'path' => '',
            'mime_type' => 'inode/directory',
            'size' => 0,
            'folder' => $newFolder,
            'visibility' => 'members',
            'uploaded_by' => auth()->id(),
        ]);

        return redirect()->route('documents.index', ['folder' => $newFolder])
            ->with('success', __('Folder created.'));
    }

    public function updateFile(Request $request, LibraryFile $file)
    {
        abort_unless(LibraryFile::canManage(auth()->user()), 403);

        $request->validate([
            'visibility' => 'required|in:public,members,instructors,bureau',
            'folder' => 'nullable|string',
            'description' => 'nullable|string|max:500',
        ]);

        $file->update($request->only('visibility', 'folder', 'description'));

        return back()->with('success', __('File updated.'));
    }

    public function destroy(LibraryFile $file)
    {
        abort_unless(LibraryFile::canManage(auth()->user()), 403);
        Storage::disk('local')->delete($file->path);
        $file->delete();

        return back()->with('success', __('File deleted.'));
    }

    public function download(LibraryFile $file)
    {
        // Verify visibility access
        $user = auth()->user();
        $visible = LibraryFile::visibleTo($user)->where('id', $file->id)->exists();
        abort_unless($visible, 403);

        return Storage::disk('local')->download($file->path, $file->original_name);
    }

    public function thumb(LibraryFile $file)
    {
        $user = auth()->user();
        abort_unless(LibraryFile::visibleTo($user)->where('id', $file->id)->exists(), 403);

        if (! $file->hasThumb() || ! Storage::disk('local')->exists($file->path)) {
            abort(404);
        }

        return response()->file(Storage::disk('local')->path($file->path));
    }
}

```

### File: app/Http/Controllers/Admin/DiveGroupRuleController.php
```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DiveGroupRule;
use App\Models\Federation;
use Illuminate\Http\Request;

class DiveGroupRuleController extends Controller
{
    public function index()
    {
        $rules = DiveGroupRule::orderBy('scope')->orderBy('dive_mode')->orderBy('min_leader_rank')->get();
        $federations = Federation::orderBy('acronym')->pluck('acronym');
        return view('admin.dive-group-rules.index', compact('rules', 'federations'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'scope' => 'required|string|max:50',
            'diver_condition' => 'required|string|max:50',
            'dive_mode' => 'required|in:supervised,autonomous,training,certification',
            'min_leader_rank' => 'required|integer|min:0',
            'leader_category' => 'required|in:instructor,diver',
            'max_depth' => 'nullable|integer|min:1|max:300',
            'max_group_size' => 'required|integer|min:1|max:10',
            'description' => 'nullable|string',
        ]);
        DiveGroupRule::create($data);
        return back()->with('success', __('Rule created.'));
    }

    public function update(Request $request, DiveGroupRule $rule)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'scope' => 'required|string|max:50',
            'diver_condition' => 'required|string|max:50',
            'dive_mode' => 'required|in:supervised,autonomous,training,certification',
            'min_leader_rank' => 'required|integer|min:0',
            'leader_category' => 'required|in:instructor,diver',
            'max_depth' => 'nullable|integer|min:1|max:300',
            'max_group_size' => 'required|integer|min:1|max:10',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);
        $data['is_active'] = $request->boolean('is_active');
        $rule->update($data);
        return back()->with('success', __('Rule updated.'));
    }

    public function destroy(DiveGroupRule $rule)
    {
        $rule->delete();
        return back()->with('success', __('Rule deleted.'));
    }
}

```

### File: app/Http/Controllers/Admin/LinkController.php
```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Link;
use Illuminate\Http\Request;

class LinkController extends Controller
{
    public function index()
    {
        $links = Link::orderBy('sort_order')->get();
        return view('admin.links.index', compact('links'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'url' => 'required|url|max:500',
            'description' => 'nullable|string',
            'is_public' => 'boolean',
            'sort_order' => 'integer',
        ]);
        $validated['is_public'] = $request->boolean('is_public');

        Link::create($validated);
        return back()->with('success', __('Link added.'));
    }

    public function destroy(Link $link)
    {
        $link->delete();
        return back()->with('success', __('Link removed.'));
    }
}

```

### File: app/Http/Controllers/Admin/LibraryController.php
```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LibraryFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class LibraryController extends Controller
{
    public function index(Request $request)
    {
        $folder = $request->get('folder', '/');
        $files = LibraryFile::inFolder($folder)->orderBy('original_name')->get();

        // Get distinct folders for navigation
        $folders = LibraryFile::selectRaw('DISTINCT folder')->orderBy('folder')->pluck('folder')
            ->flatMap(fn ($f) => collect(explode('/', trim($f, '/')))->filter()->reduce(function ($carry, $part) {
                $carry[] = ($carry->last() ?? '').'/'.$part;

                return $carry;
            }, collect()))
            ->prepend('/')
            ->unique()
            ->sort()
            ->values();

        return view('admin.library.index', compact('files', 'folder', 'folders'));
    }

    public function upload(Request $request)
    {
        $request->validate([
            'files' => 'required|array|min:1',
            'files.*' => 'file|max:51200',
            'folder' => 'required|string',
            'visibility' => 'required|in:public,members,instructors,bureau',
            'description' => 'nullable|string|max:500',
        ]);

        $folder = $request->input('folder', '/');

        foreach ($request->file('files') as $file) {
            $path = $file->store('library', 'local');
            LibraryFile::create([
                'filename' => basename($path),
                'original_name' => $file->getClientOriginalName(),
                'path' => $path,
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
                'folder' => $folder,
                'visibility' => $request->input('visibility'),
                'description' => $request->input('description'),
                'uploaded_by' => auth()->id(),
            ]);
        }

        return back()->with('success', __('Files uploaded.'));
    }

    public function update(Request $request, LibraryFile $file)
    {
        $request->validate([
            'visibility' => 'required|in:public,members,instructors,bureau',
            'folder' => 'required|string',
            'description' => 'nullable|string|max:500',
        ]);

        $file->update($request->only('visibility', 'folder', 'description'));

        return back()->with('success', __('File updated.'));
    }

    public function destroy(LibraryFile $file)
    {
        Storage::disk('local')->delete($file->path);
        $file->delete();

        return back()->with('success', __('File deleted.'));
    }

    public function download(LibraryFile $file)
    {
        return Storage::disk('local')->download($file->path, $file->original_name);
    }

    public function createFolder(Request $request)
    {
        $request->validate(['folder' => 'required|string|max:255']);

        // Folders are implicit — just redirect to the new folder view
        return redirect()->route('admin.library.index', ['folder' => $request->input('folder')]);
    }
}

```

### File: app/Http/Controllers/Admin/DashboardController.php
```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BankTransaction;
use App\Models\Document;
use App\Models\Equipment;
use App\Models\EquipmentLoan;
use App\Models\EquipmentMaintenance;
use App\Models\Event;
use App\Models\ExternalRegistration;
use App\Models\MemberDetail;
use App\Models\MemberStatus;
use App\Models\PaymentExpected;
use App\Models\ThemeSetting;
use App\Models\User;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $season = $request->get('season', date('Y'));

        $stats = [
            'total_members' => User::count(),
            'members_by_status' => MemberStatus::withCount('users')->get()->map(fn ($s) => ['name' => $s->name, 'count' => $s->users_count]),
            'new_members_this_year' => User::whereYear('created_at', $season)->count(),
            'events_count' => Event::whereYear('event_date', $season)->count(),
            'avg_attendance' => round(Event::whereYear('event_date', $season)->withCount('confirmedRegistrations')->get()->avg('confirmed_registrations_count') ?? 0, 1),
            'equipment_by_status' => Equipment::selectRaw('status, count(*) as cnt')->groupBy('status')->pluck('cnt', 'status'),
            'certs_expiring_30d' => Document::where('category', 'medical')->where('is_current', true)->whereBetween('expiry_date', [now(), now()->addDays(30)])->count(),
            'revenue' => PaymentExpected::where('status', 'paid')->where('season_year', $season)->sum('amount_paid'),
            'outstanding' => PaymentExpected::where('status', 'pending')->where('season_year', $season)->sum('amount_due'),
            'upcoming_birthdays' => MemberDetail::whereNotNull('date_of_birth')
                ->whereBetween(
                    \DB::raw(config('database.default') === 'pgsql'
                        ? 'EXTRACT(DOY FROM date_of_birth)'
                        : 'DAYOFYEAR(date_of_birth)'),
                    [\DB::raw(config('database.default') === 'pgsql' ? 'EXTRACT(DOY FROM NOW())' : 'DAYOFYEAR(NOW())'),
                        \DB::raw(config('database.default') === 'pgsql' ? 'EXTRACT(DOY FROM NOW()) + 30' : 'DAYOFYEAR(NOW()) + 30')]
                )
                ->with('user')->limit(10)->get(),
            'next_events' => Event::where('event_date', '>=', now())->orderBy('event_date')->limit(5)->get(),
        ];

        // Bureau worklist: pending actions
        $worklist = [
            'unverified_certs' => Document::where('category', 'medical')->where('is_current', true)->whereNull('verified_at')->count(),
            'expiring_certs' => Document::where('category', 'medical')->where('is_current', true)->whereBetween('expiry_date', [now(), now()->addDays(30)])->count(),
            'pending_payments' => PaymentExpected::where('status', 'pending')->where('season_year', $season)->count(),
            'pending_external_regs' => ExternalRegistration::where('status', 'pending')->count(),
            'unverified_emails' => User::whereNull('email_verified_at')->count(),
            'missing_medical' => User::whereDoesntHave('documents', fn ($q) => $q->where('category', 'medical')->where('is_current', true))->whereHas('status', fn ($q) => $q->where('slug', 'actif'))->count(),
            'missing_iban' => User::whereHas('detail', fn ($q) => $q->whereNull('iban'))->whereHas('status', fn ($q) => $q->where('slug', 'actif'))->count(),
            'new_members_unconfirmed' => User::whereNull('status_id')->whereNotNull('email_verified_at')->count(),
            'birthdays_14d' => MemberDetail::whereNotNull('date_of_birth')
                ->whereBetween(
                    \DB::raw(config('database.default') === 'pgsql'
                        ? 'EXTRACT(DOY FROM date_of_birth)'
                        : 'DAYOFYEAR(date_of_birth)'),
                    [\DB::raw(config('database.default') === 'pgsql' ? 'EXTRACT(DOY FROM NOW())' : 'DAYOFYEAR(NOW())'),
                        \DB::raw(config('database.default') === 'pgsql' ? 'EXTRACT(DOY FROM NOW()) + 14' : 'DAYOFYEAR(NOW()) + 14')]
                )
                ->with('user')->get(),
            'unmatched_transactions' => BankTransaction::where('status', 'unmatched')->count(),
            'refund_reviews' => PaymentExpected::where('refund_review_needed', true)->count(),
            'overdue_maintenance' => EquipmentMaintenance::where('is_mandatory', true)->whereNull('completed_at')->where('due_date', '<', now())->count(),
            'overdue_loans' => EquipmentLoan::whereNull('returned_at')->where(fn ($q) => $q->where('expected_return_date', '<', now())->orWhere('loaned_at', '<', now()->subDays((int) ThemeSetting::get('equipment_loan_max_days', 30))))->count(),
            'minors_no_guardian' => User::whereHas('detail', fn ($q) => $q->whereNotNull('date_of_birth')
                ->where('date_of_birth', '>', now()->subYears(18)))
                ->whereDoesntHave('guardians')->count(),
        ];

        return view('admin.dashboard.index', compact('stats', 'season', 'worklist'));
    }

    public function exportCsv(Request $request)
    {
        $type = $request->get('type', 'members');

        $headers = ['Content-Type' => 'text/csv', 'Content-Disposition' => "attachment; filename={$type}-export.csv"];

        $callback = function () use ($type) {
            $out = fopen('php://output', 'w');

            if ($type === 'members') {
                fputcsv($out, ['ID', 'First Name', 'Last Name', 'Email', 'Status', 'Role', 'Cert Level', 'Member Since']);
                User::with(['detail', 'status', 'role'])->chunk(100, function ($users) use ($out) {
                    foreach ($users as $u) {
                        fputcsv($out, [$u->id, $u->detail?->first_name, $u->detail?->last_name, $u->primary_email, $u->status?->name, $u->role?->name, $u->detail?->certification_level, $u->detail?->adhesion_year]);
                    }
                });
            } elseif ($type === 'payments') {
                fputcsv($out, ['ID', 'Member', 'Type', 'Amount Due', 'Amount Paid', 'Status', 'Communication']);
                PaymentExpected::with('user.detail')->chunk(100, function ($payments) use ($out) {
                    foreach ($payments as $p) {
                        fputcsv($out, [$p->id, $p->user?->name, $p->type, $p->amount_due, $p->amount_paid, $p->status, $p->communication]);
                    }
                });
            }

            fclose($out);
        };

        return response()->stream($callback, 200, $headers);
    }
}

```

### File: app/Http/Controllers/Admin/AuditLogController.php
```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\PaginatesFromRequest;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\ThemeSetting;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    use PaginatesFromRequest;

    public function index(Request $request)
    {
        $query = AuditLog::with('user')->orderByDesc('created_at');

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }
        if ($request->filled('model_type')) {
            $query->where('model_type', 'like', '%'.$request->model_type.'%');
        }
        if ($request->filled('action')) {
            $query->where('action', $request->action);
        }
        if ($request->filled('from')) {
            $query->where('created_at', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query->where('created_at', '<=', $request->to.' 23:59:59');
        }

        $logs = $query->paginate($this->perPage(50))->withQueryString();
        $oldestLog = AuditLog::min('created_at');
        $retentionMonths = (int) ThemeSetting::get('audit_retention_months', 24);

        return view('admin.audit-logs.index', compact('logs', 'oldestLog', 'retentionMonths'));
    }

    public function show(AuditLog $auditLog)
    {
        $auditLog->load('user');

        return view('admin.audit-logs.show', ['log' => $auditLog]);
    }

    public function purge(Request $request)
    {
        $years = (int) $request->validate(['years' => 'required|integer|min:1|max:5'])['years'];
        $cutoff = now()->subYears($years);
        $deleted = AuditLog::where('created_at', '<', $cutoff)->delete();

        return back()->with('success', __(':count audit log entries older than :years year(s) deleted.', ['count' => $deleted, 'years' => $years]));
    }

    public function updateRetention(Request $request)
    {
        $months = $request->validate(['audit_retention_months' => 'required|integer|min:1|max:120'])['audit_retention_months'];
        ThemeSetting::set('audit_retention_months', $months);

        return back()->with('success', __('Retention policy updated to :months months.', ['months' => $months]));
    }

    public function export(Request $request)
    {
        $query = AuditLog::with('user')->orderByDesc('created_at');

        if ($request->filled('from')) {
            $query->where('created_at', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query->where('created_at', '<=', $request->to.' 23:59:59');
        }
        if ($request->filled('action')) {
            $query->where('action', $request->action);
        }

        $filename = 'audit_log_'.now()->format('Y-m-d_His').'.csv';
        $headers = ['Content-Type' => 'text/csv', 'Content-Disposition' => "attachment; filename=\"$filename\""];

        return response()->stream(function () use ($query) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Time', 'User', 'Action', 'Model', 'Model ID', 'IP', 'Old Values', 'New Values']);
            $query->chunk(500, function ($logs) use ($out) {
                foreach ($logs as $log) {
                    fputcsv($out, [
                        $log->created_at->toIso8601String(),
                        $log->user?->name ?? $log->user_id,
                        $log->action,
                        class_basename($log->model_type),
                        $log->model_id,
                        $log->ip_address,
                        $log->old_values ? json_encode($log->old_values) : '',
                        $log->new_values ? json_encode($log->new_values) : '',
                    ]);
                }
            });
            fclose($out);
        }, 200, $headers);
    }
}

```

### File: app/Http/Controllers/Admin/SeasonController.php
```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Season;
use App\Models\SeasonHoliday;
use App\Models\SeasonPattern;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SeasonController extends Controller
{
    public function index()
    {
        $seasons = Season::withCount('events')->orderByDesc('year')->get();

        return view('admin.seasons.index', compact('seasons'));
    }

    public function create()
    {
        $previousSeasons = Season::orderByDesc('year')->get();

        return view('admin.seasons.form', ['season' => new Season, 'previousSeasons' => $previousSeasons]);
    }

    public function store(Request $request)
    {
        $v = $request->validate([
            'year' => 'required|integer|min:2000',
            'name' => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
        ]);

        $season = Season::create($v);

        // Clone from previous season if requested
        if ($request->filled('clone_from')) {
            $source = Season::with(['holidays', 'patterns'])->find($request->clone_from);
            if ($source) {
                $yearDiff = $v['year'] - $source->year;
                foreach ($source->holidays as $h) {
                    SeasonHoliday::create([
                        'season_id' => $season->id,
                        'name' => $h->name,
                        'start_date' => $h->start_date->addYears($yearDiff),
                        'end_date' => $h->end_date->addYears($yearDiff),
                        'is_adhoc' => $h->is_adhoc,
                    ]);
                }
                foreach ($source->patterns as $p) {
                    SeasonPattern::create(array_merge($p->only(['day_of_week', 'start_time', 'end_time', 'event_type', 'title', 'location', 'max_participants', 'color_hex']), ['season_id' => $season->id]));
                }
            }
        }

        return redirect()->route('admin.seasons.show', $season)->with('success', __('Season created.'));
    }

    public function show(Season $season)
    {
        $season->load(['holidays', 'patterns']);

        return view('admin.seasons.show', compact('season'));
    }

    public function activate(Season $season)
    {
        DB::transaction(function () use ($season) {
            Season::where('is_active', true)->update(['is_active' => false]);
            $season->update(['is_active' => true]);
        });

        return back()->with('success', __('Season activated.'));
    }

    // Holiday management
    public function storeHoliday(Request $request, Season $season)
    {
        $v = $request->validate([
            'name' => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'is_adhoc' => 'boolean',
        ]);
        $v['is_adhoc'] = $request->boolean('is_adhoc');
        $holiday = $season->holidays()->create($v);

        if ($request->wantsJson()) {
            return response()->json([
                'id' => $holiday->id,
                'name' => $holiday->name,
                'start_date' => $holiday->start_date->format('d/m'),
                'end_date' => $holiday->end_date->format('d/m/Y'),
                'is_adhoc' => $holiday->is_adhoc,
                'delete_url' => route('admin.seasons.holiday.destroy', $holiday),
            ]);
        }

        return back()->with('success', __('Holiday added.'));
    }

    public function destroyHoliday(SeasonHoliday $holiday)
    {
        $holiday->delete();

        if (request()->wantsJson()) {
            return response()->json(['ok' => true]);
        }

        return back()->with('success', __('Holiday removed.'));
    }

    // Pattern management
    public function storePattern(Request $request, Season $season)
    {
        $v = $request->validate([
            'day_of_week' => 'required|integer|min:0|max:6',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i',
            'event_type' => 'required|in:pool,dive,training,theory,social',
            'title' => 'required|string|max:255',
            'location' => 'nullable|string|max:500',
            'max_participants' => 'nullable|integer|min:1',
            'registration_opens_days_before' => 'nullable|integer|min:1',
            'color_hex' => 'nullable|string|max:7',
        ]);
        $pattern = $season->patterns()->create($v);

        if ($request->wantsJson()) {
            return response()->json(array_merge($pattern->toArray(), [
                'delete_url' => route('admin.seasons.pattern.destroy', $pattern),
            ]));
        }

        return back()->with('success', __('Pattern added.'));
    }

    public function destroyPattern(SeasonPattern $pattern)
    {
        $pattern->delete();

        if (request()->wantsJson()) {
            return response()->json(['ok' => true]);
        }

        return back()->with('success', __('Pattern removed.'));
    }

    // Generate events preview
    public function previewGeneration(Season $season)
    {
        $season->load(['patterns', 'holidays']);
        $preview = $this->buildSchedule($season);

        return view('admin.seasons.preview', compact('season', 'preview'));
    }

    // Confirm generation
    public function generateEvents(Season $season)
    {
        $season->load(['patterns', 'holidays']);
        $schedule = $this->buildSchedule($season);
        $created = 0;

        DB::transaction(function () use ($schedule, $season, &$created) {
            foreach ($schedule as $entry) {
                if ($entry['skip']) {
                    continue;
                }
                $pattern = $entry['pattern'];
                Event::create([
                    'title' => $pattern->title,
                    'color_hex' => $pattern->color_hex,
                    'event_type' => $pattern->event_type,
                    'event_date' => $entry['date'],
                    'event_time' => $pattern->start_time,
                    'end_time' => $pattern->end_time,
                    'location' => $pattern->location,
                    'max_participants' => $pattern->max_participants,
                    'waiting_list_enabled' => true,
                    'inscription_open_at' => $pattern->registration_opens_days_before
                        ? $entry['date']->copy()->subDays($pattern->registration_opens_days_before)->startOfDay()
                        : null,
                    'status' => 'scheduled',
                    'season_id' => $season->id,
                    'created_by' => auth()->id(),
                    'whatsapp_group_url' => $pattern->whatsapp_group_url,
                ]);
                $created++;
            }
        });

        return redirect()->route('admin.seasons.show', $season)->with('success', __(':count events generated.', ['count' => $created]));
    }

    private function buildSchedule(Season $season): array
    {
        $schedule = [];
        $holidays = $season->holidays;

        foreach ($season->patterns as $pattern) {
            // Carbon day_of_week: 0=Sunday..6=Saturday, but we store 0=Monday..6=Sunday
            $carbonDay = ($pattern->day_of_week + 1) % 7;
            $current = $season->start_date->copy();

            // Find first matching day
            while ($current->dayOfWeek !== $carbonDay && $current->lte($season->end_date)) {
                $current->addDay();
            }

            while ($current->lte($season->end_date)) {
                $skip = false;
                $skipReason = null;

                foreach ($holidays as $h) {
                    if ($current->between($h->start_date, $h->end_date)) {
                        $skip = true;
                        $skipReason = $h->name.($h->is_adhoc ? ' (ad-hoc)' : '');
                        break;
                    }
                }

                $schedule[] = [
                    'date' => $current->copy(),
                    'pattern' => $pattern,
                    'skip' => $skip,
                    'skip_reason' => $skipReason,
                ];

                $current->addWeek();
            }
        }

        usort($schedule, fn ($a, $b) => $a['date']->timestamp - $b['date']->timestamp);

        return $schedule;
    }
}

```

### File: app/Http/Controllers/Admin/EmailController.php
```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\PaginatesFromRequest;
use App\Http\Controllers\Controller;
use App\Models\EmailLog;
use App\Models\EmailTemplate;
use App\Models\ThemeSetting;
use App\Models\User;
use App\Services\ArticleTranslationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class EmailController extends Controller
{
    use PaginatesFromRequest;

    public function index()
    {
        $templates = EmailTemplate::orderBy('name')->get();
        $log = EmailLog::orderByDesc('created_at')->paginate($this->perPage(30));

        return view('admin.email.index', compact('templates', 'log'));
    }

    public function storeTemplate(Request $request)
    {
        $v = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:100|unique:email_templates,slug',
            'subject' => 'required|string|max:255',
            'body' => 'required|string',
            'locale' => 'required|string|max:5',
        ]);
        EmailTemplate::create($v);

        return back()->with('success', __('Template created.'));
    }

    public function updateTemplate(Request $request, EmailTemplate $template)
    {
        $v = $request->validate([
            'name' => 'required|string|max:255',
            'subject' => 'required|string|max:255',
            'body' => 'required|string',
        ]);
        $template->update($v);

        return back()->with('success', __('Template updated.'));
    }

    public function destroyTemplate(EmailTemplate $template)
    {
        $template->delete();

        return back()->with('success', __('Template deleted.'));
    }

    public function preview(Request $request)
    {
        $template = EmailTemplate::findOrFail($request->template_id);
        $user = User::with('detail')->first();
        $rendered = $this->renderTemplate($template, $user);

        return response()->json($rendered);
    }

    public function send(Request $request)
    {
        $request->validate([
            'template_id' => 'required|exists:email_templates,id',
            'group' => 'required|in:all,active,instructors,bureau,expiring_certs,unpaid,event',
            'event_id' => 'nullable|required_if:group,event|exists:events,id',
        ]);

        $template = EmailTemplate::findOrFail($request->template_id);
        $users = $this->resolveGroup($request->group, $request->event_id);
        $sourceLocale = $template->locale ?? 'fr';

        // Pre-translate subject+body per unique target locale
        $translations = []; // locale => ['subject' => ..., 'body' => ...]
        $translator = app(ArticleTranslationService::class);
        $locales = $users->pluck('preferred_locale')->filter()->unique()->reject(fn ($l) => $l === $sourceLocale);

        foreach ($locales as $locale) {
            $translations[$locale] = [
                'subject' => $translator->translateText($template->subject, $sourceLocale, $locale) ?? $template->subject,
                'body' => $translator->translateText($template->body, $sourceLocale, $locale) ?? $template->body,
            ];
        }

        // Batch by locale for efficient sending
        $sent = 0;
        foreach ($users as $user) {
            $rendered = $this->renderTemplate($template, $user);
            $userLocale = $user->preferred_locale;

            // Append translated version if user has a different preferred language
            if ($userLocale && $userLocale !== $sourceLocale && isset($translations[$userLocale])) {
                $t = $translations[$userLocale];
                $tRendered = $this->renderVars(
                    ['subject' => $t['subject'], 'body' => $t['body']],
                    $user
                );
                $rendered['body'] .= "\n\n--- ".strtoupper($userLocale)." ---\n\n".$tRendered['body'];
            }

            EmailLog::create([
                'event_id' => $request->event_id,
                'user_id' => $user->id,
                'to_email' => $user->primary_email,
                'subject' => $rendered['subject'],
                'body' => $rendered['body'],
                'template_slug' => $template->slug,
                'status' => 'queued',
            ]);
            $sent++;
        }

        // Dispatch actual sending via queue
        dispatch(function () {
            $queued = EmailLog::where('status', 'queued')->get();
            foreach ($queued as $log) {
                if (config('app.staging_mode')) {
                    $log->update(['status' => 'staging_captured']);

                    continue;
                }
                try {
                    Mail::raw($log->body, fn ($m) => $m->to($log->to_email)->subject($log->subject));
                    $log->update(['status' => 'sent', 'attempts' => $log->attempts + 1]);
                } catch (\Exception $e) {
                    $log->update(['status' => $log->attempts >= 2 ? 'failed' : 'queued', 'attempts' => $log->attempts + 1, 'error' => $e->getMessage()]);
                }
            }
        })->afterResponse();

        return back()->with('success', __(':count emails queued.', ['count' => $sent]));
    }

    private function renderTemplate(EmailTemplate $template, User $user): array
    {
        return $this->renderVars(['subject' => $template->subject, 'body' => $template->body], $user);
    }

    private function renderVars(array $texts, User $user): array
    {
        $vars = [
            '{{first_name}}' => $user->detail?->first_name ?? '',
            '{{last_name}}' => $user->detail?->last_name ?? '',
            '{{name}}' => $user->name,
            '{{email}}' => $user->primary_email,
            '{{club_name}}' => ThemeSetting::get('club_full_name', 'Diving Club'),
        ];

        return [
            'subject' => str_replace(array_keys($vars), array_values($vars), $texts['subject']),
            'body' => str_replace(array_keys($vars), array_values($vars), $texts['body']),
        ];
    }

    private function resolveGroup(string $group, ?int $eventId = null)
    {
        return match ($group) {
            'all' => User::with('detail')->whereNotNull('email_verified_at')->get(),
            'active' => User::with('detail')->whereHas('status', fn ($q) => $q->where('slug', 'actif'))->get(),
            'instructors' => User::with('detail')->whereHas('role', fn ($q) => $q->where('slug', 'instructor'))->get(),
            'bureau' => User::with('detail')->whereHas('role', fn ($q) => $q->whereIn('slug', ['bureau_master', 'bureau_finance', 'bureau_technical']))->get(),
            'expiring_certs' => User::with('detail')->whereHas('documents', fn ($q) => $q->where('category', 'medical')->where('is_current', true)->whereBetween('expiry_date', [now(), now()->addDays(30)]))->get(),
            'unpaid' => User::with('detail')->whereHas('paymentsExpected', fn ($q) => $q->where('status', 'pending'))->get(),
            'event' => $eventId ? User::with('detail')->whereHas('eventRegistrations', fn ($q) => $q->where('event_id', $eventId)->where('status', 'confirmed'))->get() : collect(),
            default => collect(),
        };
    }
}

```

### File: app/Http/Controllers/Admin/MedicalExportController.php
```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\Federation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

class MedicalExportController extends Controller
{
    /**
     * Export member list as CSV for federation medical submission.
     */
    public function exportList(Request $request)
    {
        $federationId = $request->get('federation_id');

        $query = User::with(['detail', 'documents' => fn ($q) => $q->where('category', 'medical')->where('is_current', true)])
            ->whereHas('detail')
            ->whereHas('role', fn ($q) => $q->whereNotIn('slug', ['pending']))
            ->orderBy('id');

        if ($federationId) {
            $query->whereHas('licences', fn ($q) => $q->where('federation_id', $federationId));
        }

        $members = $query->get();
        $fedName = $federationId ? Federation::find($federationId)?->acronym : 'all';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename=medical-'.$fedName.'-'.date('Y-m-d').'.csv',
        ];

        $callback = function () use ($members) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, [
                'Date Demande', 'NOM', 'Prénom', 'Date de naissance', 'sexe',
                'n°, Rue', 'Pays', 'CP', 'Localité', 'Date Examen Médical',
            ], ';');

            foreach ($members as $member) {
                $d = $member->detail;
                $medCert = $member->documents->first();

                fputcsv($out, [
                    '',
                    strtoupper($d->last_name ?? ''),
                    $d->first_name ?? '',
                    $d->date_of_birth?->format('d/m/Y') ?? '',
                    $d->sex ?? '',
                    $d->address_line1 ?? '',
                    $d->country ?? '',
                    $d->postal_code ?? '',
                    $d->city ?? '',
                    $medCert?->date_established?->format('d/m/Y') ?? '',
                ], ';');
            }

            fclose($out);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Download current medical certificates as a ZIP.
     * Includes DB-tracked documents + legacy files from private/medical/.
     * Files named: LASTNAME Firstname member# type.ext
     */
    public function downloadCertificates(Request $request)
    {
        $federationId = $request->get('federation_id');

        // DB-tracked documents
        $query = Document::where('category', 'medical')
            ->where('is_current', true)
            ->with('user.detail');

        if ($federationId) {
            $query->whereHas('user.licences', fn ($q) => $q->where('federation_id', $federationId));
        }

        $docs = $query->get();

        // Legacy files from private/medical/ (LASTNAME_Firstname.pdf)
        $legacyFiles = [];
        $legacyDir = storage_path('app/private/medical');
        if (is_dir($legacyDir)) {
            // If filtering by federation, get matching user names to filter legacy files
            $matchNames = null;
            if ($federationId) {
                $matchNames = User::whereHas('licences', fn ($q) => $q->where('federation_id', $federationId))
                    ->with('detail')
                    ->get()
                    ->map(fn ($u) => strtoupper(Str::ascii($u->detail?->last_name ?? '')))
                    ->filter()
                    ->toArray();
            }

            foreach (glob("{$legacyDir}/*") as $file) {
                $basename = pathinfo($file, PATHINFO_FILENAME);
                // Match LASTNAME_Firstname or LASTNAME Firstname
                $lastName = strtoupper(explode('_', $basename)[0] ?? '');

                if ($matchNames !== null && ! in_array($lastName, $matchNames)) {
                    continue;
                }

                // Skip if we already have a DB document for this user
                $alreadyInDb = $docs->contains(fn ($doc) => strtoupper(Str::ascii($doc->user?->detail?->last_name ?? '')) === $lastName);
                if (! $alreadyInDb) {
                    $legacyFiles[] = $file;
                }
            }
        }

        if ($docs->isEmpty() && empty($legacyFiles)) {
            return back()->with('error', __('No medical certificates to export.'));
        }

        $fedName = $federationId ? Federation::find($federationId)?->acronym : 'all';
        $zipPath = storage_path('app/temp/medical-certs-'.$fedName.'-'.date('Y-m-d').'.zip');
        @mkdir(dirname($zipPath), 0755, true);

        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return back()->with('error', 'Could not create ZIP file.');
        }

        // Add DB-tracked documents
        foreach ($docs as $doc) {
            $disk = Storage::disk('local');
            if (! $disk->exists($doc->file_path)) {
                continue;
            }

            $d = $doc->user?->detail;
            $lastName = strtoupper(Str::ascii($d->last_name ?? 'UNKNOWN'));
            $firstName = Str::ascii($d->first_name ?? 'Unknown');
            $memberId = $doc->user_id;
            $type = $doc->cert_type ? strtoupper($doc->cert_type) : 'MED';
            $ext = pathinfo($doc->original_filename, PATHINFO_EXTENSION) ?: 'pdf';

            $filename = "{$lastName} {$firstName} {$memberId} {$type}.{$ext}";
            $zip->addFromString($filename, $disk->get($doc->file_path));
        }

        // Add legacy files
        foreach ($legacyFiles as $file) {
            $zip->addFile($file, basename($file));
        }

        $zip->close();

        if (! file_exists($zipPath) || filesize($zipPath) === 0) {
            @unlink($zipPath);

            return back()->with('error', __('No certificate files found on disk to export.'));
        }

        return response()->download($zipPath, basename($zipPath))->deleteFileAfterSend();
    }
}

```

### File: app/Http/Controllers/Admin/AnnualReportController.php
```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\MemberStatus;
use App\Models\PaymentExpected;
use App\Models\User;
use Illuminate\Http\Request;

class AnnualReportController extends Controller
{
    public function show(Request $request)
    {
        $year = (int) $request->get('year', date('Y'));
        $years = range(date('Y'), (int) (User::min('created_at') ? substr(User::min('created_at'), 0, 4) : date('Y') - 3), -1);

        // Members over time (last 5 years)
        $membersTrend = collect(range($year - 4, $year))->map(fn ($y) => [
            'year' => $y,
            'count' => User::where('created_at', '<=', "$y-12-31")->count(),
        ]);

        // Events by type this year
        $eventsByType = Event::whereYear('event_date', $year)
            ->selectRaw('event_type, count(*) as cnt')
            ->groupBy('event_type')->pluck('cnt', 'event_type');

        // Monthly participation (confirmed registrations per month)
        $monthlyParticipation = collect(range(1, 12))->map(function ($m) use ($year) {
            return [
                'month' => $m,
                'label' => date('M', mktime(0, 0, 0, $m)),
                'count' => EventRegistration::where('status', 'confirmed')
                    ->whereHas('event', fn ($q) => $q->whereYear('event_date', $year)->whereMonth('event_date', $m))
                    ->count(),
            ];
        });

        // Social vs diving events participation
        $socialVsDiving = [
            'diving' => Event::whereYear('event_date', $year)->whereIn('event_type', ['pool', 'dive', 'training'])->withCount('confirmedRegistrations')->get()->sum('confirmed_registrations_count'),
            'social' => Event::whereYear('event_date', $year)->where('event_type', 'social')->withCount('confirmedRegistrations')->get()->sum('confirmed_registrations_count'),
            'theory' => Event::whereYear('event_date', $year)->where('event_type', 'theory')->withCount('confirmedRegistrations')->get()->sum('confirmed_registrations_count'),
        ];

        // Financial summary
        $finance = [
            'revenue' => PaymentExpected::where('season_year', $year)->where('status', 'paid')->sum('amount_paid'),
            'outstanding' => PaymentExpected::where('season_year', $year)->where('status', 'pending')->sum('amount_due'),
            'total_due' => PaymentExpected::where('season_year', $year)->sum('amount_due'),
            'paid_count' => PaymentExpected::where('season_year', $year)->where('status', 'paid')->count(),
            'pending_count' => PaymentExpected::where('season_year', $year)->where('status', 'pending')->count(),
        ];

        // Members by status
        $membersByStatus = MemberStatus::withCount('users')->get();

        // New members this year
        $newMembers = User::whereYear('created_at', $year)->count();

        // Total events
        $totalEvents = Event::whereYear('event_date', $year)->where('status', '!=', 'cancelled')->count();

        // Before/after comparisons
        $startOfYear = "$year-01-01";
        $endOfYear = "$year-12-31";
        $beforeAfter = [
            'members_start' => User::where('created_at', '<', $startOfYear)->count(),
            'members_end' => User::where('created_at', '<=', $endOfYear)->count(),
            'departed' => 0, // TODO: track departures when member status tracking is added
            'revenue_start' => PaymentExpected::where('season_year', $year - 1)->where('status', 'paid')->sum('amount_paid'),
            'revenue_end' => $finance['revenue'],
            'main_events' => Event::whereYear('event_date', $year)
                ->where('status', '!=', 'cancelled')
                ->whereIn('event_type', ['dive', 'trip', 'social'])
                ->withCount('confirmedRegistrations')
                ->orderByDesc('confirmed_registrations_count')
                ->limit(10)->get(),
        ];

        return view('admin.annual-report', compact(
            'year', 'years', 'membersTrend', 'eventsByType', 'monthlyParticipation',
            'socialVsDiving', 'finance', 'membersByStatus', 'newMembers', 'totalEvents', 'beforeAfter'
        ));
    }
}

```

### File: app/Http/Controllers/Admin/EquipmentController.php
```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\PaginatesFromRequest;
use App\Http\Controllers\Controller;
use App\Models\Equipment;
use App\Models\EquipmentLoan;
use App\Models\EquipmentMaintenance;
use App\Models\EquipmentMaintenanceRule;
use App\Models\User;
use Illuminate\Http\Request;

class EquipmentController extends Controller
{
    use PaginatesFromRequest;

    public function index(Request $request)
    {
        $sortable = ['name' => 'name', 'type' => 'type', 'status' => 'status'];
        $sort = $sortable[$request->get('sort')] ?? 'name';
        $dir = $request->get('dir', 'asc') === 'desc' ? 'desc' : 'asc';

        $equipment = Equipment::with(['currentLoan.user.detail'])
            ->when($request->type, fn ($q, $t) => $q->where('type', $t))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->orderBy($sort, $dir)->paginate($this->perPage(30))->withQueryString();

        return view('admin.equipment.index', compact('equipment'));
    }

    public function create()
    {
        return view('admin.equipment.form', ['item' => new Equipment]);
    }

    public function store(Request $request)
    {
        $v = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:bcd,regulator,tank,wetsuit,mask,fins,computer,other',
            'serial_number' => 'nullable|string|max:100',
            'purchase_date' => 'nullable|date',
            'condition' => 'required|in:new,good,fair,poor',
            'notes' => 'nullable|string',
        ]);

        $item = Equipment::create($v);

        // Auto-generate maintenance tasks from rules
        $rules = EquipmentMaintenanceRule::where('equipment_type', $item->type)->get();
        foreach ($rules as $rule) {
            EquipmentMaintenance::create([
                'equipment_id' => $item->id,
                'maintenance_name' => $rule->maintenance_name,
                'due_date' => now()->addMonths($rule->interval_months),
                'is_mandatory' => $rule->is_mandatory,
            ]);
        }

        return redirect()->route('admin.equipment.index')->with('success', __('Equipment added with :count maintenance tasks.', ['count' => $rules->count()]));
    }

    public function show(Equipment $equipment)
    {
        $equipment->load(['maintenanceTasks' => fn ($q) => $q->orderBy('due_date'), 'loans' => fn ($q) => $q->with('user.detail')->orderByDesc('loaned_at')]);
        $members = User::with('detail')->whereHas('detail')->get()->sortBy(fn ($u) => $u->detail?->last_name);

        return view('admin.equipment.show', compact('equipment', 'members'));
    }

    public function update(Request $request, Equipment $equipment)
    {
        $v = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|string',
            'serial_number' => 'nullable|string|max:100',
            'condition' => 'required|in:new,good,fair,poor',
            'status' => 'required|in:available,on_loan,maintenance_required,retired',
            'notes' => 'nullable|string',
        ]);
        $equipment->update($v);

        return back()->with('success', __('Equipment updated.'));
    }

    public function loan(Request $request, Equipment $equipment)
    {
        if (! $equipment->isAvailable()) {
            return back()->with('error', __('Equipment is not available for loan.'));
        }

        $request->validate(['user_id' => 'required|exists:users,id', 'expected_return_date' => 'nullable|date|after:today']);

        EquipmentLoan::create([
            'equipment_id' => $equipment->id,
            'user_id' => $request->user_id,
            'loaned_at' => now(),
            'expected_return_date' => $request->expected_return_date,
            'loaned_by' => auth()->id(),
        ]);
        $equipment->update(['status' => 'on_loan']);

        return back()->with('success', __('Equipment loaned.'));
    }

    public function returnLoan(EquipmentLoan $loan)
    {
        $loan->update(['returned_at' => now(), 'returned_by' => auth()->id()]);

        $status = $loan->equipment->hasOverdueMaintenance() ? 'maintenance_required' : 'available';
        $loan->equipment->update(['status' => $status]);

        return back()->with('success', __('Equipment returned.'));
    }

    public function completeMaintenance(EquipmentMaintenance $maintenance)
    {
        $maintenance->update(['completed_at' => now(), 'completed_by' => auth()->id()]);

        // Schedule next maintenance
        $rule = EquipmentMaintenanceRule::where('equipment_type', $maintenance->equipment->type)
            ->where('maintenance_name', $maintenance->maintenance_name)->first();

        if ($rule) {
            EquipmentMaintenance::create([
                'equipment_id' => $maintenance->equipment_id,
                'maintenance_name' => $maintenance->maintenance_name,
                'due_date' => now()->addMonths($rule->interval_months),
                'is_mandatory' => $rule->is_mandatory,
            ]);
        }

        // Update equipment status if no more overdue
        if (! $maintenance->equipment->hasOverdueMaintenance() && $maintenance->equipment->status === 'maintenance_required') {
            $maintenance->equipment->update(['status' => $maintenance->equipment->currentLoan ? 'on_loan' : 'available']);
        }

        return back()->with('success', __('Maintenance completed. Next scheduled.'));
    }
}

```

### File: app/Http/Controllers/Admin/PartnershipController.php
```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClubPartnership;
use App\Models\ExternalRegistration;
use App\Models\ThemeSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

class PartnershipController extends Controller
{
    public function index()
    {
        $partners = ClubPartnership::withCount('externalRegistrations')->orderBy('name')->get();

        return view('admin.partnerships.index', compact('partners'));
    }

    public function create()
    {
        $keys = ClubPartnership::generateKeyPair();

        return view('admin.partnerships.create', compact('keys'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'base_url' => 'required|url',
            'api_key_id' => 'required|string',
            'api_secret' => 'required|string',
            'their_api_key_id' => 'nullable|string',
            'their_api_secret' => 'nullable|string',
        ]);

        ClubPartnership::create([
            'name' => $data['name'],
            'base_url' => rtrim($data['base_url'], '/'),
            'api_key_id' => $data['api_key_id'],
            'api_secret_hash' => Hash::make($data['api_secret']),
            'their_api_key_id' => $data['their_api_key_id'],
            'their_api_secret' => $data['their_api_secret'] ? Crypt::encryptString($data['their_api_secret']) : null,
        ]);

        return redirect()->route('admin.partnerships.index')->with('success', 'Partnership created. Share the Key ID and Secret with the partner club.');
    }

    public function destroy(ClubPartnership $partnership)
    {
        $partnership->delete();

        return back()->with('success', 'Partnership removed.');
    }

    /**
     * Fetch federated events from a partner club and display them.
     */
    public function remoteEvents(ClubPartnership $partnership)
    {
        if (! $partnership->their_api_key_id || ! $partnership->their_api_secret) {
            return back()->with('error', 'Outbound API credentials not configured for this partner.');
        }

        try {
            $response = Http::withHeaders([
                'X-Club-Key-Id' => $partnership->their_api_key_id,
                'X-Club-Secret' => Crypt::decryptString($partnership->their_api_secret),
            ])->timeout(10)->get($partnership->base_url.'/api/federation/events');

            $events = $response->successful() ? $response->json('events', []) : [];
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to connect: '.$e->getMessage());
        }

        return view('admin.partnerships.remote-events', compact('partnership', 'events'));
    }

    /**
     * Manage external registrations for our events.
     */
    public function registrations(Request $request)
    {
        $regs = ExternalRegistration::with(['event', 'partnership'])
            ->orderByDesc('created_at')
            ->paginate(25);

        return view('admin.partnerships.registrations', compact('regs'));
    }

    public function approveRegistration(ExternalRegistration $registration)
    {
        $registration->update(['status' => 'approved']);
        $this->notifyExternalMember($registration, 'approved');

        return back()->with('success', $registration->external_member_name.' approved.');
    }

    public function rejectRegistration(ExternalRegistration $registration)
    {
        $registration->update(['status' => 'rejected']);
        $this->notifyExternalMember($registration, 'rejected');

        return back()->with('success', $registration->external_member_name.' rejected.');
    }

    private function notifyExternalMember(ExternalRegistration $reg, string $status): void
    {
        if (! $reg->external_member_email) {
            return;
        }

        $clubName = ThemeSetting::get('club_full_name', config('app.name'));
        $event = $reg->event;

        $body = $status === 'approved'
            ? __("Dear :name,\n\nYour registration for \":event\" on :date has been approved by :club.\n\nLocation: :location\n\nWe look forward to seeing you!\n:club", [
                'name' => $reg->external_member_name,
                'event' => $event->title,
                'date' => $event->event_date->format('d/m/Y'),
                'location' => $event->location ?? '—',
                'club' => $clubName,
            ])
            : __("Dear :name,\n\nUnfortunately, your registration for \":event\" on :date could not be accepted by :club.\n\nPlease contact us if you have questions.\n:club", [
                'name' => $reg->external_member_name,
                'event' => $event->title,
                'date' => $event->event_date->format('d/m/Y'),
                'club' => $clubName,
            ]);

        $subject = $status === 'approved'
            ? __('Registration Approved — :event', ['event' => $event->title])
            : __('Registration Update — :event', ['event' => $event->title]);

        Mail::raw($body, fn ($m) => $m->to($reg->external_member_email)->subject($subject));
    }
}

```

### File: app/Http/Controllers/Admin/ArticleController.php
```php
<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\HtmlSanitizer;
use App\Http\Controllers\Concerns\PaginatesFromRequest;
use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\ArticleImage;
use App\Models\Vote;
use App\Services\ArticleTranslationService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ArticleController extends Controller
{
    use PaginatesFromRequest;

    public function index(Request $request)
    {
        $articles = Article::when($request->type, fn ($q, $t) => $q->where('article_type', $t))
            ->orderByDesc('updated_at')->paginate($this->perPage(20));

        return view('admin.articles.index', compact('articles'));
    }

    public function create(Request $request)
    {
        $votes = Vote::where('status', 'open')->orWhere('status', 'draft')->orderByDesc('created_at')->get();

        return view('admin.articles.form', ['article' => new Article(['article_type' => $request->get('type', 'news')]), 'votes' => $votes]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'body' => 'required|string',
            'article_type' => 'required|in:'.implode(',', array_keys(Article::TYPES)),
            'is_published' => 'boolean',
            'is_public' => 'boolean',
            'featured_image' => 'nullable|image|max:5120',
            'vote_id' => 'nullable|exists:votes,id',
            'gallery.*' => 'image|max:5120',
            'gallery_captions.*' => 'nullable|string|max:255',
            'gallery_layouts.*' => 'nullable|in:full,half,third',
        ]);

        $validated['slug'] = Str::slug($validated['title']).'-'.Str::random(5);
        $validated['author_id'] = auth()->id();
        $validated['is_published'] = $request->boolean('is_published');
        $validated['is_public'] = $request->boolean('is_public');
        $validated['body'] = HtmlSanitizer::clean($validated['body']);

        if ($request->hasFile('featured_image')) {
            $validated['featured_image'] = $request->file('featured_image')->store('articles', 'public');
        }

        $article = Article::create(collect($validated)->except(['gallery', 'gallery_captions', 'gallery_layouts'])->toArray());
        $this->storeGallery($request, $article);

        return redirect()->route('admin.articles.index')->with('success', __('Article created.'));
    }

    public function edit(Article $article)
    {
        $votes = Vote::where('status', 'open')->orWhere('status', 'draft')->orderByDesc('created_at')->get();

        return view('admin.articles.form', compact('article', 'votes'));
    }

    public function update(Request $request, Article $article)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'body' => 'required|string',
            'article_type' => 'required|in:'.implode(',', array_keys(Article::TYPES)),
            'is_published' => 'boolean',
            'is_public' => 'boolean',
            'featured_image' => 'nullable|image|max:5120',
            'vote_id' => 'nullable|exists:votes,id',
            'gallery.*' => 'image|max:5120',
            'gallery_captions.*' => 'nullable|string|max:255',
            'gallery_layouts.*' => 'nullable|in:full,half,third',
            'delete_images' => 'nullable|array',
            'delete_images.*' => 'exists:article_images,id',
        ]);

        $validated['slug'] = Str::slug($validated['title']).'-'.Str::random(5);
        $validated['is_published'] = $request->boolean('is_published');
        $validated['is_public'] = $request->boolean('is_public');
        $validated['body'] = HtmlSanitizer::clean($validated['body']);

        if ($request->hasFile('featured_image')) {
            $validated['featured_image'] = $request->file('featured_image')->store('articles', 'public');
        }

        // Delete selected gallery images
        if ($request->delete_images) {
            ArticleImage::whereIn('id', $request->delete_images)->where('article_id', $article->id)->delete();
        }

        $article->update(collect($validated)->except(['gallery', 'gallery_captions', 'gallery_layouts', 'delete_images'])->toArray());
        $this->storeGallery($request, $article);

        // Mark existing translations as stale (will be re-translated lazily on next access)
        $article->translations()->where('auto_translated', true)->update(['stale' => true]);

        return redirect()->route('admin.articles.edit', $article)->with('success', __('Article updated.'));
    }

    public function destroy(Article $article)
    {
        $article->delete();

        return redirect()->route('admin.articles.index')->with('success', __('Article deleted.'));
    }

    private function storeGallery(Request $request, Article $article): void
    {
        if (! $request->hasFile('gallery')) {
            return;
        }
        $maxSort = $article->images()->max('sort_order') ?? 0;
        foreach ($request->file('gallery') as $i => $file) {
            ArticleImage::create([
                'article_id' => $article->id,
                'file_path' => $file->store('articles/gallery', 'public'),
                'alt_text' => $request->input("gallery_captions.$i"),
                'caption' => $request->input("gallery_captions.$i"),
                'layout_hint' => $request->input("gallery_layouts.$i", 'full'),
                'sort_order' => ++$maxSort,
            ]);
        }
    }

    public function translate(Request $request, Article $article)
    {
        $locales = config('app.available_locales', ['en', 'fr', 'de', 'lb', 'pt', 'it', 'es', 'nl', 'ro', 'hu', 'sk']);
        $source = $request->input('source_locale', 'fr');
        (new ArticleTranslationService)->translateAll($article, $locales, $source);

        return back()->with('success', __('Translations generated for :count languages.', ['count' => count($locales) - 1]));
    }
}

```

### File: app/Http/Controllers/Admin/GuardianController.php
```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GuardianLink;
use App\Models\ParentalConsent;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class GuardianController extends Controller
{
    public function index()
    {
        $minors = User::whereHas('detail', fn ($q) => $q->whereNotNull('date_of_birth')
            ->where('date_of_birth', '>', now()->subYears(18)))
            ->with(['detail', 'guardians', 'parentalConsents.grantedBy'])
            ->get();

        return view('admin.guardians.index', compact('minors'));
    }

    public function linkGuardian(Request $request)
    {
        $v = $request->validate([
            'minor_user_id' => 'required|exists:users,id',
            'guardian_user_id' => 'required|exists:users,id|different:minor_user_id',
            'relationship' => 'required|in:parent,legal_guardian',
        ]);

        GuardianLink::firstOrCreate(
            ['guardian_user_id' => $v['guardian_user_id'], 'minor_user_id' => $v['minor_user_id']],
            ['relationship' => $v['relationship']]
        );

        return back()->with('success', __('Guardian linked.'));
    }

    public function unlinkGuardian(GuardianLink $link)
    {
        $link->delete();

        return back()->with('success', __('Guardian unlinked.'));
    }

    public function storeConsent(Request $request)
    {
        $v = $request->validate([
            'minor_user_id' => 'required|exists:users,id',
            'consent_type' => 'required|in:general,events,photos,medical',
            'document' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        $path = $request->hasFile('document')
            ? $request->file('document')->store('parental-consents', 'local')
            : null;

        ParentalConsent::updateOrCreate(
            ['minor_user_id' => $v['minor_user_id'], 'consent_type' => $v['consent_type']],
            [
                'granted_by' => auth()->id(),
                'granted' => true,
                'granted_at' => now(),
                'revoked_at' => null,
                'document_path' => $path,
            ]
        );

        return back()->with('success', __('Parental consent recorded.'));
    }

    public function revokeConsent(ParentalConsent $consent)
    {
        $consent->update(['granted' => false, 'revoked_at' => now()]);

        return back()->with('success', __('Consent revoked.'));
    }

    public function downloadConsent(ParentalConsent $consent)
    {
        abort_unless($consent->document_path && Storage::disk('local')->exists($consent->document_path), 404);

        return Storage::disk('local')->download($consent->document_path);
    }
}

```

### File: app/Http/Controllers/Admin/PaymentController.php
```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\PaginatesFromRequest;
use App\Http\Controllers\Controller;
use App\Models\BankTransaction;
use App\Models\MembershipFeeComponent;
use App\Models\PaymentExpected;
use App\Models\User;
use App\Services\BankReconciliationService;
use App\Services\FeeCalculationService;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    use PaginatesFromRequest;

    public function index(Request $request)
    {
        $payments = PaymentExpected::with('user.detail')
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->orderByDesc('created_at')->paginate($this->perPage(30))->withQueryString();
        $components = MembershipFeeComponent::orderBy('sort_order')->get();

        return view('admin.payments.index', compact('payments', 'components'));
    }

    public function components()
    {
        $components = MembershipFeeComponent::orderBy('sort_order')->get();

        return view('admin.payments.components', compact('components'));
    }

    public function storeComponent(Request $request)
    {
        $v = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:100',
            'amount' => 'required|numeric|min:0',
            'is_base' => 'boolean',
            'is_optional' => 'boolean',
            'description' => 'nullable|string',
        ]);
        $v['is_base'] = $request->boolean('is_base');
        $v['is_optional'] = $request->boolean('is_optional');
        MembershipFeeComponent::create($v);

        return back()->with('success', __('Component added.'));
    }

    public function destroyComponent(MembershipFeeComponent $component)
    {
        $component->delete();

        return back()->with('success', __('Component removed.'));
    }

    public function calculateFee(Request $request, User $user)
    {
        $calc = app(FeeCalculationService::class)->calculate($user, $request->get('season', date('Y')), $request->get('optionals', []));

        return back()->with('success', __('Fee: €:amount — :comm', ['amount' => number_format($calc['amount_due'], 2), 'comm' => $calc['communication']]));
    }

    public function generateFee(Request $request, User $user)
    {
        $pe = app(FeeCalculationService::class)->createPaymentExpected($user, $request->get('season', date('Y')), $request->get('optionals', []));

        return back()->with('success', __('Payment expected created: €:amount', ['amount' => number_format($pe->amount_due, 2)]));
    }

    public function generateBulkFees(Request $request)
    {
        $season = $request->get('season', date('Y'));
        $svc = app(FeeCalculationService::class);

        $users = User::whereHas('status', fn ($q) => $q->where('slug', 'actif'))
            ->whereDoesntHave('paymentsExpected', fn ($q) => $q->where('type', 'membership')->where('season_year', $season))
            ->get();

        $count = 0;
        foreach ($users as $user) {
            $svc->createPaymentExpected($user, $season);
            $count++;
        }

        return back()->with('success', __(':count membership fees generated for season :season.', ['count' => $count, 'season' => $season]));
    }

    public function adjustComponents(Request $request, PaymentExpected $payment)
    {
        $request->validate([
            'components' => 'required|array',
            'components.*.label' => 'required|string',
            'components.*.amount' => 'required|numeric|min:0',
        ]);

        $components = $request->components;
        $total = collect($components)->sum('amount');

        $payment->update([
            'components' => $components,
            'amount_due' => round($total, 2),
        ]);

        return back()->with('success', __('Components adjusted. New total: €:amount', ['amount' => number_format($total, 2)]));
    }

    // Bank reconciliation
    public function reconciliation()
    {
        $transactions = BankTransaction::with('matchedPayment.user.detail')->orderByDesc('transaction_date')->paginate(50);
        $summary = [
            'unmatched' => BankTransaction::where('status', 'unmatched')->count(),
            'matched' => BankTransaction::where('status', 'matched')->count(),
            'confirmed' => BankTransaction::where('status', 'confirmed')->count(),
        ];

        return view('admin.payments.reconciliation', compact('transactions', 'summary'));
    }

    public function importStatement(Request $request)
    {
        $request->validate([
            'statement' => 'required_without:statement_pdf|nullable|string',
            'statement_pdf' => 'required_without:statement|nullable|file|mimes:pdf|max:10240',
            'statement_ref' => 'nullable|string|max:100',
        ]);

        $svc = app(BankReconciliationService::class);

        if ($request->hasFile('statement_pdf')) {
            $path = $request->file('statement_pdf')->store('bank-statements', 'local');
            $result = $svc->parsePdfStatement(storage_path('app/'.$path), $request->statement_ref);

            return back()->with('success', __(':count transactions imported from PDF (:pages pages).', [
                'count' => count($result['transactions']),
                'pages' => $result['page_count'],
            ]));
        }

        $txs = $svc->parseStatement($request->statement);

        return back()->with('success', __(':count transactions imported.', ['count' => count($txs)]));
    }

    public function suggestMatches()
    {
        $matches = app(BankReconciliationService::class)->suggestMatches();

        return back()->with('success', __(':count matches suggested — please review and confirm.', ['count' => count($matches)]));
    }

    public function confirmMatch(BankTransaction $transaction)
    {
        app(BankReconciliationService::class)->confirmMatch($transaction);

        return back()->with('success', __('Match confirmed.'));
    }

    public function ignoreTransaction(BankTransaction $transaction)
    {
        $transaction->update(['status' => 'ignored']);

        return back()->with('success', __('Transaction ignored.'));
    }
}

```

### File: app/Http/Controllers/Admin/BackupController.php
```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\BackupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BackupController extends Controller
{
    public function __construct(protected BackupService $backup) {}

    public function index(): View
    {
        return view('admin.backups.index', [
            'backups' => $this->backup->list(),
        ]);
    }

    public function create(Request $request): RedirectResponse
    {
        $includeFiles = $request->boolean('include_files', true);

        try {
            $result = $this->backup->create($includeFiles);
            $this->backup->prune((int) config('backup.retention', 4));

            return back()->with('success', __('Backup created: :file (:size)', [
                'file' => $result['filename'],
                'size' => $this->backup->list()[0]['size_human'] ?? '',
            ]));
        } catch (\Throwable $e) {
            return back()->with('error', __('Backup failed: :msg', ['msg' => $e->getMessage()]));
        }
    }

    public function show(string $filename): View
    {
        $filename = basename($filename);
        $path = storage_path("app/backups/{$filename}");
        abort_unless(file_exists($path), 404);

        return view('admin.backups.show', [
            'filename' => $filename,
            'manifest' => $this->backup->readManifest($path),
            'files' => $this->backup->listFiles($path),
            'size' => filesize($path),
            'size_human' => $this->humanSize(filesize($path)),
        ]);
    }

    public function download(string $filename): BinaryFileResponse
    {
        $filename = basename($filename);
        $path = storage_path("app/backups/{$filename}");
        abort_unless(file_exists($path), 404);

        return response()->download($path);
    }

    public function destroy(string $filename): RedirectResponse
    {
        $this->backup->delete(basename($filename));

        return back()->with('success', __('Backup deleted.'));
    }

    protected function humanSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < 3) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 1).' '.$units[$i];
    }
}

```

### File: app/Http/Controllers/Admin/VoteController.php
```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Vote;
use App\Models\VoteOption;
use App\Models\VoteToken;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class VoteController extends Controller
{
    public function index()
    {
        $votes = Vote::withCount(['tokens', 'ballots'])->orderByDesc('created_at')->get();

        return view('admin.votes.index', compact('votes'));
    }

    public function create()
    {
        return view('admin.votes.form', ['vote' => new Vote]);
    }

    public function store(Request $request)
    {
        $v = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'mode' => 'required|in:simple,election',
            'opens_at' => 'nullable|date',
            'closes_at' => 'nullable|date|after_or_equal:opens_at',
            'num_positions' => 'nullable|integer|min:1|max:20',
            'min_vote_pct' => 'nullable|integer|min:0|max:100',
            'options' => 'required|array|min:2',
            'options.*' => 'required|string|max:255',
        ]);

        $vote = Vote::create([
            'title' => $v['title'],
            'description' => $v['description'],
            'mode' => $v['mode'],
            'allow_multiple' => $request->boolean('allow_multiple'),
            'allow_change' => $request->boolean('allow_change'),
            'is_public' => $request->boolean('is_public'),
            'num_positions' => $v['num_positions'] ?? 1,
            'min_vote_pct' => $v['min_vote_pct'] ?? 50,
            'opens_at' => $v['opens_at'],
            'closes_at' => $v['closes_at'],
            'created_by' => auth()->id(),
        ]);

        foreach ($v['options'] as $i => $label) {
            VoteOption::create(['vote_id' => $vote->id, 'label' => $label, 'sort_order' => $i]);
        }

        return redirect()->route('admin.votes.show', $vote)->with('success', __('Vote created.'));
    }

    public function show(Vote $vote)
    {
        $vote->load(['options.ballots', 'tokens']);
        $results = $vote->options->map(fn ($o) => ['label' => $o->label, 'count' => $o->ballots->count()]);

        return view('admin.votes.show', compact('vote', 'results'));
    }

    public function generateTokens(Vote $vote)
    {
        $users = User::whereNotNull('email_verified_at')->get();
        $created = 0;

        foreach ($users as $user) {
            if (! $vote->tokens()->where('user_id', $user->id)->exists()) {
                VoteToken::create([
                    'vote_id' => $vote->id,
                    'user_id' => $user->id,
                    'token' => Str::random(128),
                ]);
                $created++;
            }
        }

        return back()->with('success', __(':count tokens generated.', ['count' => $created]));
    }

    public function open(Vote $vote)
    {
        $vote->update(['status' => 'open']);

        return back()->with('success', __('Vote opened.'));
    }

    public function close(Vote $vote)
    {
        $vote->update(['status' => 'closed']);

        return back()->with('success', __('Vote closed.'));
    }

    public function cancel(Vote $vote)
    {
        $vote->update(['status' => 'cancelled']);

        return back()->with('success', __('Vote cancelled.'));
    }
}

```

### File: app/Http/Controllers/Admin/ThumbnailController.php
```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LibraryFile;
use Illuminate\Support\Facades\Storage;

class ThumbnailController extends Controller
{
    public function show(LibraryFile $file)
    {
        $thumbPath = 'thumbnails/' . $file->id . '.jpg';

        if (!Storage::disk('local')->exists($thumbPath)) {
            $generated = $this->generate($file, $thumbPath);
            if (!$generated) {
                abort(404);
            }
        }

        return response(Storage::disk('local')->get($thumbPath), 200, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    private function generate(LibraryFile $file, string $thumbPath): bool
    {
        $source = Storage::disk('local')->path($file->path);
        $dest = Storage::disk('local')->path($thumbPath);
        Storage::disk('local')->makeDirectory('thumbnails');

        if (str_starts_with($file->mime_type, 'image/')) {
            return $this->imageThumb($source, $dest);
        }

        if ($file->mime_type === 'application/pdf') {
            return $this->pdfThumb($source, $dest);
        }

        return false;
    }

    private function imageThumb(string $source, string $dest): bool
    {
        $img = match (true) {
            str_ends_with($source, '.png') => @imagecreatefrompng($source),
            str_ends_with($source, '.gif') => @imagecreatefromgif($source),
            default => @imagecreatefromjpeg($source),
        };
        if (!$img) return false;

        $w = imagesx($img);
        $h = imagesy($img);
        $size = 200;
        $ratio = min($size / $w, $size / $h);
        $nw = (int) ($w * $ratio);
        $nh = (int) ($h * $ratio);

        $thumb = imagecreatetruecolor($nw, $nh);
        imagecopyresampled($thumb, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
        imagejpeg($thumb, $dest, 75);
        imagedestroy($img);
        imagedestroy($thumb);

        return true;
    }

    private function pdfThumb(string $source, string $dest): bool
    {
        // pdftoppm is faster and more reliable than Ghostscript for thumbnails
        $tmp = $dest . '_tmp';
        $cmd = sprintf(
            'pdftoppm -jpeg -f 1 -l 1 -scale-to 200 %s %s 2>/dev/null',
            escapeshellarg($source),
            escapeshellarg($tmp)
        );
        exec($cmd);

        // pdftoppm appends -1.jpg to the output
        $generated = $tmp . '-1.jpg';
        if (file_exists($generated)) {
            rename($generated, $dest);
            return true;
        }

        return false;
    }
}

```

### File: app/Http/Controllers/Admin/GuideController.php
```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

class GuideController extends Controller
{
    private array $sections = [
        'overview' => 'System Overview',
        'first-steps' => 'First Steps After Deployment',
        'members' => 'Managing Members',
        'minors' => 'Minors & Parental Consent',
        'seasons-events' => 'Seasons & Events',
        'dive-groups' => 'Dive Group Planner',
        'medical' => 'Medical Compliance',
        'payments' => 'Payments & Fees',
        'equipment' => 'Equipment Inventory',
        'content' => 'CMS, Classifieds & Documents',
        'email' => 'Email System',
        'voting' => 'Voting System',
        'partnerships' => 'Inter-Club Partnerships',
        'social-media' => 'Social Media Auto-Publish',
        'gdpr' => 'GDPR & Privacy',
        'audit-log' => 'Audit Log',
        'settings' => 'Settings & Configuration',
        'api-keys' => 'API Keys & OAuth Setup',
        'backup' => 'Backups & Maintenance',
        'troubleshooting' => 'Troubleshooting',
    ];

    public function index()
    {
        return view('admin.guide.index', ['sections' => $this->sections]);
    }

    public function show(string $section)
    {
        abort_unless(array_key_exists($section, $this->sections), 404);
        $keys = array_keys($this->sections);
        $idx = array_search($section, $keys);
        $prev = $idx > 0 ? ['slug' => $keys[$idx - 1], 'title' => $this->sections[$keys[$idx - 1]]] : null;
        $next = $idx < count($keys) - 1 ? ['slug' => $keys[$idx + 1], 'title' => $this->sections[$keys[$idx + 1]]] : null;

        return view("admin.guide.{$section}", [
            'sections' => $this->sections,
            'current' => $section,
            'title' => $this->sections[$section],
            'prev' => $prev,
            'next' => $next,
        ]);
    }
}

```

### File: app/Http/Controllers/Admin/MemberController.php
```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\PaginatesFromRequest;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\MemberStatus;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;

class MemberController extends Controller
{
    use PaginatesFromRequest;

    public function index(Request $request)
    {
        $query = User::with(['detail', 'role', 'status']);

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('primary_email', 'like', "%$s%")
                    ->orWhere('username', 'like', "%$s%")
                    ->orWhereHas('detail', fn ($q2) => $q2->where('first_name', 'like', "%$s%")->orWhere('last_name', 'like', "%$s%"));
            });
        }
        if ($request->filled('status_id')) {
            $query->where('status_id', $request->status_id);
        }
        if ($request->filled('role_id')) {
            $query->where('role_id', $request->role_id);
        }

        $sortable = ['id' => 'users.id', 'email' => 'primary_email', 'name' => 'primary_email'];
        $sort = $sortable[$request->get('sort')] ?? 'users.id';
        $dir = $request->get('dir', 'asc') === 'desc' ? 'desc' : 'asc';

        $members = $query->orderBy($sort, $dir)->paginate($this->perPage(25))->withQueryString();
        $statuses = MemberStatus::orderBy('name')->get();
        $roles = Role::orderBy('name')->get();

        return view('admin.members.index', compact('members', 'statuses', 'roles'));
    }

    public function impersonate(User $user)
    {
        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'impersonate_start',
            'model_type' => User::class,
            'model_id' => $user->id,
            'new_values' => ['target' => $user->primary_email],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'created_at' => now(),
        ]);

        session([
            'impersonating' => $user->id,
            'impersonating_name' => $user->name,
            'original_user_id' => auth()->id(),
        ]);

        auth()->login($user);

        return redirect()->route('profile.show')->with('success', __('Now impersonating :name', ['name' => $user->name]));
    }

    public function stopImpersonation()
    {
        $originalId = session('original_user_id');
        abort_unless($originalId, 403);
        session()->forget(['impersonating', 'impersonating_name', 'original_user_id']);

        if ($originalId) {
            auth()->loginUsingId($originalId);
        }

        return redirect()->route('admin.members.index')->with('success', __('Impersonation ended.'));
    }
}

```

### File: app/Http/Controllers/Admin/TrialRequestController.php
```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ThemeSetting;
use App\Models\TrialRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

/**
 * @author ClubCEP.eu
 */
class TrialRequestController extends Controller
{
    public function index()
    {
        $requests = TrialRequest::orderByDesc('created_at')->get();

        return view('admin.trial-requests.index', compact('requests'));
    }

    public function update(Request $request, TrialRequest $trialRequest)
    {
        $data = $request->validate([
            'status' => 'required|in:pending,confirmed,completed,cancelled',
            'confirmed_date' => 'nullable|date',
            'admin_notes' => 'nullable|string|max:1000',
        ]);

        $oldStatus = $trialRequest->status;

        if ($data['status'] === 'confirmed') {
            $data['confirmed_by'] = auth()->id();
        }

        $trialRequest->update($data);

        // Email the applicant on status change
        if ($oldStatus !== $data['status']) {
            $this->notifyApplicant($trialRequest);
        }

        return back()->with('success', __('Trial request updated.'));
    }

    private function notifyApplicant(TrialRequest $tr): void
    {
        $clubName = ThemeSetting::get('club_full_name', config('app.name'));

        $body = match ($tr->status) {
            'confirmed' => __("Dear :name,\n\nYour trial dive request with :club has been confirmed for :date.\n\nPlease bring: swimsuit, towel, and a positive attitude! We provide all diving equipment.\n\nIf you have any questions, reply to this email.\n\nSee you soon!\n:club", [
                'name' => $tr->first_name,
                'club' => $clubName,
                'date' => $tr->confirmed_date?->format('d/m/Y') ?? __('a date to be confirmed'),
            ]),
            'cancelled' => __("Dear :name,\n\nUnfortunately, your trial dive request with :club has been cancelled.\n\nIf you'd like to reschedule, please submit a new request on our website or reply to this email.\n\nBest regards,\n:club", [
                'name' => $tr->first_name,
                'club' => $clubName,
            ]),
            'completed' => __("Dear :name,\n\nThank you for your trial dive with :club! We hope you enjoyed the experience.\n\nIf you'd like to join the club, you can register on our website.\n\nHappy bubbles!\n:club", [
                'name' => $tr->first_name,
                'club' => $clubName,
            ]),
            default => null,
        };

        if ($body) {
            $subject = match ($tr->status) {
                'confirmed' => __('Trial Dive Confirmed — :club', ['club' => $clubName]),
                'cancelled' => __('Trial Dive Update — :club', ['club' => $clubName]),
                'completed' => __('Thank You — :club', ['club' => $clubName]),
            };

            Mail::raw($body, fn ($m) => $m->to($tr->email)->subject($subject));
        }
    }
}

```

### File: app/Http/Controllers/Admin/DiveSiteController.php
```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DiveSite;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DiveSiteController extends Controller
{
    public function index()
    {
        $sites = DiveSite::orderBy('name')->get();

        return view('admin.dive-sites.index', compact('sites'));
    }

    public function create()
    {
        return view('admin.dive-sites.form', ['site' => new DiveSite]);
    }

    public function store(Request $request)
    {
        $data = $this->validate($request);
        if ($request->hasFile('image')) {
            $data['image_path'] = $request->file('image')->store('dive-sites', 'public');
        }
        if ($request->hasFile('map_image')) {
            $data['map_image_path'] = $request->file('map_image')->store('dive-sites', 'public');
        }
        if ($request->hasFile('site_plan')) {
            $data['site_plan_path'] = $request->file('site_plan')->store('dive-sites', 'public');
        }
        DiveSite::create($data);

        return redirect()->route('admin.dive-sites.index')->with('success', __('Dive site created.'));
    }

    public function edit(DiveSite $diveSite)
    {
        return view('admin.dive-sites.form', ['site' => $diveSite]);
    }

    public function update(Request $request, DiveSite $diveSite)
    {
        $data = $this->validate($request);
        foreach (['image' => 'image_path', 'map_image' => 'map_image_path', 'site_plan' => 'site_plan_path'] as $field => $col) {
            if ($request->hasFile($field)) {
                if ($diveSite->$col) {
                    Storage::disk('public')->delete($diveSite->$col);
                }
                $data[$col] = $request->file($field)->store('dive-sites', 'public');
            }
        }
        $diveSite->update($data);

        return redirect()->route('admin.dive-sites.edit', $diveSite)->with('success', __('Dive site updated.'));
    }

    public function destroy(DiveSite $diveSite)
    {
        if ($diveSite->image_path) {
            Storage::disk('public')->delete($diveSite->image_path);
        }
        $diveSite->delete();

        return back()->with('success', __('Dive site deleted.'));
    }

    private function validate(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:255',
            'country' => 'nullable|string|max:100',
            'region' => 'nullable|string|max:100',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'max_depth' => 'nullable|integer|min:1|max:300',
            'water_type' => 'nullable|in:'.implode(',', DiveSite::WATER_TYPES),
            'conditions' => 'nullable|string',
            'marine_life' => 'nullable|string',
            'safety_notes' => 'nullable|string',
            'access_notes' => 'nullable|string',
            'facilities' => 'nullable|string',
            'food_options' => 'nullable|string',
            'nearest_hospital' => 'nullable|string',
            'website_url' => 'nullable|url|max:500',
            'entry_fee' => 'nullable|numeric|min:0',
            'booking_url' => 'nullable|url|max:500',
            'image' => 'nullable|image|max:5120',
            'map_image' => 'nullable|image|max:5120',
            'site_plan' => 'nullable|file|mimes:jpg,jpeg,png,gif,svg,pdf|max:10240',
            'safety_docs_folder' => 'nullable|string|max:255',
            'is_active' => 'boolean',
        ]);
    }
}

```

### File: app/Http/Controllers/Admin/SettingsController.php
```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFederationRequest;
use App\Http\Requests\StoreMaintenanceRuleRequest;
use App\Http\Requests\StoreMedicalRuleRequest;
use App\Http\Requests\StoreMembershipFeeRequest;
use App\Models\EquipmentMaintenanceRule;
use App\Models\Federation;
use App\Models\MedicalComplianceRule;
use App\Models\MembershipFee;
use App\Models\MemberStatus;
use App\Models\ThemeSetting;
use App\Services\LicenseService;
use App\Services\ThemeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class SettingsController extends Controller
{
    public function index()
    {
        return view('admin.settings.index', [
            'federations' => Federation::orderBy('acronym')->get(),
            'statuses' => MemberStatus::orderBy('name')->get(),
            'medicalRules' => MedicalComplianceRule::with('federation')->orderBy('federation_id')->orderBy('age_bracket_low')->get(),
            'maintenanceRules' => EquipmentMaintenanceRule::orderBy('equipment_type')->get(),
            'themeSettings' => ThemeSetting::all_settings(),
            'themePresets' => ThemeService::presets(),
            'membershipFees' => MembershipFee::with('status')->orderBy('season_year', 'desc')->orderBy('status_id')->get(),
        ]);
    }

    // --- Federations ---
    public function storeFederation(StoreFederationRequest $request)
    {
        Federation::create($request->validated());

        return back()->with('success', __('Federation added.'));
    }

    public function updateFederation(StoreFederationRequest $request, Federation $federation)
    {
        $federation->update($request->validated());

        return back()->with('success', __('Federation updated.'));
    }

    public function destroyFederation(Federation $federation)
    {
        $federation->delete();

        return back()->with('success', __('Federation deleted.'));
    }

    // --- Member Statuses ---
    public function storeStatus(Request $request)
    {
        $v = $request->validate(['name' => 'required|string|max:100', 'slug' => 'required|string|max:50|unique:member_statuses', 'description' => 'nullable|string']);
        MemberStatus::create($v);

        return back()->with('success', __('Status added.'));
    }

    public function updateStatus(Request $request, MemberStatus $status)
    {
        $v = $request->validate(['name' => 'required|string|max:100', 'description' => 'nullable|string']);
        $status->update($v);

        return back()->with('success', __('Status updated.'));
    }

    // --- Medical Compliance Rules ---
    public function storeMedicalRule(StoreMedicalRuleRequest $request)
    {
        MedicalComplianceRule::create($request->validated());

        return back()->with('success', __('Medical rule added.'));
    }

    public function updateMedicalRule(StoreMedicalRuleRequest $request, MedicalComplianceRule $rule)
    {
        $rule->update($request->validated());

        return back()->with('success', __('Medical rule updated.'));
    }

    public function destroyMedicalRule(MedicalComplianceRule $rule)
    {
        $rule->delete();

        return back()->with('success', __('Medical rule deleted.'));
    }

    // --- Equipment Maintenance Rules ---
    public function storeMaintenanceRule(StoreMaintenanceRuleRequest $request)
    {
        $v = $request->validated();
        $v['is_mandatory'] = $request->boolean('is_mandatory');
        EquipmentMaintenanceRule::create($v);

        return back()->with('success', __('Maintenance rule added.'));
    }

    public function updateMaintenanceRule(StoreMaintenanceRuleRequest $request, EquipmentMaintenanceRule $rule)
    {
        $v = $request->validated();
        $v['is_mandatory'] = $request->boolean('is_mandatory');
        $rule->update($v);

        return back()->with('success', __('Maintenance rule updated.'));
    }

    public function destroyMaintenanceRule(EquipmentMaintenanceRule $rule)
    {
        $rule->delete();

        return back()->with('success', __('Maintenance rule deleted.'));
    }

    // --- Membership Fees ---
    public function storeMembershipFee(StoreMembershipFeeRequest $request)
    {
        $v = $request->validated();
        MembershipFee::updateOrCreate(
            ['season_year' => $v['season_year'], 'status_id' => $v['status_id']],
            $v
        );

        return back()->with('success', __('Membership fee saved.'));
    }

    public function destroyMembershipFee(MembershipFee $fee)
    {
        $fee->delete();

        return back()->with('success', __('Membership fee deleted.'));
    }

    public function updateTheme(Request $request)
    {
        $allowed = ['primary_color', 'secondary_color', 'accent_color', 'header_gradient_start', 'header_gradient_end', 'footer_bg', 'body_bg', 'body_color', 'logo_text', 'logo_emoji', 'logo_accent_text', 'logo_plain_text', 'club_full_name', 'layout_width', 'card_style', 'header_bubbles', 'preset', 'club_iban', 'club_bic', 'club_email', 'club_address', 'club_phone', 'club_country', 'warehouse_address', 'warehouse_lat', 'warehouse_lon', 'club_short_code', 'social_auto_publish', 'fb_group_is_closed', 'fb_group_id', 'fb_publish_enabled', 'ig_publish_enabled', 'ig_account_id', 'license_key', 'ui_style', 'ui_show_icons', 'training_locations', 'social_facebook', 'social_instagram', 'social_youtube', 'social_tiktok', 'social_whatsapp', 'social_x'];

        // Handle enabled_locales checkbox array separately
        if ($request->has('enabled_locales')) {
            $locales = array_intersect($request->input('enabled_locales', []), array_keys(config('languages', [])));
            ThemeSetting::set('enabled_locales', json_encode(array_values($locales)));
        }
        foreach ($allowed as $key) {
            if ($request->has($key)) {
                ThemeSetting::set($key, $request->input($key));
            }
        }
        Cache::forget('theme_css');
        Cache::forget('theme_settings');
        if ($request->has('license_key')) {
            LicenseService::flushCache();
        }

        return back()->with('success', __('Theme updated.'));
    }

    public function applyPreset(Request $request)
    {
        $presets = ThemeService::presets();
        $name = $request->input('preset');
        if (! isset($presets[$name])) {
            return back()->with('error', __('Unknown preset.'));
        }
        foreach ($presets[$name] as $k => $v) {
            ThemeSetting::set($k, $v);
        }
        ThemeSetting::set('preset', $name);
        Cache::forget('theme_css');
        Cache::forget('theme_settings');

        return back()->with('success', __('Preset applied: ').$name);
    }

    public function uploadLogo(Request $request)
    {
        $request->validate(['logo' => 'required|image|max:2048']);
        $path = $request->file('logo')->store('theme', 'public');
        ThemeSetting::set('logo_image', $path);
        Cache::forget('theme_settings');

        return back()->with('success', __('Logo uploaded.'));
    }
}

```

### File: app/Http/Controllers/MembersDirectoryController.php
```php
<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\PaginatesFromRequest;
use App\Models\User;
use Illuminate\Http\Request;

class MembersDirectoryController extends Controller
{
    use PaginatesFromRequest;

    public function directory(Request $request)
    {
        $query = User::with(['detail', 'role', 'status'])
            ->whereHas('detail', fn ($q) => $q->whereNotNull('first_name'));

        if ($request->filled('search')) {
            $s = $request->search;
            $query->whereHas('detail', fn ($q) => $q->where('first_name', 'like', "%{$s}%")->orWhere('last_name', 'like', "%{$s}%"));
        }

        $members = $query->orderByDesc('id')->paginate($this->perPage(30))->withQueryString();

        return view('members.directory', compact('members'));
    }

    public function trombinoscope()
    {
        $members = User::with('detail')
            ->whereHas('detail', fn ($q) => $q->whereNotNull('first_name'))
            ->get()
            ->sortBy(fn ($u) => $u->detail?->last_name);

        return view('members.trombinoscope', compact('members'));
    }
}

```

### File: app/Http/Controllers/EventController.php
```php
<?php

/**
 * Event CRUD, registration, cancellation, and photo management.
 *
 * Handles the full event lifecycle: creation, editing, self-registration,
 * proxy registration (bureau/instructor registering any member), cancellation
 * with audit trail, waiting list auto-promotion, deposit-based payment generation,
 * and GDPR-compliant photo uploads with auto-social-media publishing.
 *
 * @author  ClubCEP.eu
 *
 * @see     \App\Models\Event
 * @see     \App\Models\EventRegistration
 * @see     \App\Services\MedicalComplianceService  — medical cert gate for dive events
 */

namespace App\Http\Controllers;

use App\Helpers\HtmlSanitizer;
use App\Models\DiveSite;
use App\Models\EmailLog;
use App\Models\Event;
use App\Models\EventPhoto;
use App\Models\EventRegistration;
use App\Models\GdprConsent;
use App\Models\PaymentExpected;
use App\Models\Season;
use App\Models\ThemeSetting;
use App\Models\User;
use App\Services\FaceDetectionService;
use App\Services\ImageQualityService;
use App\Services\MedicalComplianceService;
use App\Services\PushNotificationService;
use App\Services\SocialPublishService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class EventController extends Controller
{
    // ─── Calendar Views ────────────────────────────────────────

    public function index(Request $request)
    {
        $view = $request->get('view', 'month');
        $date = $request->get('date') ? Carbon::parse($request->get('date')) : now();

        $query = Event::where('status', '!=', 'cancelled');

        if ($view === 'month') {
            $start = $date->copy()->startOfMonth();
            $end = $date->copy()->endOfMonth();
        } elseif ($view === 'week') {
            $start = $date->copy()->startOfWeek();
            $end = $date->copy()->endOfWeek();
        } else {
            $start = $date->copy()->startOfDay();
            $end = $date->copy()->endOfDay();
        }

        $events = $query->whereBetween('event_date', [$start, $end])
            ->orderBy('event_date')->orderBy('event_time')
            ->withCount(['confirmedRegistrations as confirmed_count', 'waitingRegistrations as waiting_count'])
            ->get();

        return view('events.index', compact('events', 'view', 'date', 'start', 'end'));
    }

    public function show(Event $event)
    {
        $event->load([
            'registrations.user.detail', 'registrations.user.certificationLevels',
            'registrations.registeredByUser.detail', 'registrations.cancelledByUser.detail',
            'instructor.detail', 'responsible.detail', 'season', 'diveSite',
            'diveGroups.members.user.certificationLevels',
        ]);
        $userReg = auth()->check() ? $event->registrations()->where('user_id', auth()->id())->first() : null;
        $emailHistory = EmailLog::where('event_id', $event->id)->orderByDesc('created_at')->get();
        $members = auth()->check() ? User::with('detail')->whereHas('role', fn ($q) => $q->where('id', '>', 1))->orderBy('username')->get() : collect();

        return view('events.show', compact('event', 'userReg', 'emailHistory', 'members'));
    }

    // ─── Event CRUD ──────────────────────────────────────────

    public function create()
    {
        $this->authorizeBureau();
        $seasons = Season::orderByDesc('year')->get();
        $instructors = User::whereHas('role', fn ($q) => $q->whereIn('slug', ['instructor', 'bureau_master']))->with('detail')->get();
        $diveSites = DiveSite::active()->orderBy('name')->get();
        $locationSuggestions = $this->topLocations();

        return view('events.form', ['event' => new Event, 'seasons' => $seasons, 'instructors' => $instructors, 'diveSites' => $diveSites, 'locationSuggestions' => $locationSuggestions]);
    }

    public function store(Request $request)
    {
        $this->authorizeBureau();
        $data = $this->validateEvent($request);
        $data['description'] = HtmlSanitizer::clean($data['description'] ?? '');
        $data['created_by'] = auth()->id();
        $data['assistant_ids'] = array_map('intval', array_filter((array) $request->assistant_ids));
        $data['participant_email'] = null; // will be set after creation

        $event = Event::create($data);
        $event->update(['participant_email' => 'event-'.$event->id.'@'.config('club.domain')]);

        // Push notification for non-routine events
        if (! in_array($event->event_type, ['pool', 'theory'])) {
            app(PushNotificationService::class)->sendToAll(
                __('New Event'),
                $event->title.' — '.$event->event_date?->format('d/m/Y'),
                route('events.show', $event)
            );
        }

        return redirect()->route('events.show', $event)->with('success', __('Event created.'));
    }

    public function edit(Event $event)
    {
        $this->authorizeEventEdit($event);
        $seasons = Season::orderByDesc('year')->get();
        $instructors = User::whereHas('role', fn ($q) => $q->whereIn('slug', ['instructor', 'bureau_master']))->with('detail')->get();
        $diveSites = DiveSite::active()->orderBy('name')->get();
        $locationSuggestions = $this->topLocations();

        return view('events.form', compact('event', 'seasons', 'instructors', 'diveSites', 'locationSuggestions'));
    }

    public function update(Request $request, Event $event)
    {
        $this->authorizeEventEdit($event);
        $data = $this->validateEvent($request);
        $data['description'] = HtmlSanitizer::clean($data['description'] ?? '');
        $data['assistant_ids'] = array_map('intval', array_filter((array) $request->assistant_ids));
        $event->update($data);

        return redirect()->route('events.show', $event)->with('success', __('Event updated.'));
    }

    // ─── Registration & Cancellation ──────────────────────────
    // Supports self-registration and proxy registration (any member can be
    // registered by bureau/instructor). Medical compliance is enforced for
    // pool, dive, and training events. Waiting list auto-promotes on cancel.

    public function register(Event $event, Request $request)
    {
        $actor = auth()->user();
        $targetUserId = $request->input('user_id', $actor->id);
        $targetUser = User::findOrFail($targetUserId);
        $comment = $request->input('comment');

        if (! $event->isRegistrationOpen()) {
            return back()->with('error', __('Registration is not open for this event.'));
        }

        if ($event->registrations()->where('user_id', $targetUser->id)->whereIn('status', ['confirmed', 'waiting'])->exists()) {
            return back()->with('error', __(':name is already registered.', ['name' => $targetUser->name]));
        }

        // Remove old cancelled registration if re-registering
        $event->registrations()->where('user_id', $targetUser->id)->where('status', 'cancelled')->delete();

        // Medical compliance gate — pool, dive, training require valid cert
        if (in_array($event->event_type, ['pool', 'dive', 'training'])) {
            // Profile completeness gate — require DOB, sex, mobile, emergency contact
            if (! $targetUser->hasDiveProfile()) {
                $fields = implode(', ', $targetUser->missingDiveProfileFields());
                $msg = $targetUser->id === $actor->id
                    ? __('Please complete your profile before registering: :fields', ['fields' => $fields])
                    : __(':name must complete their profile: :fields', ['name' => $targetUser->name, 'fields' => $fields]);

                return back()->with('error', $msg);
            }

            if (! app(MedicalComplianceService::class)->isCompliant($targetUser)) {
                $msg = $targetUser->id === $actor->id
                    ? __('You need a valid medical certificate to register for this event. Please upload one in your profile.')
                    : __(':name needs a valid medical certificate.', ['name' => $targetUser->name]);

                return back()->with('error', $msg);
            }
        }

        DB::transaction(function () use ($event, $targetUser, $actor, $comment) {
            $registeredBy = $targetUser->id !== $actor->id ? $actor->id : null;

            if ($event->isFull()) {
                if (! $event->waiting_list_enabled) {
                    return back()->with('error', __('Event is full.'));
                }
                $pos = ($event->waitingRegistrations()->max('waiting_list_position') ?? 0) + 1;
                EventRegistration::create([
                    'event_id' => $event->id,
                    'user_id' => $targetUser->id,
                    'status' => 'waiting',
                    'waiting_list_position' => $pos,
                    'comment' => $comment,
                    'registered_by' => $registeredBy,
                ]);
            } else {
                EventRegistration::create([
                    'event_id' => $event->id,
                    'user_id' => $targetUser->id,
                    'status' => 'confirmed',
                    'comment' => $comment,
                    'registered_by' => $registeredBy,
                ]);

                // Auto-generate payment only for deposits (not estimated_cost)
                $totalDue = 0;
                $components = [];
                foreach ([1, 2, 3] as $i) {
                    $amt = $event->{"deposit_{$i}_amount"};
                    if ($amt > 0) {
                        $totalDue += $amt;
                        $components[] = ['label' => __('Deposit')." $i".($event->{"deposit_{$i}_date"} ? ' ('.$event->{"deposit_{$i}_date"}->format('d/m/Y').')' : ''), 'amount' => (float) $amt];
                    }
                }
                if ($totalDue > 0) {
                    $detail = $targetUser->detail;
                    $name = strtoupper($detail?->last_name ?? 'MEMBER');
                    PaymentExpected::create([
                        'user_id' => $targetUser->id,
                        'type' => 'event',
                        'event_id' => $event->id,
                        'season_year' => $event->event_date->format('Y'),
                        'amount_due' => $totalDue,
                        'communication' => ThemeSetting::get('club_short_code', config('club.id', 'CLUB')).'-'.$event->event_date->format('Y').'-'.$event->id.'-'.$name,
                        'components' => $components,
                        'status' => 'pending',
                    ]);
                }
            }
        });

        $who = $targetUser->id !== $actor->id ? $targetUser->name : __('You');

        return back()->with('success', __(':who registered successfully.', ['who' => $who]));
    }

    public function cancelRegistration(Event $event, Request $request)
    {
        $actor = auth()->user();
        $targetUserId = $request->input('user_id', $actor->id);
        $reg = $event->registrations()->where('user_id', $targetUserId)->whereIn('status', ['confirmed', 'waiting'])->firstOrFail();
        $wasConfirmed = $reg->status === 'confirmed';

        $reg->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancelled_by' => $actor->id !== (int) $targetUserId ? $actor->id : null,
            'cancel_comment' => $request->input('cancel_comment'),
        ]);

        // Cancel unpaid payment; flag paid ones for refund review
        $paidPayment = PaymentExpected::where('event_id', $event->id)
            ->where('user_id', $targetUserId)
            ->where('status', 'paid')
            ->exists();

        PaymentExpected::where('event_id', $event->id)
            ->where('user_id', $targetUserId)
            ->where('status', 'pending')
            ->delete();

        if ($paidPayment) {
            PaymentExpected::where('event_id', $event->id)
                ->where('user_id', $targetUserId)
                ->where('status', 'paid')
                ->update(['refund_review_needed' => true]);
        }

        // Auto-promote first waiting list entry
        if ($wasConfirmed) {
            $next = $event->waitingRegistrations()->first();
            if ($next) {
                $next->update(['status' => 'confirmed', 'waiting_list_position' => null]);
            }
        }

        return back()->with('success', __('Registration cancelled.'));
    }

    public function cancel(Event $event)
    {
        $this->authorizeBureau();
        $event->update(['status' => 'cancelled']);

        return redirect()->route('events.index')->with('success', __('Event cancelled.'));
    }

    // ─── Photo Gallery (GDPR-gated) ──────────────────────────
    // Only confirmed participants with photo_publication GDPR consent can upload.
    // Photos are auto-scored by quality heuristic and published to social media.

    public function uploadPhoto(Request $request, Event $event)
    {
        $request->validate([
            'photos.*' => 'required|image|max:10240',
            'caption' => 'nullable|string|max:255',
            'gdpr_consent' => 'required|accepted',
        ]);

        // GDPR: check photo_publication consent
        $consent = GdprConsent::where('user_id', auth()->id())
            ->where('consent_type', 'photo_publication')->where('granted', true)->exists();
        if (! $consent) {
            return back()->with('error', __('You must grant photo publication consent in Privacy settings before uploading event photos.'));
        }

        foreach ($request->file('photos', []) as $file) {
            $realPath = $file->getRealPath();
            $path = $file->store('event-photos/'.$event->id, 'public');

            // Quality score (sharpness, exposure, saturation, contrast, resolution)
            $score = app(ImageQualityService::class)->score($realPath);

            // Face detection — photos with faces are hidden from public/anonymous pages
            $hasFaces = app(FaceDetectionService::class)->hasFaces($realPath);

            $photo = EventPhoto::create([
                'event_id' => $event->id,
                'uploaded_by' => auth()->id(),
                'path' => $path,
                'caption' => $request->caption,
                'quality_score' => $score,
                'has_faces' => $hasFaces,
                'gdpr_consent' => true,
            ]);

            // Auto-publish to social media if eligible
            app(SocialPublishService::class)->publishToFacebook($photo);
        }

        return back()->with('success', __('Photos uploaded.'));
    }

    public function deletePhoto(Event $event, EventPhoto $photo)
    {
        $user = auth()->user();
        abort_unless($photo->event_id === $event->id, 404);
        abort_unless($user->isBureau() || $photo->uploaded_by === $user->id, 403);

        Storage::disk('public')->delete($photo->path);
        $photo->delete();

        return back()->with('success', __('Photo deleted.'));
    }

    // ─── Authorization & Helpers ──────────────────────────────

    private function validateEvent(Request $request): array
    {
        return $request->validate([
            'title' => 'required|string|max:255',
            'color_hex' => 'nullable|string|max:7',
            'event_type' => 'required|in:pool,dive,training,theory,social',
            'event_date' => 'required|date',
            'event_time' => 'nullable|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i',
            'end_date' => 'nullable|date|after_or_equal:event_date',
            'location' => 'nullable|string|max:500',
            'description' => 'nullable|string',
            'responsible_id' => 'nullable|exists:users,id',
            'max_participants' => 'nullable|integer|min:1',
            'waiting_list_enabled' => 'boolean',
            'inscription_open_at' => 'nullable|date',
            'inscriptions_closed' => 'boolean',
            'levels_display' => 'boolean',
            'confirmation_required' => 'boolean',
            'estimated_cost' => 'nullable|numeric|min:0',
            'deposit_1_date' => 'nullable|date',
            'deposit_1_amount' => 'nullable|numeric|min:0',
            'deposit_2_date' => 'nullable|date',
            'deposit_2_amount' => 'nullable|numeric|min:0',
            'deposit_3_date' => 'nullable|date',
            'deposit_3_amount' => 'nullable|numeric|min:0',
            'instructor_id' => 'nullable|exists:users,id',
            'permissions_expire_date' => 'nullable|date',
            'status' => 'nullable|in:scheduled,cancelled,completed',
            'season_id' => 'nullable|exists:seasons,id',
            'dive_site_id' => 'nullable|exists:dive_sites,id',
        ]);
    }

    private function authorizeBureau(): void
    {
        abort_unless(auth()->user()->isBureau(), 403);
    }

    /** Bureau can always edit; instructors can edit their own events until permissions expire. */
    private function authorizeEventEdit(Event $event): void
    {
        $user = auth()->user();
        if ($user->isBureau()) {
            return;
        }
        if ($event->instructor_id === $user->id && (! $event->permissions_expire_date || $event->permissions_expire_date->isFuture())) {
            return;
        }
        abort(403);
    }

    private function topLocations(): array
    {
        return Event::selectRaw('location, count(*) as cnt')
            ->whereNotNull('location')->where('location', '!=', '')
            ->groupBy('location')->orderByDesc('cnt')
            ->pluck('location')->all();
    }
}

```

### File: app/Http/Controllers/QrCodeController.php
```php
<?php

/**
 * QR code generation: vCard, SEPA EPC, federation licence, and signed payment URLs.
 *
 * Signed payment QRs encode a URL (not raw bank details) so the club's TLS
 * certificate proves identity and an HMAC signature prevents tampering.
 * This mitigates quishing attacks on EPC QR codes.
 *
 * @author ClubCEP.eu
 */

namespace App\Http\Controllers;

use App\Models\MemberLicence;
use App\Models\PaymentExpected;
use App\Models\ThemeSetting;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class QrCodeController extends Controller
{
    public function vcard()
    {
        $user = auth()->user();
        $d = $user->detail;

        $vcard = "BEGIN:VCARD\r\nVERSION:3.0\r\n";
        $vcard .= "N:{$d?->last_name};{$d?->first_name}\r\n";
        $vcard .= "FN:{$user->name}\r\n";
        $vcard .= "EMAIL:{$user->primary_email}\r\n";
        if ($d?->phone_mobile) {
            $vcard .= "TEL;TYPE=CELL:{$d->phone_mobile}\r\n";
        }
        $vcard .= 'ORG:'.ThemeSetting::get('club_full_name', 'Diving Club')."\r\n";
        $vcard .= "END:VCARD\r\n";

        return $this->generatePng($vcard, "vcard-{$user->id}.png");
    }

    // ─── Signed Payment QR (anti-quishing) ─────────────────────

    /** Generate a QR containing a signed verification URL instead of raw EPC data. */
    public function signedPaymentQr(Request $request)
    {
        $amount = round((float) $request->query('amount', 0), 2);
        $communication = $request->query('communication', '');

        if ($amount <= 0) {
            return response('Invalid amount', 400);
        }

        $url = self::buildSignedUrl($amount, $communication);

        return $this->generatePng($url, 'payment-qr.png', false);
    }

    /** Verification page — user lands here after scanning the QR. */
    public function verifyPayment(Request $request)
    {
        $amount = (float) $request->query('a', 0);
        $communication = $request->query('c', '');
        $expires = (int) $request->query('e', 0);
        $signature = $request->query('s', '');

        // Verify signature
        $payload = $amount.'|'.$communication.'|'.$expires;
        $expected = hash_hmac('sha256', $payload, config('app.key'));

        if (! hash_equals($expected, $signature)) {
            return view('payment-verify', ['valid' => false, 'error' => __('Invalid signature — this QR code may have been tampered with.')]);
        }

        if ($expires < time()) {
            return view('payment-verify', ['valid' => false, 'error' => __('This payment QR has expired. Please generate a new one.')]);
        }

        $cfg = config('cotisation');

        return view('payment-verify', [
            'valid' => true,
            'amount' => $amount,
            'communication' => $communication,
            'iban' => $cfg['iban'],
            'bic' => $cfg['bic'],
            'beneficiary' => $cfg['beneficiary'],
            'bank' => $cfg['bank'],
        ]);
    }

    /** Build a signed URL with HMAC and expiry. */
    public static function buildSignedUrl(float $amount, string $communication): string
    {
        $expires = time() + 86400 * 30; // 30 days validity
        $payload = $amount.'|'.$communication.'|'.$expires;
        $signature = hash_hmac('sha256', $payload, config('app.key'));

        return route('payment.verify', [
            'a' => $amount,
            'c' => $communication,
            'e' => $expires,
            's' => $signature,
        ]);
    }

    // ─── Legacy EPC QR (kept for backward compatibility) ───────

    public function sepaPublic(Request $request)
    {
        $amount = $request->query('amount', 0);
        $communication = $request->query('communication', '');
        $iban = ThemeSetting::get('club_iban') ?: config('club.iban', '');

        if (! $iban) {
            return response('No IBAN configured', 400);
        }

        $epc = "BCD\n002\n1\nSCT\n";
        $epc .= ThemeSetting::get('club_bic')."\n";
        $epc .= ThemeSetting::get('club_full_name', 'Diving Club')."\n";
        $epc .= $iban."\n";
        $epc .= 'EUR'.number_format((float) $amount, 2, '.', '')."\n";
        $epc .= "\n";
        $epc .= $communication."\n";

        return $this->generatePng($epc, 'sepa-dues.png', false);
    }

    public function sepa(PaymentExpected $payment)
    {
        $user = auth()->user();
        if ($payment->user_id !== $user->id && ! $user->isBureauMaster()) {
            abort(403);
        }

        $iban = ThemeSetting::get('club_iban') ?: config('club.iban', '');
        $epc = "BCD\n002\n1\nSCT\n";
        $epc .= ThemeSetting::get('club_bic')."\n";
        $epc .= ThemeSetting::get('club_full_name', 'Diving Club')."\n";
        $epc .= $iban."\n";
        $epc .= 'EUR'.number_format($payment->amount_due, 2, '.', '')."\n";
        $epc .= "\n";
        $epc .= $payment->communication."\n";

        return $this->generatePng($epc, "sepa-{$payment->id}.png", false);
    }

    public function federation(MemberLicence $licence)
    {
        $user = auth()->user();
        if ($licence->user_id !== $user->id && ! $user->isBureau() && ! $user->detail?->active_instructor) {
            abort(403);
        }

        if (! $licence->licence_number) {
            return back()->with('error', __('No licence number — licence pending.'));
        }

        // FFESSM InfoLicencié URL: requires numeric part of licence + federation key
        if ($licence->federation?->acronym === 'FFESSM' && $licence->federation_key) {
            $number = preg_replace('/^[A-Z]-\d{2}-/', '', $licence->licence_number);
            $url = "https://infolicencie.ffessm.fr/Home/InfoLicence?number={$number}&key={$licence->federation_key}";
        } else {
            // Generic fallback for other federations
            $key = hash('sha256', $licence->licence_number.config('club.id').config('club.federation_salt'));
            $url = 'https://verify.'.config('club.domain', 'example.com')."/licence/{$key}";
        }

        return $this->generatePng($url, "federation-{$licence->id}.png", false);
    }

    private function generatePng(string $data, string $filename, bool $download = true): Response
    {
        $result = Builder::create()
            ->writer(new PngWriter)
            ->data($data)
            ->encoding(new Encoding('UTF-8'))
            ->size(300)
            ->margin(10)
            ->build();

        $headers = ['Content-Type' => 'image/png'];
        if ($download) {
            $headers['Content-Disposition'] = "attachment; filename={$filename}";
        }

        return response($result->getString(), 200, $headers);
    }
}

```

### File: app/Http/Controllers/StagingMailController.php
```php
<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\PaginatesFromRequest;
use App\Models\EmailLog;
use Illuminate\Http\Request;

class StagingMailController extends Controller
{
    use PaginatesFromRequest;

    public function index(Request $request)
    {
        abort_unless(config('app.staging_mode'), 404);

        $mails = EmailLog::where('status', 'staging_captured')
            ->orderByDesc('created_at')
            ->paginate($this->perPage(25));

        return view('staging.mailbox', compact('mails'));
    }

    public function show(EmailLog $mail)
    {
        abort_unless(config('app.staging_mode'), 404);
        abort_unless($mail->status === 'staging_captured', 404);

        return view('staging.mail-show', compact('mail'));
    }

    public function raw(EmailLog $mail)
    {
        abort_unless(config('app.staging_mode'), 404);
        abort_unless($mail->status === 'staging_captured', 404);

        return response($mail->body)->header('Content-Type', 'text/html');
    }

    public function clear()
    {
        abort_unless(config('app.staging_mode'), 404);

        EmailLog::where('status', 'staging_captured')->delete();

        return back()->with('success', 'Staging mailbox cleared.');
    }
}

```

### File: app/Http/Controllers/DuesCalculatorController.php
```php
<?php

namespace App\Http\Controllers;

use App\Models\MembershipFee;
use App\Models\MembershipFeeComponent;
use App\Models\MemberStatus;
use App\Models\ThemeSetting;
use Illuminate\Http\Request;

class DuesCalculatorController extends Controller
{
    public function show()
    {
        $year = date('Y');
        $statuses = MemberStatus::orderBy('name')->get();
        $fees = MembershipFee::where('season_year', $year)->with('status')->get()->keyBy('status_id');
        $optionals = MembershipFeeComponent::where('is_optional', true)->orderBy('sort_order')->get();

        return view('dues-calculator', compact('year', 'statuses', 'fees', 'optionals'));
    }

    public function calculate(Request $request)
    {
        $year = $request->input('season_year', date('Y'));
        $statusId = $request->input('status_id');
        $selectedOptionals = $request->input('optionals', []);
        // Merge radio group selections
        foreach ($request->all() as $key => $val) {
            if (str_starts_with($key, 'optionals_') && $val) {
                $selectedOptionals[] = $val;
            }
        }

        $statuses = MemberStatus::orderBy('name')->get();
        $fees = MembershipFee::where('season_year', $year)->with('status')->get()->keyBy('status_id');
        $optionals = MembershipFeeComponent::where('is_optional', true)->orderBy('sort_order')->get();

        $baseFee = $fees[$statusId]?->amount ?? 0;
        $optionalTotal = MembershipFeeComponent::where('is_optional', true)->whereIn('slug', $selectedOptionals)->sum('amount');
        $total = round($baseFee + $optionalTotal, 2);

        $status = $statuses->find($statusId);
        $lastName = strtoupper($request->input('last_name', ''));
        $firstName = strtoupper($request->input('first_name', ''));
        $name = trim("$lastName $firstName");
        $opts = $selectedOptionals ? '+' . implode('+', $selectedOptionals) : '';
        $communication = ThemeSetting::get('club_short_code', config('club.id', 'CLUB')) . "-{$year}-{$name}{$opts}";

        $breakdown = [];
        $breakdown[] = ['label' => __('Membership') . ' (' . ($status?->name ?? '—') . ')', 'amount' => $baseFee];
        foreach (MembershipFeeComponent::where('is_optional', true)->whereIn('slug', $selectedOptionals)->get() as $opt) {
            $breakdown[] = ['label' => $opt->name, 'amount' => $opt->amount];
        }

        return view('dues-calculator', compact('year', 'statuses', 'fees', 'optionals', 'statusId', 'selectedOptionals', 'total', 'communication', 'breakdown', 'lastName', 'firstName'));
    }
}

```

### File: app/Http/Controllers/TrialController.php
```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTrialRequest;
use App\Models\TrialRequest;

class TrialController extends Controller
{
    public function show()
    {
        return view('trial.show');
    }

    public function store(StoreTrialRequest $request)
    {
        $data = $request->validated();
        unset($data['website']);

        // Timestamp check
        if (now()->timestamp - (int) $request->input('_ts', 0) < 3) {
            return back()->with('error', __('Please try again.'));
        }

        TrialRequest::create($data);

        return back()->with('success', __('Your request has been submitted! We will contact you to confirm a date and time.'));
    }
}

```

### File: app/Http/Controllers/Concerns/PaginatesFromRequest.php
```php
<?php

namespace App\Http\Controllers\Concerns;

trait PaginatesFromRequest
{
    protected function perPage(int $default = 30): int|string
    {
        $val = request('per_page', $default);

        return $val === 'all' ? 999999 : (int) min($val, 999999);
    }
}

```

### File: app/Http/Controllers/Auth/RegisterController.php
```php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterUserRequest;
use App\Models\MemberDetail;
use App\Models\Role;
use App\Models\User;
use App\Models\UserEmail;
use App\Services\PushNotificationService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class RegisterController extends Controller
{
    public function create()
    {
        return view('auth.register');
    }

    public function store(RegisterUserRequest $request)
    {
        $validated = $request->validated();

        // Bot check: form submitted too fast
        if (time() - (int) $request->_ts < 3) {
            return back()->withErrors(['email' => __('Please try again.')])->withInput();
        }

        $user = DB::transaction(function () use ($validated) {
            $memberRole = Role::where('slug', 'member')->first();

            $user = User::create([
                'primary_email' => $validated['email'],
                'password' => $validated['password'],
                'role_id' => $memberRole->id,
            ]);

            UserEmail::create([
                'user_id' => $user->id,
                'email' => $validated['email'],
                'is_primary' => true,
                'is_verified' => false,
            ]);

            MemberDetail::create([
                'user_id' => $user->id,
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
            ]);

            return $user;
        });

        event(new Registered($user));
        Auth::login($user);

        app(PushNotificationService::class)->sendToBureau(
            __('New Member'),
            $validated['first_name'].' '.$validated['last_name'],
            '/admin/members'
        );

        return redirect()->route('verification.notice');
    }
}

```

### File: app/Http/Controllers/Auth/LoginController.php
```php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\FailedLoginAttempt;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class LoginController extends Controller
{
    public function create()
    {
        return view('auth.login');
    }

    public function store(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        // Check lockout
        $recentFails = DB::table('failed_login_attempts')
            ->where('email', $request->email)
            ->where('attempted_at', '>=', now()->subMinutes(10))
            ->count();

        if ($recentFails >= 5) {
            return back()->withErrors(['email' => __('Account locked. Try again in 15 minutes.')]);
        }

        if (Auth::attempt(['primary_email' => $request->email, 'password' => $request->password], $request->boolean('remember'))) {
            $request->session()->regenerate();

            // Clear failed attempts
            DB::table('failed_login_attempts')->where('email', $request->email)->delete();

            return redirect()->intended(route('profile.show'));
        }

        // Record failed attempt
        DB::table('failed_login_attempts')->insert([
            'email' => $request->email,
            'ip_address' => $request->ip(),
            'attempted_at' => now(),
        ]);

        return back()->withErrors(['email' => __('Invalid credentials.')]);
    }

    public function destroy(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/');
    }
}

```

### File: app/Http/Controllers/Auth/SocialAuthController.php
```php
<?php

/**
 * Social (OAuth) authentication: redirect, callback, and pending link confirmation.
 *
 * When a social login email matches an existing account, the link is NOT
 * auto-applied. Instead, a pending link is stored in the session and the
 * existing account owner must confirm it after logging in. This prevents
 * account takeover via email spoofing from untrusted OAuth providers.
 *
 * @author ClubCEP.eu
 */

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\MemberDetail;
use App\Models\Role;
use App\Models\User;
use App\Models\UserEmail;
use App\Models\UserSocialAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Laravel\Socialite\Facades\Socialite;

class SocialAuthController extends Controller
{
    protected array $providers = ['google', 'microsoft', 'facebook', 'x'];

    public function redirect(string $provider)
    {
        abort_unless(in_array($provider, $this->providers), 404);

        return Socialite::driver($provider)->redirect();
    }

    public function callback(string $provider)
    {
        abort_unless(in_array($provider, $this->providers), 404);

        try {
            $socialUser = Socialite::driver($provider)->user();
        } catch (\Exception $e) {
            return redirect()->route('login')->with('error', __('Authentication failed. Please try again.'));
        }

        // 1. Existing social link — just update tokens and log in
        $social = UserSocialAccount::where('provider', $provider)
            ->where('provider_user_id', $socialUser->getId())
            ->first();

        if ($social) {
            $social->update(['token' => $socialUser->token, 'refresh_token' => $socialUser->refreshToken]);
            Auth::login($social->user, true);

            return redirect()->intended(route('profile.show'));
        }

        // 2. Email matches existing account — require confirmation (anti-takeover)
        $email = $socialUser->getEmail();

        if (! $email) {
            return redirect()->route('login')->with('error', __('No email returned by :provider. Please use another login method.', ['provider' => ucfirst($provider)]));
        }

        $emailRecord = UserEmail::where('email', $email)->first();
        $existingUser = $emailRecord?->user ?? User::where('primary_email', $email)->first();

        if ($existingUser) {
            session([
                'pending_social_link' => [
                    'provider' => $provider,
                    'provider_user_id' => $socialUser->getId(),
                    'email' => $email,
                    'token' => encrypt($socialUser->token),
                    'refresh_token' => encrypt($socialUser->refreshToken ?? ''),
                    'user_id' => $existingUser->id,
                ],
            ]);

            return redirect()->route('login')->with('warning',
                __('A :provider account with this email exists. Please log in with your password to confirm the link.', ['provider' => ucfirst($provider)])
            );
        }

        // 3. New user — create account
        $user = DB::transaction(function () use ($provider, $socialUser) {
            $memberRole = Role::where('slug', 'member')->first();

            $user = User::create([
                'primary_email' => $socialUser->getEmail(),
                'role_id' => $memberRole->id,
                'email_verified_at' => now(),
            ]);

            UserEmail::create([
                'user_id' => $user->id,
                'email' => $socialUser->getEmail(),
                'is_primary' => true,
                'is_verified' => true,
                'label' => $provider,
            ]);

            UserSocialAccount::create([
                'user_id' => $user->id,
                'provider' => $provider,
                'provider_user_id' => $socialUser->getId(),
                'email' => $socialUser->getEmail(),
                'token' => $socialUser->token,
                'refresh_token' => $socialUser->refreshToken,
            ]);

            $name = $socialUser->getName() ?? '';
            $parts = explode(' ', $name, 2);
            MemberDetail::create([
                'user_id' => $user->id,
                'first_name' => $parts[0] ?? '',
                'last_name' => $parts[1] ?? '',
            ]);

            return $user;
        });

        Auth::login($user, true);

        return redirect()->route('profile.show')->with('success', __('Welcome! Please complete your profile.'));
    }

    /** After password login, confirm and apply a pending social link. */
    public function confirmLink(Request $request)
    {
        $pending = session('pending_social_link');

        if (! $pending || $pending['user_id'] !== auth()->id()) {
            return redirect()->route('profile.show');
        }

        DB::transaction(function () use ($pending) {
            UserSocialAccount::create([
                'user_id' => $pending['user_id'],
                'provider' => $pending['provider'],
                'provider_user_id' => $pending['provider_user_id'],
                'email' => $pending['email'],
                'token' => decrypt($pending['token']),
                'refresh_token' => decrypt($pending['refresh_token']),
            ]);

            AuditLog::create([
                'user_id' => $pending['user_id'],
                'action' => 'sso_linked',
                'model_type' => UserSocialAccount::class,
                'model_id' => $pending['user_id'],
                'new_values' => ['provider' => $pending['provider'], 'email' => $pending['email']],
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'created_at' => now(),
            ]);
        });

        session()->forget('pending_social_link');

        return redirect()->route('profile.show')->with('success',
            __(':provider account linked successfully.', ['provider' => ucfirst($pending['provider'])])
        );
    }

    /** Dismiss a pending social link without applying it. */
    public function dismissLink()
    {
        session()->forget('pending_social_link');

        return redirect()->route('profile.show');
    }
}

```

### File: app/Http/Controllers/Auth/EuLoginController.php
```php
<?php

/**
 * EU Login (ECAS) authentication via CAS protocol.
 *
 * EU Login is the European Commission's authentication service.
 * In simple CAS mode, no client registration or API keys are needed.
 * The user is redirected to EU Login, authenticates, and a ticket
 * is returned and validated server-side.
 *
 * @author ClubCEP.eu
 */

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\MemberDetail;
use App\Models\Role;
use App\Models\User;
use App\Models\UserEmail;
use App\Models\UserSocialAccount;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class EuLoginController extends Controller
{
    private function initCas(): void
    {
        if (! \phpCAS::isInitialized()) {
            \phpCAS::client(
                CAS_VERSION_3_0,
                'ecas.ec.europa.eu',
                443,
                '/cas',
                config('app.url')
            );
            // EU Login requires laxValidate for external (non-Commission) users
            \phpCAS::setServerServiceValidateURL('https://ecas.ec.europa.eu/cas/laxValidate');
            \phpCAS::setNoCasServerValidation();
        }
    }

    public function redirect()
    {
        $this->initCas();
        \phpCAS::setFixedServiceURL(route('auth.eulogin.callback'));
        \phpCAS::forceAuthentication();
    }

    public function callback()
    {
        $this->initCas();
        \phpCAS::setFixedServiceURL(route('auth.eulogin.callback'));
        \phpCAS::forceAuthentication();

        $username = \phpCAS::getUser();
        $attributes = \phpCAS::getAttributes();

        $email = $attributes['email'] ?? $attributes['mail'] ?? ($username.'@ec.europa.eu');
        $firstName = $attributes['givenName'] ?? $attributes['firstname'] ?? '';
        $lastName = $attributes['sn'] ?? $attributes['lastname'] ?? '';

        // 1. Existing social link
        $social = UserSocialAccount::where('provider', 'eulogin')
            ->where('provider_user_id', $username)
            ->first();

        if ($social) {
            Auth::login($social->user, true);

            return redirect()->intended(route('profile.show'));
        }

        // 2. Email matches existing account — auto-link (EU Login is trusted)
        $user = User::where('primary_email', $email)->first();

        if ($user) {
            UserSocialAccount::create([
                'user_id' => $user->id,
                'provider' => 'eulogin',
                'provider_user_id' => $username,
                'email' => $email,
            ]);
            Auth::login($user, true);

            return redirect()->intended(route('profile.show'));
        }

        // 3. New user
        $user = DB::transaction(function () use ($username, $email, $firstName, $lastName) {
            $user = User::create([
                'primary_email' => $email,
                'role_id' => Role::where('slug', 'member')->value('id'),
                'email_verified_at' => now(),
            ]);

            UserEmail::create([
                'user_id' => $user->id,
                'email' => $email,
                'is_primary' => true,
                'is_verified' => true,
                'label' => 'eulogin',
            ]);

            UserSocialAccount::create([
                'user_id' => $user->id,
                'provider' => 'eulogin',
                'provider_user_id' => $username,
                'email' => $email,
            ]);

            MemberDetail::create([
                'user_id' => $user->id,
                'first_name' => $firstName,
                'last_name' => $lastName,
            ]);

            return $user;
        });

        Auth::login($user, true);

        return redirect()->route('profile.show')->with('success', __('Welcome! Please complete your profile.'));
    }
}

```

### File: app/Http/Controllers/Controller.php
```php
<?php

namespace App\Http\Controllers;

abstract class Controller
{
    //
}

```

### File: app/Http/Controllers/ContactController.php
```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\ContactFormRequest;
use App\Models\ThemeSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Mail;

class ContactController extends Controller
{
    public function send(ContactFormRequest $request): RedirectResponse
    {
        // Honeypot check
        if ($request->filled('website')) {
            return back()->with('success', __('Message sent. Thank you!'));
        }

        $data = $request->validated();
        $to = ThemeSetting::get('club_email', config('mail.from.address'));

        Mail::raw(
            "From: {$data['name']} <{$data['email']}>\n\n{$data['message']}",
            fn ($msg) => $msg->to($to)
                ->replyTo($data['email'], $data['name'])
                ->subject("[Contact] {$data['subject']}")
        );

        return back()->with('success', __('Message sent. Thank you!'));
    }
}

```

### File: app/Http/Controllers/GdprController.php
```php
<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\GdprConsent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class GdprController extends Controller
{
    public function consents()
    {
        $consents = auth()->user()->gdprConsents()->get()->keyBy('consent_type');

        return view('gdpr.consents', compact('consents'));
    }

    public function updateConsent(Request $request)
    {
        $request->validate(['consent_type' => 'required|in:data_processing,marketing,photo_publication']);
        $granted = $request->boolean('granted');

        GdprConsent::updateOrCreate(
            ['user_id' => auth()->id(), 'consent_type' => $request->consent_type],
            [
                'granted' => $granted,
                'granted_at' => $granted ? now() : null,
                'revoked_at' => ! $granted ? now() : null,
            ]
        );

        return back()->with('success', __('Consent updated.'));
    }

    public function exportData()
    {
        $user = auth()->user();
        $user->load(['detail', 'emails', 'licences', 'documents', 'gdprConsents', 'eventRegistrations.event', 'paymentsExpected']);

        $data = [
            'user' => $user->only(['id', 'username', 'primary_email', 'created_at']),
            'detail' => $user->detail?->toArray(),
            'emails' => $user->emails->toArray(),
            'licences' => $user->licences->toArray(),
            'documents' => $user->documents->map(fn ($d) => $d->only(['category', 'original_filename', 'date_established', 'created_at']))->toArray(),
            'consents' => $user->gdprConsents->toArray(),
            'event_registrations' => $user->eventRegistrations->map(fn ($r) => [
                'event' => $r->event?->title,
                'event_date' => $r->event?->event_date?->format('Y-m-d'),
                'status' => $r->status,
                'registered_at' => $r->created_at?->toIso8601String(),
            ])->toArray(),
            'payments' => $user->paymentsExpected->map(fn ($p) => $p->only(['type', 'season_year', 'amount_due', 'amount_paid', 'status', 'communication']))->toArray(),
            'exported_at' => now()->toIso8601String(),
        ];

        $filename = "gdpr-export-{$user->id}-".now()->format('Ymd').'.json';

        return response()->json($data)->header('Content-Disposition', "attachment; filename={$filename}");
    }

    public function requestErasure()
    {
        return view('gdpr.erasure-confirm');
    }

    public function confirmErasure(Request $request)
    {
        $request->validate([
            'confirm' => 'required|accepted',
            'password' => 'required|current_password',
        ]);
        $user = auth()->user();

        // Delete documents
        foreach ($user->documents as $doc) {
            Storage::disk('local')->delete($doc->file_path);
        }
        $user->documents()->delete();

        // Delete avatar
        if ($user->detail?->avatar_path) {
            Storage::disk('public')->delete($user->detail->avatar_path);
        }

        // Anonymize
        $user->detail?->update([
            'first_name' => 'ERASED', 'last_name' => 'ERASED', 'birth_name' => null,
            'phone_private' => null, 'phone_office' => null, 'phone_mobile' => null,
            'date_of_birth' => null, 'place_of_birth' => null,
            'address_line1' => null, 'address_line2' => null, 'city' => null, 'postal_code' => null,
            'emergency_contact_name' => null, 'emergency_contact_phone' => null,
            'avatar_path' => null,
        ]);

        $user->update(['primary_email' => "erased-{$user->id}@erased.local", 'password' => null, 'username' => null]);
        $user->emails()->delete();
        $user->socialAccounts()->delete();

        AuditLog::create([
            'user_id' => $user->id, 'action' => 'gdpr_erasure',
            'model_type' => 'App\\Models\\User', 'model_id' => $user->id,
            'new_values' => ['erased_at' => now()->toIso8601String()],
            'ip_address' => $request->ip(), 'user_agent' => $request->userAgent(),
        ]);

        auth()->logout();

        return redirect('/')->with('success', __('Your data has been erased.'));
    }
}

```

### File: app/Http/Controllers/HomepageLayoutController.php
```php
<?php

/**
 * Homepage layout configuration and widget rendering.
 *
 * The homepage is composed of draggable widgets whose order and settings
 * are stored as JSON in theme_settings. Bureau admins can reorder,
 * show/hide, and configure widgets via an inline edit mode.
 *
 * @author ClubCEP.eu
 */

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Event;
use App\Models\EventPhoto;
use App\Models\Link;
use App\Models\ThemeSetting;
use App\Models\User;
use Illuminate\Http\Request;

class HomepageLayoutController extends Controller
{
    /** Default layout when none is saved. */
    public static function defaultLayout(): array
    {
        return [
            ['type' => 'hero', 'enabled' => true, 'zone' => 'top', 'visibility' => 'public', 'config' => ['height' => '350px', 'title' => ThemeSetting::get('club_full_name', config('app.name')), 'subtitle' => 'Dive with us in Luxembourg 🤿']],
            ['type' => 'welcome', 'enabled' => true, 'zone' => 'main', 'visibility' => 'public', 'config' => ['text' => 'Welcome to DivingClub']],
            ['type' => 'articles', 'enabled' => true, 'zone' => 'main', 'visibility' => 'public', 'config' => ['limit' => 10]],
            ['type' => 'upcoming_events', 'enabled' => false, 'zone' => 'main', 'visibility' => 'members', 'config' => ['limit' => 5]],
            ['type' => 'quick_links', 'enabled' => true, 'zone' => 'sidebar', 'visibility' => 'public', 'config' => []],
            ['type' => 'photos', 'enabled' => true, 'zone' => 'sidebar', 'visibility' => 'public', 'config' => ['count' => 8]],
            ['type' => 'custom_html', 'enabled' => false, 'zone' => 'sidebar', 'visibility' => 'public', 'config' => ['html' => '']],
        ];
    }

    /** Widget type metadata. */
    public static function widgetTypes(): array
    {
        return [
            'hero' => ['icon' => '🖼️', 'label' => 'Hero Slideshow', 'zones' => ['top']],
            'welcome' => ['icon' => '👋', 'label' => 'Welcome Text', 'zones' => ['main', 'top']],
            'articles' => ['icon' => '📰', 'label' => 'Article Stream', 'zones' => ['main']],
            'upcoming_events' => ['icon' => '📅', 'label' => 'Upcoming Events', 'zones' => ['main', 'sidebar']],
            'quick_links' => ['icon' => '🔗', 'label' => 'Quick Links', 'zones' => ['sidebar']],
            'photos' => ['icon' => '📸', 'label' => 'Photo Gallery', 'zones' => ['sidebar', 'main']],
            'custom_html' => ['icon' => '✏️', 'label' => 'Custom HTML', 'zones' => ['main', 'sidebar', 'top']],
        ];
    }

    /** Get the saved layout or default. */
    public static function getLayout(): array
    {
        $json = ThemeSetting::get('homepage_layout');

        return $json ? json_decode($json, true) : self::defaultLayout();
    }

    /** Save layout via AJAX. */
    public function saveLayout(Request $request)
    {
        abort_unless(auth()->user()?->isBureauMaster(), 403);

        $layout = $request->input('layout');
        if (! is_array($layout)) {
            return response()->json(['error' => 'Invalid layout'], 422);
        }

        ThemeSetting::set('homepage_layout', json_encode($layout));

        return response()->json(['ok' => true]);
    }

    /** Check if a widget is visible to the current user. */
    public static function isVisibleTo(array $widget, ?User $user): bool
    {
        $vis = $widget['visibility'] ?? 'public';

        return match ($vis) {
            'public' => true,
            'members' => (bool) $user,
            'instructors' => $user && ($user->isBureau() || $user->hasRole('instructor')),
            'bureau' => $user && $user->isBureau(),
            default => true,
        };
    }

    /** Load widget data based on type. */
    public static function loadWidgetData(array $widget): array
    {
        return match ($widget['type']) {
            'hero' => ['photos' => auth()->check()
                ? EventPhoto::randomForMembers($widget['config']['count'] ?? 8)->get()
                : EventPhoto::randomPublic($widget['config']['count'] ?? 8)->get()],
            'articles' => ['articles' => Article::active()->where('is_public', true)
                ->where('article_type', '!=', 'classified')->where('sort_order', '>=', 0)
                ->with('author.detail')->orderByDesc('created_at')
                ->limit($widget['config']['limit'] ?? 10)->get()],
            'quick_links' => ['links' => Link::where('is_public', true)->orderBy('sort_order')->get()],
            'photos' => ['photos' => auth()->check()
                ? EventPhoto::randomForMembers($widget['config']['count'] ?? 8)->get()
                : EventPhoto::randomPublic($widget['config']['count'] ?? 8)->get()],
            'upcoming_events' => ['events' => auth()->check()
                ? Event::where('event_date', '>=', now())
                    ->orderBy('event_date')->limit($widget['config']['limit'] ?? 5)->get()
                : collect()],
            default => [],
        };
    }
}

```

### File: app/Http/Controllers/CalendarFeedController.php
```php
<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Carbon\Carbon;
use Illuminate\Http\Response;

class CalendarFeedController extends Controller
{
    public function ical(): Response
    {
        $events = Event::where('status', '!=', 'cancelled')
            ->where('event_date', '>=', now()->subMonths(3))
            ->withCount('confirmedRegistrations')
            ->orderBy('event_date')
            ->get();

        $clubName = config('app.name', 'DivingClub');
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//DivingClub-Manager//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'X-WR-CALNAME:'.$clubName,
        ];

        foreach ($events as $event) {
            $dtStart = $this->formatDt($event->event_date, $event->event_time);
            $dtEnd = $this->formatDt(
                $event->end_date ?? $event->event_date,
                $event->end_time ?? $event->event_time
            );

            $lines[] = 'BEGIN:VEVENT';
            $lines[] = 'UID:event-'.$event->id.'@'.parse_url(config('app.url'), PHP_URL_HOST);
            $lines[] = 'DTSTART:'.$dtStart;
            $lines[] = 'DTEND:'.$dtEnd;
            $lines[] = 'SUMMARY:'.$this->escape($event->title);
            if ($event->location) {
                $lines[] = 'LOCATION:'.$this->escape($event->location);
            }
            if ($event->description) {
                $desc = strip_tags($event->description);
            } else {
                $desc = '';
            }
            $attendance = $event->confirmed_registrations_count.($event->max_participants ? '/'.$event->max_participants : '').' registered';
            $desc = $attendance.($desc ? '\n'.$desc : '');
            $lines[] = 'DESCRIPTION:'.$this->escape($desc);
            $lines[] = 'URL:'.route('events.show', $event);
            $lines[] = 'DTSTAMP:'.$event->updated_at->format('Ymd\THis\Z');
            $lines[] = 'END:VEVENT';
        }

        $lines[] = 'END:VCALENDAR';

        return response(implode("\r\n", $lines), 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'inline; filename="calendar.ics"',
        ]);
    }

    private function formatDt($date, ?string $time): string
    {
        $dateStr = $date instanceof Carbon ? $date->format('Y-m-d') : (string) $date;
        $dt = $time ? "{$dateStr} {$time}" : $dateStr;

        return Carbon::parse($dt)->format('Ymd\THis');
    }

    private function escape(string $text): string
    {
        return str_replace(["\n", ',', ';'], ['\\n', '\\,', '\\;'], $text);
    }
}

```

### File: app/Http/Controllers/PushSubscriptionController.php
```php
<?php

namespace App\Http\Controllers;

use App\Models\PushSubscription;
use Illuminate\Http\Request;

class PushSubscriptionController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'endpoint' => 'required|url',
            'keys.p256dh' => 'required|string',
            'keys.auth' => 'required|string',
        ]);

        PushSubscription::updateOrCreate(
            ['endpoint' => $data['endpoint']],
            [
                'user_id' => auth()->id(),
                'p256dh' => $data['keys']['p256dh'],
                'auth' => $data['keys']['auth'],
            ]
        );

        return response()->json(['ok' => true]);
    }

    public function destroy(Request $request)
    {
        PushSubscription::where('user_id', auth()->id())
            ->where('endpoint', $request->input('endpoint'))
            ->delete();

        return response()->json(['ok' => true]);
    }
}

```

### File: app/Http/Controllers/ProfileController.php
```php
<?php

namespace App\Http\Controllers;

use App\Helpers\IconHelper;
use App\Http\Requests\UpdateProfileLanguageRequest;
use App\Models\Document;
use App\Models\MemberLicence;
use App\Models\MemberStatus;
use App\Models\User;
use App\Models\UserEmail;
use App\Services\MedicalComplianceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProfileController extends Controller
{
    public function uploadAvatar(Request $request, ?User $user = null)
    {
        $viewer = auth()->user();
        $target = $user ?? $viewer;
        if ($target->id !== $viewer->id && ! $viewer->isBureauMaster()) {
            abort(403);
        }

        $request->validate(['avatar' => 'required|image|mimes:jpg,jpeg,png,webp|max:5120']);
        $path = $request->file('avatar')->store('avatars/'.$target->id, 'public');

        // Delete old avatar
        $old = $target->detail?->avatar_path;
        if ($old) {
            Storage::disk('public')->delete($old);
        }

        $target->detail()->updateOrCreate(['user_id' => $target->id], ['avatar_path' => $path]);

        return back()->with('success', __('Photo updated.'));
    }

    public function deleteAvatar(?User $user = null)
    {
        $viewer = auth()->user();
        $target = $user ?? $viewer;
        if ($target->id !== $viewer->id && ! $viewer->isBureauMaster()) {
            abort(403);
        }

        if ($target->detail?->avatar_path) {
            Storage::disk('public')->delete($target->detail->avatar_path);
            $target->detail->update(['avatar_path' => null]);
        }

        return back()->with('success', __('Photo removed.'));
    }

    public function addCertification(Request $request)
    {
        $request->validate(['certification_level_id' => 'required|exists:certification_levels,id', 'obtained_date' => 'nullable|date']);
        $user = auth()->user();
        $user->certificationLevels()->syncWithoutDetaching([
            $request->certification_level_id => ['obtained_date' => $request->obtained_date, 'display_priority' => 0],
        ]);

        return back()->withInput(['tab' => 'diving'])->with('success', __('Certification added.'));
    }

    public function updateCertification(Request $request, int $certLevel)
    {
        $request->validate(['obtained_date' => 'nullable|date']);
        \DB::table('user_certification_levels')
            ->where('user_id', auth()->id())
            ->where('certification_level_id', $certLevel)
            ->update(['obtained_date' => $request->obtained_date, 'updated_at' => now()]);

        return back()->withInput(['tab' => 'diving'])->with('success', __('Certification updated.'));
    }

    public function setPrimaryCert(int $certLevel)
    {
        $user = auth()->user();
        \DB::table('user_certification_levels')->where('user_id', $user->id)->update(['is_primary' => false]);
        \DB::table('user_certification_levels')->where('user_id', $user->id)->where('certification_level_id', $certLevel)->update(['is_primary' => true, 'display_priority' => \DB::raw('display_priority + 1')]);

        return back()->withInput(['tab' => 'diving'])->with('success', __('Primary certification updated.'));
    }

    public function removeCertification(int $certLevel)
    {
        auth()->user()->certificationLevels()->detach($certLevel);

        return back()->withInput(['tab' => 'diving'])->with('success', __('Certification removed.'));
    }

    public function show(Request $request, ?User $user = null)
    {
        $viewer = auth()->user();
        $target = $user ?? $viewer;

        if ($target->id !== $viewer->id && ! $viewer->isBureauMaster()) {
            abort(403);
        }

        $target->load(['detail', 'emails', 'licences.federation', 'documents']);
        $statuses = MemberStatus::orderBy('name')->get();
        $tab = $request->get('tab', 'info');
        $medicalStatus = app(MedicalComplianceService::class)->getStatus($target);

        return view('profile.show', compact('target', 'viewer', 'statuses', 'tab', 'medicalStatus'));
    }

    public function updateInfo(Request $request, ?User $user = null)
    {
        $viewer = auth()->user();
        $target = $user ?? $viewer;

        if ($target->id !== $viewer->id && ! $viewer->isBureauMaster()) {
            abort(403);
        }

        $rules = [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'username' => 'nullable|string|max:255|unique:users,username,'.$target->id,
            'nationality' => 'nullable|string|max:100',
            'phone_private' => 'nullable|string|max:50',
            'phone_office' => 'nullable|string|max:50',
            'phone_mobile' => 'nullable|string|max:50',
            'sex' => 'required|in:M,F,X',
            'club_email' => 'nullable|email|max:255',
        ];

        // Members can change their own status; bureau_master can change anyone's
        if ($viewer->id === $target->id || $viewer->isBureauMaster()) {
            $rules['status_id'] = 'nullable|exists:member_statuses,id';
        }

        // Only bureau_master can change these
        if ($viewer->isBureauMaster()) {
            $rules['bureau_member'] = 'nullable|boolean';
            $rules['active_instructor'] = 'nullable|boolean';
            $rules['adhesion_year'] = 'nullable|integer|min:1900|max:'.date('Y');
            $rules['cotisation_years'] = 'nullable|array';
            $rules['cotisation_years.*'] = 'integer|min:1900|max:'.(date('Y') + 1);
        }

        $validated = $request->validate($rules);

        // Block member from changing bureau-only fields
        if (! $viewer->isBureauMaster()) {
            if ($request->has('bureau_member') || $request->has('active_instructor')) {
                abort(403);
            }
        }

        DB::transaction(function () use ($target, $validated, $viewer) {
            $target->update(array_filter([
                'username' => $validated['username'] ?? null,
            ], fn ($v) => $v !== null));

            // Status change — allowed for self or bureau_master
            if (isset($validated['status_id'])) {
                $target->update(['status_id' => $validated['status_id']]);
            }

            $detailData = collect($validated)->except(['username', 'status_id', 'cotisation_years'])->toArray();

            if ($viewer->isBureauMaster()) {
                $detailData['bureau_member'] = $validated['bureau_member'] ?? false;
                $detailData['active_instructor'] = $validated['active_instructor'] ?? false;
                $detailData['adhesion_year'] = $validated['adhesion_year'] ?? null;
                if (isset($validated['cotisation_years'])) {
                    $detailData['cotisation_years'] = array_map('strval', $validated['cotisation_years']);
                }
            }

            $target->detail()->updateOrCreate(['user_id' => $target->id], $detailData);
        });

        return back()->with('success', __('Profile updated.'))->withInput(['tab' => 'info']);
    }

    public function updatePrivate(Request $request, ?User $user = null)
    {
        $viewer = auth()->user();
        $target = $user ?? $viewer;

        if ($target->id !== $viewer->id && ! $viewer->isBureauMaster()) {
            abort(403);
        }

        $validated = $request->validate([
            'date_of_birth' => 'nullable|date',
            'place_of_birth' => 'nullable|string|max:255',
            'address_line1' => 'nullable|string|max:255',
            'address_line2' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'postal_code' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:100',
            'iban' => 'nullable|string|max:34',
            'emergency_contact_name' => 'nullable|string|max:255',
            'emergency_contact_phone' => 'nullable|string|max:50',
            'emergency_contact_relationship' => 'nullable|string|max:100',
            'brevet_date' => 'nullable|date',
        ]);

        $target->detail()->updateOrCreate(['user_id' => $target->id], $validated);

        return back()->with('success', __('Private info updated.'))->withInput(['tab' => 'private']);
    }

    /** Set the FFESSM InfoLicencié verification key — member (own licence) or bureau. */
    public function updateFederationKey(Request $request, MemberLicence $licence)
    {
        $user = auth()->user();
        if ($licence->user_id !== $user->id && ! $user->isBureauMaster()) {
            abort(403);
        }
        $request->validate(['federation_key' => 'nullable|string|max:20']);
        $licence->update(['federation_key' => strtoupper(trim($request->federation_key))]);

        return back()->with('success', __('Federation key updated.'))->withInput(['tab' => 'renewal']);
    }

    public function updateDiving(Request $request, ?User $user = null)
    {
        $viewer = auth()->user();
        $target = $user ?? $viewer;

        if ($target->id !== $viewer->id && ! $viewer->isBureauMaster()) {
            abort(403);
        }

        // Instructor bio sub-form
        if ($request->input('tab') === 'instructor_bio') {
            $request->validate([
                'instructor_bio' => 'nullable|string|max:2000',
                'instructor_specialties' => 'nullable|string|max:1000',
                'instructor_motivation' => 'nullable|string|max:1000',
                'show_on_public_site' => 'boolean',
            ]);
            $target->detail()->updateOrCreate(['user_id' => $target->id], $request->only('instructor_bio', 'instructor_specialties', 'instructor_motivation', 'show_on_public_site'));

            return back()->with('success', __('Instructor profile updated.'))->withInput(['tab' => 'diving']);
        }

        $validated = $request->validate([
            'dive_count' => 'nullable|integer|min:0',
            'total_dives' => 'nullable|integer|min:0',
            'last_dive_date' => 'nullable|date',
            'air_consumption' => 'nullable|numeric|min:0|max:1',
            'ease_level' => 'nullable|numeric|min:0|max:1',
            'primary_intent' => 'nullable|string|in:exploration,photography,training,deep,wreck,night,drift',
            'is_photographer' => 'nullable|boolean',
            'certification_level' => 'nullable|string|max:50',
            'other_certifications' => 'nullable|string',
            'training_enrollments' => 'nullable|string',
        ]);

        $validated['other_certifications'] = $validated['other_certifications']
            ? array_map('trim', explode(',', $validated['other_certifications'])) : [];
        $validated['training_enrollments'] = $validated['training_enrollments']
            ? array_map('trim', explode(',', $validated['training_enrollments'])) : [];

        $target->detail()->updateOrCreate(['user_id' => $target->id], $validated);

        return back()->with('success', __('Diving info updated.'))->withInput(['tab' => 'diving']);
    }

    public function updateLanguage(UpdateProfileLanguageRequest $request, ?User $user = null)
    {
        $viewer = auth()->user();
        $target = $user ?? $viewer;

        if ($target->id !== $viewer->id && ! $viewer->isBureauMaster()) {
            abort(403);
        }

        $validated = $request->validated();

        // Convert empty string to null (use club default)
        $validated['show_icons'] = $validated['show_icons'] === '' || $validated['show_icons'] === null
            ? null : (int) $validated['show_icons'];

        $target->detail()->updateOrCreate(['user_id' => $target->id], $validated);
        IconHelper::flush();

        return back()->with('success', __('Language preference updated.'))->withInput(['tab' => 'language']);
    }

    public function uploadDocument(Request $request, ?User $user = null)
    {
        $viewer = auth()->user();
        $target = $user ?? ($request->target_user_id ? User::findOrFail($request->target_user_id) : $viewer);

        if ($target->id !== $viewer->id && ! $viewer->isBureauMaster()) {
            abort(403);
        }

        $request->validate([
            'file' => 'required|file|mimetypes:application/pdf,image/jpeg,image/png|max:10240',
            'category' => 'required|string|in:certification,medical,insurance,other',
            'date_established' => 'nullable|date',
            'cert_type' => 'nullable|string|max:30',
        ]);

        $file = $request->file('file');
        $path = $file->store('documents/'.$target->id, 'local');

        $doc = Document::create([
            'user_id' => $target->id,
            'category' => $request->category,
            'cert_type' => $request->cert_type,
            'file_path' => $path,
            'original_filename' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size_bytes' => $file->getSize(),
            'date_established' => $request->date_established,
            'is_current' => true,
        ]);

        // Evaluate medical compliance rules
        if ($request->category === 'medical') {
            app(MedicalComplianceService::class)->evaluateCertificate($doc);
        }

        // Notify bureau when a medical cert is uploaded
        if ($request->category === 'medical') {
            $bureauUsers = User::whereHas('role', fn ($q) => $q->whereIn('slug', ['bureau_master', 'bureau_technical']))->get();
            foreach ($bureauUsers as $admin) {
                Mail::raw(
                    __(':name uploaded a medical certificate (:type, :date).', [
                        'name' => $target->detail?->first_name.' '.$target->detail?->last_name,
                        'type' => $doc->cert_type ?? 'medical',
                        'date' => $doc->date_established?->format('Y-m-d') ?? '—',
                    ]),
                    fn ($m) => $m->to($admin->primary_email)->subject(__('Medical certificate uploaded'))
                );
            }
        }

        return back()->with('success', __('Document uploaded.'));
    }

    public function downloadDocument(Document $document)
    {
        $viewer = auth()->user();
        if ($document->user_id !== $viewer->id && ! $viewer->isBureauMaster()) {
            abort(403);
        }

        return Storage::disk('local')->download($document->file_path, $document->original_filename);
    }

    // Email management
    public function addEmail(Request $request)
    {
        $user = auth()->user();

        if ($user->emails()->count() >= 5) {
            return back()->with('error', __('Maximum of 5 email addresses allowed.'));
        }

        $validated = $request->validate([
            'email' => 'required|email|unique:user_emails,email',
            'label' => 'nullable|string|max:50',
        ]);

        UserEmail::create([
            'user_id' => $user->id,
            'email' => $validated['email'],
            'is_primary' => false,
            'is_verified' => false,
            'label' => $validated['label'],
            'verification_token' => Str::random(64),
            'verification_sent_at' => now(),
        ]);

        // TODO: Send verification email in Phase 6

        return back()->with('success', __('Email added. Please verify it.'));
    }

    public function setPrimaryEmail(UserEmail $email)
    {
        $user = auth()->user();
        if ($email->user_id !== $user->id && ! $user->isBureauMaster()) {
            abort(403);
        }
        if (! $email->is_verified) {
            return back()->with('error', __('Only verified emails can be set as primary.'));
        }

        DB::transaction(function () use ($email) {
            UserEmail::where('user_id', $email->user_id)->update(['is_primary' => false]);
            $email->update(['is_primary' => true]);
            User::where('id', $email->user_id)->update(['primary_email' => $email->email]);
        });

        return back()->with('success', __('Primary email updated.'));
    }

    public function deleteEmail(UserEmail $email)
    {
        $user = auth()->user();
        if ($email->user_id !== $user->id && ! $user->isBureauMaster()) {
            abort(403);
        }
        if ($email->is_primary) {
            return back()->with('error', __('Cannot delete primary email. Set another as primary first.'));
        }

        $email->delete();

        return back()->with('success', __('Email removed.'));
    }

    public function verifyCertificate(Request $request, Document $document)
    {
        abort_unless(auth()->user()->isBureauMaster(), 403);

        $data = ['is_verified' => true, 'verified_by' => auth()->id(), 'verified_at' => now()];

        if ($request->filled('date_established')) {
            $data['date_established'] = $request->date('date_established');
        }

        if ($request->filled('cert_type')) {
            $data['cert_type'] = $request->input('cert_type');
        }

        $document->update($data);

        if ($document->category === 'medical') {
            app(MedicalComplianceService::class)->evaluateCertificate($document);
        }

        return back()->with('success', __('Certificate verified.'));
    }
}

```


## Directory: app/Http/Requests

### File: app/Http/Requests/StoreMembershipFeeRequest.php
```php
<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreMembershipFeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->isBureauMaster();
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'season_year' => 'required|string|max:10',
            'status_id' => 'required|exists:member_statuses,id',
            'amount' => 'required|numeric|min:0',
            'label' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ];
    }
}

```

### File: app/Http/Requests/StoreDiveGroupRuleRequest.php
```php
<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreDiveGroupRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->isBureauMaster();
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'federation_id' => 'required|exists:federations,id',
            'zone' => 'required|string|max:50',
            'min_divers' => 'required|integer|min:1|max:10',
            'max_divers' => 'required|integer|min:1|max:10|gte:min_divers',
            'guide_required' => 'boolean',
            'guide_conditions' => 'nullable|string|max:500',
            'diver_conditions' => 'nullable|string|max:500',
            'notes' => 'nullable|string|max:1000',
        ];
    }
}

```

### File: app/Http/Requests/RegisterUserRequest.php
```php
<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:user_emails,email|unique:users,primary_email',
            'password' => ['required', 'confirmed', Password::defaults()],
            'website' => 'size:0',
            '_ts' => 'required|integer',
        ];
    }
}

```

### File: app/Http/Requests/UpdateProfileInfoRequest.php
```php
<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileInfoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'date_of_birth' => 'nullable|date',
            'place_of_birth' => 'nullable|string|max:255',
            'address_line1' => 'nullable|string|max:255',
            'address_line2' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'postal_code' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:100',
            'iban' => 'nullable|string|max:34',
            'emergency_contact_name' => 'nullable|string|max:255',
            'emergency_contact_phone' => 'nullable|string|max:50',
            'emergency_contact_relationship' => 'nullable|string|max:100',
            'brevet_date' => 'nullable|date',
        ];
    }
}

```

### File: app/Http/Requests/StoreClassifiedRequest.php
```php
<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreClassifiedRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'body' => 'required|string|max:5000',
            'category' => 'required|string|in:sale,wanted,buddy,other',
            'price' => 'nullable|numeric|min:0',
            'featured_image' => 'nullable|image|max:5120',
        ];
    }
}

```

### File: app/Http/Requests/ContactFormRequest.php
```php
<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ContactFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'subject' => 'required|string|max:255',
            'message' => 'required|string|max:5000',
        ];
    }
}

```

### File: app/Http/Requests/StoreArticleRequest.php
```php
<?php

namespace App\Http\Requests;

use App\Models\Article;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreArticleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->isBureau();
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'body' => 'required|string',
            'article_type' => 'required|in:'.implode(',', array_keys(Article::TYPES)),
            'is_published' => 'boolean',
            'is_public' => 'boolean',
            'featured_image' => 'nullable|image|max:5120',
            'vote_id' => 'nullable|exists:votes,id',
            'gallery.*' => 'image|max:5120',
            'gallery_captions.*' => 'nullable|string|max:255',
            'gallery_layouts.*' => 'nullable|in:full,half,third',
            'delete_images' => 'nullable|array',
            'delete_images.*' => 'exists:article_images,id',
        ];
    }
}

```

### File: app/Http/Requests/StoreSeasonRequest.php
```php
<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreSeasonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->isBureauMaster();
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'year' => 'required|integer|min:2000',
            'name' => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'clone_from' => 'nullable|exists:seasons,id',
        ];
    }
}

```

### File: app/Http/Requests/StoreVoteRequest.php
```php
<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreVoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->isBureauMaster();
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:5000',
            'type' => 'required|string|in:simple,election',
            'max_choices' => 'required|integer|min:1',
            'opens_at' => 'nullable|date',
            'closes_at' => 'nullable|date|after:opens_at',
            'options' => 'required|array|min:2',
            'options.*' => 'required|string|max:255',
        ];
    }
}

```

### File: app/Http/Requests/StoreMaintenanceRuleRequest.php
```php
<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreMaintenanceRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->isBureauMaster();
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'equipment_type' => 'required|string|max:100',
            'maintenance_name' => 'required|string|max:255',
            'interval_months' => 'required|integer|min:1',
            'is_mandatory' => 'boolean',
            'regulation_reference' => 'nullable|string|max:255',
        ];
    }
}

```

### File: app/Http/Requests/SendEmailRequest.php
```php
<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SendEmailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->isBureauMaster();
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'template_id' => 'required|exists:email_templates,id',
            'group' => 'required|in:all,active,instructors,bureau,expiring_certs,unpaid,event',
            'event_id' => 'nullable|required_if:group,event|exists:events,id',
        ];
    }
}

```

### File: app/Http/Requests/StoreEventRequest.php
```php
<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->isBureau() || auth()->user()?->hasRole('instructor');
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'color_hex' => 'nullable|string|max:7',
            'event_type' => 'required|in:pool,dive,training,theory,social',
            'event_date' => 'required|date',
            'event_time' => 'nullable|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i',
            'end_date' => 'nullable|date|after_or_equal:event_date',
            'location' => 'nullable|string|max:500',
            'description' => 'nullable|string',
            'responsible_id' => 'nullable|exists:users,id',
            'max_participants' => 'nullable|integer|min:1',
            'waiting_list_enabled' => 'boolean',
            'inscription_open_at' => 'nullable|date',
            'inscriptions_closed' => 'boolean',
            'levels_display' => 'boolean',
            'confirmation_required' => 'boolean',
            'estimated_cost' => 'nullable|numeric|min:0',
            'deposit_1_date' => 'nullable|date',
            'deposit_1_amount' => 'nullable|numeric|min:0',
            'deposit_2_date' => 'nullable|date',
            'deposit_2_amount' => 'nullable|numeric|min:0',
            'deposit_3_date' => 'nullable|date',
            'deposit_3_amount' => 'nullable|numeric|min:0',
            'instructor_id' => 'nullable|exists:users,id',
            'permissions_expire_date' => 'nullable|date',
            'status' => 'nullable|in:scheduled,cancelled,completed',
            'season_id' => 'nullable|exists:seasons,id',
            'dive_site_id' => 'nullable|exists:dive_sites,id',
        ];
    }
}

```

### File: app/Http/Requests/UpdateProfileDivingRequest.php
```php
<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileDivingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'dive_count' => 'nullable|integer|min:0',
            'total_dives' => 'nullable|integer|min:0',
            'last_dive_date' => 'nullable|date',
            'air_consumption' => 'nullable|numeric|min:0|max:1',
            'ease_level' => 'nullable|numeric|min:0|max:1',
            'primary_intent' => 'nullable|string|in:exploration,photography,training,deep,wreck,night,drift',
            'is_photographer' => 'nullable|boolean',
            'certification_level' => 'nullable|string|max:50',
            'other_certifications' => 'nullable|string',
            'training_enrollments' => 'nullable|string',
        ];
    }
}

```

### File: app/Http/Requests/StoreMedicalRuleRequest.php
```php
<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreMedicalRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->isBureauMaster();
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'federation_id' => 'required|exists:federations,id',
            'age_bracket_low' => 'required|integer|min:0',
            'age_bracket_high' => 'required|integer|min:0|gte:age_bracket_low',
            'cert_type' => 'required|string|in:gp,ent,cardio,ophthalmologist,other',
            'validity_months' => 'required|integer|min:1',
        ];
    }
}

```

### File: app/Http/Requests/StoreDiveSiteRequest.php
```php
<?php

namespace App\Http\Requests;

use App\Models\DiveSite;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreDiveSiteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->isBureauMaster();
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'country' => 'nullable|string|max:100',
            'region' => 'nullable|string|max:100',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'max_depth' => 'nullable|integer|min:1|max:300',
            'water_type' => 'nullable|in:'.implode(',', DiveSite::WATER_TYPES),
            'conditions' => 'nullable|string',
            'marine_life' => 'nullable|string',
            'safety_notes' => 'nullable|string',
            'access_notes' => 'nullable|string',
            'facilities' => 'nullable|string',
            'food_options' => 'nullable|string',
            'nearest_hospital' => 'nullable|string',
            'website_url' => 'nullable|url|max:500',
            'entry_fee' => 'nullable|numeric|min:0',
            'booking_url' => 'nullable|url|max:500',
            'image' => 'nullable|image|max:5120',
            'map_image' => 'nullable|image|max:5120',
            'site_plan' => 'nullable|file|mimes:jpg,jpeg,png,gif,svg,pdf|max:10240',
            'safety_docs_folder' => 'nullable|string|max:255',
            'is_active' => 'boolean',
        ];
    }
}

```

### File: app/Http/Requests/StoreTrialRequest.php
```php
<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreTrialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:30',
            'message' => 'nullable|string|max:2000',
            'preferred_date' => 'nullable|date|after:today',
            'website' => 'nullable|string|max:0',
        ];
    }
}

```

### File: app/Http/Requests/UploadDocumentRequest.php
```php
<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UploadDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'file' => 'required|file|mimetypes:application/pdf,image/jpeg,image/png|max:10240',
            'category' => 'required|string|in:certification,medical,insurance,other',
            'date_established' => 'nullable|date',
        ];
    }
}

```

### File: app/Http/Requests/StoreFederationRequest.php
```php
<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreFederationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->isBureauMaster();
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'acronym' => 'required|string|max:20|unique:federations,acronym'.($this->route('federation') ? ','.$this->route('federation')->id : ''),
            'full_name' => 'required|string|max:255',
        ];
    }
}

```

### File: app/Http/Requests/StoreCommentRequest.php
```php
<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'body' => 'required|string|max:5000',
            'parent_id' => 'nullable|exists:comments,id',
        ];
    }
}

```

### File: app/Http/Requests/StoreEquipmentRequest.php
```php
<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreEquipmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->isBureau();
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'type' => 'required|string|max:100',
            'serial_number' => 'nullable|string|max:100',
            'purchase_date' => 'nullable|date',
            'condition' => 'nullable|string|in:new,good,fair,poor,retired',
            'notes' => 'nullable|string|max:2000',
        ];
    }
}

```

### File: app/Http/Requests/UpdateProfileLanguageRequest.php
```php
<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileLanguageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'preferred_language' => 'required|in:en,fr,de,it,es,pt,nl,pl,ro,cs,el,lb',
            'show_icons' => 'nullable|in:0,1',
        ];
    }
}

```


## Directory: app/Providers

### File: app/Providers/StagingMailServiceProvider.php
```php
<?php

namespace App\Providers;

use App\Models\EmailLog;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class StagingMailServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if (! config('app.staging_mode')) {
            return;
        }

        // Force mail to log driver unless Mailpit is catching it via Postfix relay
        if (! config('app.staging_use_smtp')) {
            config(['mail.default' => 'log']);
        }

        // Capture every outgoing email into email_log
        Event::listen(MessageSending::class, function (MessageSending $event) {
            $message = $event->message;
            $to = collect($message->getTo())->map(fn ($a) => $a->getAddress())->implode(', ');
            $eventId = null;
            foreach ($message->getTo() as $addr) {
                if (preg_match('/^event-(\d+)@/i', $addr->getAddress(), $m)) {
                    $eventId = (int) $m[1];
                    break;
                }
            }

            EmailLog::create([
                'event_id' => $eventId,
                'to_email' => $to,
                'subject' => $message->getSubject() ?? '(no subject)',
                'body' => $message->getHtmlBody() ?? $message->getTextBody() ?? '',
                'from_email' => collect($message->getFrom())->map(fn ($a) => $a->getAddress())->first(),
                'from_name' => collect($message->getFrom())->map(fn ($a) => $a->getName())->first(),
                'status' => 'staging_captured',
            ]);
        });
    }
}

```

### File: app/Providers/ThemeServiceProvider.php
```php
<?php

namespace App\Providers;

use App\Services\ThemeService;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class ThemeServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        View::composer('components.layout', function ($view) {
            $view->with('themeCSS', ThemeService::css());
            $view->with('theme', ThemeService::settings());
        });
    }
}

```

### File: app/Providers/AppServiceProvider.php
```php
<?php

namespace App\Providers;

use App\Auth\DivingClubUserProvider;
use App\Services\LicenseService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * Shares license watermark with all views so PDF/HTML output can
     * display "UNLICENSED" when the installation exceeds the free tier
     * without a valid key. This is a second check point independent of
     * the CheckLicense middleware — removing the middleware alone won't
     * remove the watermark from generated documents.
     */
    public function boot(): void
    {
        // Map 'email' → 'primary_email' for password reset and credential lookups
        Auth::provider('divingclub', fn ($app, $config) => new DivingClubUserProvider($app['hash'], $config['model'])
        );

        View::composer('*', function ($view) {
            if (! $view->offsetExists('licenseWatermark')) {
                $view->with('licenseWatermark', LicenseService::watermark());
            }
        });

        // Intercept all outgoing mail in staging — redirect to a single address
        if (config('app.staging_mode') && $to = config('mail.always_to')) {
            Mail::alwaysTo($to);
        }

        // @icon('🤿') — outputs emoji only when icons are enabled for current user
        Blade::directive('icon', function (string $expression) {
            return "<?php echo \App\Helpers\IconHelper::render({$expression}); ?>";
        });
    }
}

```


## Directory: database/migrations

### File: database/migrations/2026_03_20_191724_add_num_positions_to_votes.php
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('votes', function (Blueprint $table) {
            $table->unsignedTinyInteger('num_positions')->default(1)->after('allow_change');
            $table->unsignedTinyInteger('min_vote_pct')->default(50)->after('num_positions');
        });
    }

    public function down(): void
    {
        Schema::table('votes', function (Blueprint $table) {
            $table->dropColumn(['num_positions', 'min_vote_pct']);
        });
    }
};

```

### File: database/migrations/2026_03_20_191346_add_planning_fields_to_dive_groups.php
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dive_groups', function (Blueprint $table) {
            $table->unsignedSmallInteger('planned_duration')->nullable()->after('planned_depth'); // minutes
            $table->string('gas_mix', 20)->default('air')->after('planned_duration'); // air, nitrox32, nitrox36, trimix, O2
            $table->unsignedTinyInteger('line_number')->nullable()->after('gas_mix'); // 1-4 for fiche de sécurité
            $table->time('planned_entry_time')->nullable()->after('line_number');
            $table->time('planned_exit_time')->nullable()->after('planned_entry_time');
        });
    }

    public function down(): void
    {
        Schema::table('dive_groups', function (Blueprint $table) {
            $table->dropColumn(['planned_duration', 'gas_mix', 'line_number', 'planned_entry_time', 'planned_exit_time']);
        });
    }
};

```

### File: database/migrations/2026_03_19_080000_add_federation_visibility_iban_bank_ref_guest_details.php
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Federation activation: active/recognized/invisible per club
        Schema::table('federations', function (Blueprint $table) {
            $table->enum('visibility', ['active', 'recognized', 'invisible'])->default('active')->after('full_name');
        });

        // IBAN for members (quicker payment/better reconcile)
        Schema::table('member_details', function (Blueprint $table) {
            $table->string('iban', 34)->nullable()->after('country');
        });

        // Bank statement reference stored with payment on reconciliation
        Schema::table('payment_expected', function (Blueprint $table) {
            $table->string('bank_statement_ref', 100)->nullable()->after('reconciled_at');
            $table->date('bank_statement_date')->nullable()->after('bank_statement_ref');
        });

        // More detail on external registrations (federation guests)
        Schema::table('external_registrations', function (Blueprint $table) {
            $table->string('external_member_phone', 30)->nullable()->after('external_member_email');
            $table->string('external_member_federation', 50)->nullable()->after('external_member_phone');
            $table->string('external_member_licence_no', 50)->nullable()->after('external_member_federation');
            $table->text('external_member_emergency_contact')->nullable()->after('external_member_licence_no');
        });
    }

    public function down(): void
    {
        Schema::table('federations', fn (Blueprint $t) => $t->dropColumn('visibility'));
        Schema::table('member_details', fn (Blueprint $t) => $t->dropColumn('iban'));
        Schema::table('payment_expected', fn (Blueprint $t) => $t->dropColumn(['bank_statement_ref', 'bank_statement_date']));
        Schema::table('external_registrations', fn (Blueprint $t) => $t->dropColumn(['external_member_phone', 'external_member_federation', 'external_member_licence_no', 'external_member_emergency_contact']));
    }
};

```

### File: database/migrations/2026_03_17_201204_add_compliance_to_documents.php
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->boolean('is_compliant')->nullable()->after('is_current');
            $table->text('compliance_notes')->nullable()->after('is_compliant');
            $table->date('reminder_30_sent_at')->nullable()->after('compliance_notes');
            $table->date('reminder_15_sent_at')->nullable()->after('reminder_30_sent_at');
            $table->date('reminder_7_sent_at')->nullable()->after('reminder_15_sent_at');
            $table->date('reminder_0_sent_at')->nullable()->after('reminder_7_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn(['is_compliant', 'compliance_notes', 'reminder_30_sent_at', 'reminder_15_sent_at', 'reminder_7_sent_at', 'reminder_0_sent_at']);
        });
    }
};

```

### File: database/migrations/2026_03_20_194613_add_statement_ref_to_bank_transactions.php
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bank_transactions', function (Blueprint $table) {
            $table->string('statement_ref', 100)->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('bank_transactions', function (Blueprint $table) {
            $table->dropColumn('statement_ref');
        });
    }
};

```

### File: database/migrations/2026_03_21_173354_add_show_icons_to_member_details.php
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('member_details', function (Blueprint $table) {
            $table->boolean('show_icons')->nullable()->after('preferred_language');
        });
    }

    public function down(): void
    {
        Schema::table('member_details', function (Blueprint $table) {
            $table->dropColumn('show_icons');
        });
    }
};

```

### File: database/migrations/2026_03_20_080650_add_emergency_fields_to_dive_sites.php
```php
<?php

/**
 * Add emergency-specific fields to dive_sites for fiche de sécurité generation.
 *
 * These fields populate the emergency info block on the FFESSM fiche de sécurité:
 * required safety equipment, emergency phone/VHF, nearest hyperbaric chamber.
 *
 * @author ClubCEP.eu
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dive_sites', function (Blueprint $table) {
            $table->string('emergency_phone')->nullable()->after('nearest_hospital');
            $table->string('vhf_channel')->nullable()->after('emergency_phone');
            $table->text('required_safety_equipment')->nullable()->after('vhf_channel');
            $table->string('nearest_hyperbaric_chamber')->nullable()->after('required_safety_equipment');
            $table->string('hyperbaric_phone')->nullable()->after('nearest_hyperbaric_chamber');
            $table->unsignedSmallInteger('hospital_distance_km')->nullable()->after('hyperbaric_phone');
            $table->unsignedSmallInteger('hyperbaric_distance_km')->nullable()->after('hospital_distance_km');
        });
    }

    public function down(): void
    {
        Schema::table('dive_sites', function (Blueprint $table) {
            $table->dropColumn([
                'emergency_phone', 'vhf_channel', 'required_safety_equipment',
                'nearest_hyperbaric_chamber', 'hyperbaric_phone',
                'hospital_distance_km', 'hyperbaric_distance_km',
            ]);
        });
    }
};

```

### File: database/migrations/2026_03_18_150000_create_instructor_availabilities.php
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('instructor_availabilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('slot')->default('evening'); // morning, afternoon, evening, full_day
            $table->string('note')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'date', 'slot']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instructor_availabilities');
    }
};

```

### File: database/migrations/2026_03_17_200731_add_avatar_to_member_details.php
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('member_details', function (Blueprint $table) {
            $table->string('avatar_path')->nullable()->after('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('member_details', function (Blueprint $table) {
            $table->dropColumn('avatar_path');
        });
    }
};

```

### File: database/migrations/2026_03_18_180000_create_club_partnerships_and_federation.php
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Partner clubs (key exchange)
        Schema::create('club_partnerships', function (Blueprint $table) {
            $table->id();
            $table->string('name');                    // "Club Européen de Plongée"
            $table->string('base_url');                // "https://cep.divingclub.eu"
            $table->string('api_key_id', 64)->unique(); // Our key ID they use to call us
            $table->text('api_secret_hash');            // bcrypt of the shared secret
            $table->text('their_api_key_id')->nullable(); // Key ID we use to call them
            $table->text('their_api_secret')->nullable(); // Encrypted secret for outbound calls
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_sync_at')->nullable();
            $table->timestamps();
        });

        // Events marked as federated (shareable with partner clubs)
        Schema::table('events', function (Blueprint $table) {
            $table->boolean('is_federated')->default(false)->after('status');
            $table->unsignedInteger('external_slots')->default(0)->after('is_federated');
        });

        // External registrations from partner clubs
        Schema::create('external_registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('partnership_id')->constrained('club_partnerships')->cascadeOnDelete();
            $table->string('external_member_name');
            $table->string('external_member_email')->nullable();
            $table->string('external_cert_level')->nullable();  // "LIFRAS P2★" or "FFESSM N2"
            $table->date('external_medical_valid_until')->nullable();
            $table->string('status')->default('pending'); // pending, approved, rejected, cancelled
            $table->text('notes')->nullable();
            $table->string('external_ref')->nullable();   // ID on the remote instance
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('external_registrations');
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['is_federated', 'external_slots']);
        });
        Schema::dropIfExists('club_partnerships');
    }
};

```

### File: database/migrations/2026_03_18_070000_add_comments_and_vote_features.php
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Article comments (threaded)
        Schema::create('article_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('article_comments')->cascadeOnDelete();
            $table->text('body');
            $table->timestamps();
            $table->index(['article_id', 'created_at']);
        });

        // Multi-select ballots: allow multiple endorsements per voter
        // Add unique constraint change: for trip proposals, one user can vote for multiple options
        Schema::table('votes', function (Blueprint $table) {
            $table->boolean('allow_multiple')->default(false)->after('mode');
            $table->boolean('allow_change')->default(true)->after('allow_multiple');
            $table->boolean('is_public')->default(false)->after('allow_change');
        });

        // Article type background colors (admin-configurable)
        // Stored in theme_settings as article_bg_<type> keys — no schema change needed

        // Article gallery layout
        Schema::table('article_images', function (Blueprint $table) {
            $table->string('caption')->nullable()->after('alt_text');
            $table->string('layout_hint', 20)->default('full')->after('caption'); // full, half, third
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('article_comments');
        Schema::table('votes', function (Blueprint $table) {
            $table->dropColumn(['allow_multiple', 'allow_change', 'is_public']);
        });
        Schema::table('article_images', function (Blueprint $table) {
            $table->dropColumn(['caption', 'layout_hint']);
        });
    }
};

```

### File: database/migrations/0001_01_01_000003_create_user_emails_table.php
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_emails', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('email')->unique();
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_verified')->default(false);
            $table->string('label')->nullable();
            $table->string('verification_token')->nullable();
            $table->timestamp('verification_sent_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'is_primary']);
        });

        Schema::create('user_social_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider');
            $table->string('provider_user_id');
            $table->string('email')->nullable();
            $table->text('token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'provider_user_id']);
            $table->index(['user_id', 'provider']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_social_accounts');
        Schema::dropIfExists('user_emails');
    }
};

```

### File: database/migrations/0001_01_01_000005_create_content_tables.php
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('category');
            $table->string('file_path');
            $table->string('original_filename');
            $table->string('mime_type');
            $table->unsignedInteger('size_bytes');
            $table->date('date_established')->nullable();
            $table->date('expiry_date')->nullable();
            $table->boolean('is_verified')->default(false);
            $table->foreignId('verified_by')->nullable()->constrained('users');
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('superseded_by')->nullable()->constrained('documents');
            $table->boolean('is_current')->default(true);
            $table->timestamps();

            $table->index(['user_id', 'category']);
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained();
            $table->unsignedBigInteger('impersonated_user_id')->nullable();
            $table->string('action');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at');

            $table->index(['model_type', 'model_id']);
            $table->index('user_id');
            $table->index('created_at');
        });

        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->longText('body');
            $table->string('featured_image')->nullable();
            $table->boolean('is_published')->default(false);
            $table->boolean('is_public')->default(true);
            $table->foreignId('author_id')->constrained('users');
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('article_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained()->cascadeOnDelete();
            $table->string('file_path');
            $table->string('alt_text')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('links', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('url');
            $table->text('description')->nullable();
            $table->boolean('is_public')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('links');
        Schema::dropIfExists('article_images');
        Schema::dropIfExists('articles');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('documents');
    }
};

```

### File: database/migrations/2026_03_20_190225_add_cert_level_and_max_buddies_to_buddy_requests.php
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('buddy_requests', function (Blueprint $table) {
            $table->string('desired_cert_level')->nullable()->after('max_depth');
            $table->unsignedTinyInteger('max_buddies')->nullable()->after('desired_cert_level');
        });
    }

    public function down(): void
    {
        Schema::table('buddy_requests', function (Blueprint $table) {
            $table->dropColumn(['desired_cert_level', 'max_buddies']);
        });
    }
};

```

### File: database/migrations/2026_03_18_170000_create_article_translations_and_user_locale.php
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('article_translations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('article_id')->constrained()->cascadeOnDelete();
            $t->string('locale', 5);
            $t->string('title');
            $t->longText('body');
            $t->boolean('auto_translated')->default(true);
            $t->timestamps();
            $t->unique(['article_id', 'locale']);
        });

        Schema::table('users', function (Blueprint $t) {
            $t->string('preferred_locale', 5)->nullable()->after('status_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('article_translations');
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('preferred_locale'));
    }
};

```

### File: database/migrations/2026_03_19_170337_add_stale_to_article_translations.php
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('article_translations', function (Blueprint $table) {
            $table->boolean('stale')->default(false)->after('auto_translated');
        });
    }

    public function down(): void
    {
        Schema::table('article_translations', function (Blueprint $table) {
            $table->dropColumn('stale');
        });
    }
};

```

### File: database/migrations/2026_03_21_163823_add_diver_profile_fields_to_member_details.php
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('member_details', function (Blueprint $table) {
            $table->float('air_consumption')->default(0.5)->after('dive_count');
            $table->float('ease_level')->default(0.5)->after('air_consumption');
            $table->string('primary_intent', 30)->default('exploration')->after('ease_level');
            $table->boolean('is_photographer')->default(false)->after('primary_intent');
            $table->integer('total_dives')->default(0)->after('is_photographer');
            $table->date('last_dive_date')->nullable()->after('total_dives');
        });
    }

    public function down(): void
    {
        Schema::table('member_details', function (Blueprint $table) {
            $table->dropColumn(['air_consumption', 'ease_level', 'primary_intent', 'is_photographer', 'total_dives', 'last_dive_date']);
        });
    }
};

```

### File: database/migrations/2026_03_17_214946_create_certification_and_theme_tables.php
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Certification levels lookup table
        Schema::create('certification_levels', function (Blueprint $t) {
            $t->id();
            $t->foreignId('federation_id')->constrained()->cascadeOnDelete();
            $t->string('code', 30);           // e.g. "N1", "OWD", "P1"
            $t->string('name');                // e.g. "Niveau 1", "Open Water Diver"
            $t->string('category', 30);        // diver, instructor, specialty
            $t->unsignedSmallInteger('rank')->default(0); // hierarchy within federation
            $t->string('equivalence_group', 30)->nullable(); // cross-federation equivalence
            $t->timestamps();
            $t->unique(['federation_id', 'code']);
        });

        // User certification levels (many-to-many)
        Schema::create('user_certification_levels', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('certification_level_id')->constrained()->cascadeOnDelete();
            $t->date('obtained_date')->nullable();
            $t->boolean('is_primary')->default(false); // user's preferred display cert
            $t->unsignedInteger('display_priority')->default(0); // learned from user behavior
            $t->timestamps();
            $t->unique(['user_id', 'certification_level_id']);
        });

        // WhatsApp group link on events and season patterns
        Schema::table('events', function (Blueprint $t) {
            $t->string('whatsapp_group_url')->nullable()->after('participant_email');
        });
        Schema::table('season_patterns', function (Blueprint $t) {
            $t->string('whatsapp_group_url')->nullable()->after('color_hex');
        });

        // Theme settings (key-value, DB-driven)
        Schema::create('theme_settings', function (Blueprint $t) {
            $t->id();
            $t->string('key', 80)->unique();
            $t->text('value')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::table('events', fn (Blueprint $t) => $t->dropColumn('whatsapp_group_url'));
        Schema::table('season_patterns', fn (Blueprint $t) => $t->dropColumn('whatsapp_group_url'));
        Schema::dropIfExists('user_certification_levels');
        Schema::dropIfExists('certification_levels');
        Schema::dropIfExists('theme_settings');
    }
};

```

### File: database/migrations/0001_01_01_000004_create_member_tables.php
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('federations', function (Blueprint $table) {
            $table->id();
            $table->string('acronym')->unique();
            $table->string('full_name');
            $table->timestamps();
        });

        Schema::create('member_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('birth_name')->nullable();
            $table->string('nationality')->nullable();
            $table->string('phone_private')->nullable();
            $table->string('phone_office')->nullable();
            $table->string('phone_mobile')->nullable();
            $table->enum('sex', ['M', 'F', 'X'])->nullable();
            $table->year('adhesion_year')->nullable();
            $table->boolean('bureau_member')->default(false);
            $table->boolean('active_instructor')->default(false);
            $table->string('cep_email')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('place_of_birth')->nullable();
            $table->string('address_line1')->nullable();
            $table->string('address_line2')->nullable();
            $table->string('city')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('country')->nullable();
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_phone')->nullable();
            $table->string('emergency_contact_relationship')->nullable();
            $table->date('brevet_date')->nullable();
            $table->unsignedInteger('dive_count')->default(0);
            $table->string('certification_level')->nullable();
            $table->json('other_certifications')->nullable();
            $table->json('training_enrollments')->nullable();
            $table->string('preferred_language', 5)->default('en');
            $table->json('cotisation_years')->nullable();
            $table->string('bcd_size')->nullable();
            $table->text('bcd_notes')->nullable();
            $table->timestamps();
        });

        Schema::create('member_licences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('federation_id')->constrained('federations');
            $table->string('licence_number')->nullable();
            $table->string('federation_key')->nullable();
            $table->date('licence_request_date')->nullable();
            $table->boolean('licence_request_pending')->default(false);
            $table->timestamps();

            $table->unique(['user_id', 'federation_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_licences');
        Schema::dropIfExists('member_details');
        Schema::dropIfExists('federations');
    }
};

```

### File: database/migrations/0001_01_01_000006_create_rules_tables.php
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medical_compliance_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('federation_id')->constrained('federations')->cascadeOnDelete();
            $table->unsignedInteger('age_bracket_low');
            $table->unsignedInteger('age_bracket_high');
            $table->string('cert_type'); // gp, ent, cardio, ophthalmologist, other
            $table->unsignedInteger('validity_months');
            $table->timestamps();
        });

        Schema::create('equipment_maintenance_rules', function (Blueprint $table) {
            $table->id();
            $table->string('equipment_type');
            $table->string('maintenance_name');
            $table->unsignedInteger('interval_months');
            $table->boolean('is_mandatory')->default(true);
            $table->string('regulation_reference')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment_maintenance_rules');
        Schema::dropIfExists('medical_compliance_rules');
    }
};

```

### File: database/migrations/2026_03_18_160000_add_activity_type_to_instructor_availabilities.php
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('instructor_availabilities', function (Blueprint $table) {
            $table->string('activity_type')->default('pool')->after('slot');
        });

        // MySQL won't drop unique if FK references same column — drop FK first
        Schema::table('instructor_availabilities', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });
        Schema::table('instructor_availabilities', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'date', 'slot']);
        });
        Schema::table('instructor_availabilities', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['user_id', 'date', 'slot', 'activity_type']);
        });
    }

    public function down(): void
    {
        Schema::table('instructor_availabilities', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropUnique(['user_id', 'date', 'slot', 'activity_type']);
        });
        Schema::table('instructor_availabilities', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['user_id', 'date', 'slot']);
            $table->dropColumn('activity_type');
        });
    }
};

```

### File: database/migrations/2026_03_17_202728_create_payments_tables.php
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Phase 4: Payments & Fees
        Schema::create('membership_fee_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('season_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug')->index();
            $table->decimal('amount', 8, 2);
            $table->boolean('is_base')->default(false);
            $table->boolean('is_optional')->default(false);
            $table->string('description')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('payment_expected', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // membership, event_deposit
            $table->foreignId('event_id')->nullable()->constrained()->nullOnDelete();
            $table->string('season_year')->nullable();
            $table->decimal('amount_due', 8, 2);
            $table->string('communication')->nullable();
            $table->json('components')->nullable();
            $table->string('status')->default('pending'); // pending, paid, partial, cancelled
            $table->decimal('amount_paid', 8, 2)->default(0);
            $table->date('paid_at')->nullable();
            $table->string('reconciled_by')->nullable();
            $table->timestamp('reconciled_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status']);
        });

        Schema::create('bank_transactions', function (Blueprint $table) {
            $table->id();
            $table->date('transaction_date');
            $table->decimal('amount', 10, 2);
            $table->string('communication')->nullable();
            $table->string('counterparty')->nullable();
            $table->foreignId('matched_payment_id')->nullable()->constrained('payment_expected')->nullOnDelete();
            $table->integer('match_score')->nullable();
            $table->string('status')->default('unmatched'); // unmatched, matched, confirmed, ignored
            $table->foreignId('confirmed_by')->nullable()->constrained('users');
            $table->timestamps();
        });

        // Phase 5: Equipment
        Schema::create('equipment', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type'); // bcd, regulator, tank, wetsuit, mask, fins, computer, other
            $table->string('serial_number')->nullable();
            $table->date('purchase_date')->nullable();
            $table->string('condition')->default('good'); // new, good, fair, poor
            $table->string('status')->default('available'); // available, on_loan, maintenance_required, retired
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('equipment_maintenance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipment_id')->constrained()->cascadeOnDelete();
            $table->string('maintenance_name');
            $table->date('due_date');
            $table->date('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users');
            $table->text('notes')->nullable();
            $table->boolean('is_mandatory')->default(true);
            $table->timestamps();
            $table->index(['equipment_id', 'due_date']);
        });

        Schema::create('equipment_loans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('loaned_at');
            $table->date('returned_at')->nullable();
            $table->foreignId('loaned_by')->nullable()->constrained('users');
            $table->foreignId('returned_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->index(['equipment_id', 'returned_at']);
        });

        // Phase 6: Email System
        Schema::create('email_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('subject');
            $table->text('body'); // supports {{variables}}
            $table->string('locale', 5)->default('en');
            $table->timestamps();
        });

        Schema::create('email_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('to_email');
            $table->string('subject');
            $table->text('body')->nullable();
            $table->string('template_slug')->nullable();
            $table->string('status')->default('sent'); // queued, sent, failed
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->text('error')->nullable();
            $table->timestamps();
        });

        // Phase 7: Voting
        Schema::create('votes', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('mode'); // simple, election
            $table->string('status')->default('draft'); // draft, open, closed, cancelled
            $table->timestamp('opens_at')->nullable();
            $table->timestamp('closes_at')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });

        Schema::create('vote_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vote_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('vote_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vote_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('token', 128)->unique();
            $table->boolean('is_consumed')->default(false);
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();
            $table->unique(['vote_id', 'user_id']);
        });

        Schema::create('vote_ballots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vote_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vote_option_id')->constrained()->cascadeOnDelete();
            $table->string('token_hash')->nullable(); // null for election (anonymous), set for simple
            $table->timestamps();
            $table->index(['vote_id', 'vote_option_id']);
        });

        // Phase 8: GDPR
        Schema::create('gdpr_consents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('consent_type'); // data_processing, marketing, photo_publication
            $table->boolean('granted')->default(false);
            $table->timestamp('granted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'consent_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gdpr_consents');
        Schema::dropIfExists('vote_ballots');
        Schema::dropIfExists('vote_tokens');
        Schema::dropIfExists('vote_options');
        Schema::dropIfExists('votes');
        Schema::dropIfExists('email_log');
        Schema::dropIfExists('email_templates');
        Schema::dropIfExists('equipment_loans');
        Schema::dropIfExists('equipment_maintenance');
        Schema::dropIfExists('equipment');
        Schema::dropIfExists('bank_transactions');
        Schema::dropIfExists('payment_expected');
        Schema::dropIfExists('membership_fee_components');
    }
};

```

### File: database/migrations/2026_03_17_234400_add_event_photos_and_club_iban.php
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete();
            $table->string('path');
            $table->string('thumbnail_path')->nullable();
            $table->string('caption', 500)->nullable();
            $table->unsignedTinyInteger('quality_score')->default(50); // 0-100 auto-rated
            $table->boolean('approved')->default(true);
            $table->timestamps();
        });

        // Add IBAN to theme_settings if not present
        \App\Models\ThemeSetting::firstOrCreate(['key' => 'club_iban'], ['value' => env('CLUB_IBAN', '')]);
        \App\Models\ThemeSetting::firstOrCreate(['key' => 'club_bic'], ['value' => '']);
    }

    public function down(): void
    {
        Schema::dropIfExists('event_photos');
        \App\Models\ThemeSetting::whereIn('key', ['club_iban', 'club_bic'])->delete();
    }
};

```

### File: database/migrations/2026_03_20_190259_add_show_on_public_site_to_member_details.php
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('member_details', function (Blueprint $table) {
            $table->boolean('show_on_public_site')->default(true)->after('instructor_motivation');
        });
    }

    public function down(): void
    {
        Schema::table('member_details', function (Blueprint $table) {
            $table->dropColumn('show_on_public_site');
        });
    }
};

```

### File: database/migrations/2026_03_21_170957_add_ffessm_fields_to_member_licences.php
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('member_licences', function (Blueprint $table) {
            $table->string('insurance_type', 50)->nullable()->after('licence_number');
            $table->date('medical_cert_expiry')->nullable()->after('insurance_type');
            $table->string('season', 20)->nullable()->after('medical_cert_expiry');
            $table->date('registration_date')->nullable()->after('season');
        });
    }

    public function down(): void
    {
        Schema::table('member_licences', function (Blueprint $table) {
            $table->dropColumn(['insurance_type', 'medical_cert_expiry', 'season', 'registration_date']);
        });
    }
};

```

### File: database/migrations/2026_03_18_110000_enhance_dive_sites_and_groups.php
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dive_sites', function (Blueprint $table) {
            $table->text('facilities')->nullable()->after('access_notes');
            $table->text('nearest_hospital')->nullable()->after('facilities');
            $table->string('website_url')->nullable()->after('nearest_hospital');
            $table->string('map_image_path')->nullable()->after('image_path');
        });

        // Add purpose/label to dive groups
        Schema::table('dive_groups', function (Blueprint $table) {
            $table->string('purpose')->nullable()->after('dive_mode'); // explo, exercise, certify, autonomous_training, bapteme, navigation, etc.
        });
    }

    public function down(): void
    {
        Schema::table('dive_sites', function (Blueprint $table) {
            $table->dropColumn(['facilities', 'nearest_hospital', 'website_url', 'map_image_path']);
        });
        Schema::table('dive_groups', function (Blueprint $table) {
            $table->dropColumn('purpose');
        });
    }
};

```

### File: database/migrations/2026_03_20_194402_add_public_photos_banned_to_member_details.php
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('member_details', function (Blueprint $table) {
            $table->boolean('public_photos_banned')->default(false)->after('show_on_public_site');
        });

        // Set default for existing minors
        DB::table('member_details')
            ->whereNotNull('date_of_birth')
            ->where('date_of_birth', '>', now()->subYears(18))
            ->update(['public_photos_banned' => true]);
    }

    public function down(): void
    {
        Schema::table('member_details', function (Blueprint $table) {
            $table->dropColumn('public_photos_banned');
        });
    }
};

```

### File: database/migrations/2026_03_17_221556_rework_fee_system.php
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Rework: fees are absolute amounts per status per season, not ratios
        // Drop the old multiplier-based columns
        Schema::table('member_statuses', function (Blueprint $t) {
            $t->dropColumn('fee_multiplier');
        });

        // New table: absolute fee amounts per status per season year
        Schema::create('membership_fees', function (Blueprint $t) {
            $t->id();
            $t->string('season_year', 10);  // e.g. "2026"
            $t->foreignId('status_id')->constrained('member_statuses')->cascadeOnDelete();
            $t->decimal('amount', 8, 2);     // absolute amount decided by bureau
            $t->string('label')->nullable();  // e.g. "Cotisation Actif 2026"
            $t->text('notes')->nullable();    // e.g. "Decided at AG 2025-12-15"
            $t->timestamps();
            $t->unique(['season_year', 'status_id']);
        });

        // Keep optional add-ons (insurance, double affiliation) but simplify
        // membership_fee_components already exists — just used for optionals now
    }

    public function down(): void
    {
        Schema::dropIfExists('membership_fees');
        Schema::table('member_statuses', function (Blueprint $t) {
            $t->decimal('fee_multiplier', 5, 2)->default(1.00)->after('slug');
        });
    }
};

```

### File: database/migrations/2026_03_19_115600_add_library_visibility.php
```php
<?php

/**
 * Replace binary is_public with role-based visibility on library_files.
 *
 * @author ClubCEP.eu
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('library_files', function (Blueprint $table) {
            // public=anyone, members=logged-in, instructors=instructor+bureau, bureau=bureau only
            $table->string('visibility', 20)->default('members')->after('is_public');
        });

        // Migrate existing data
        DB::table('library_files')->where('is_public', true)->update(['visibility' => 'public']);
        DB::table('library_files')->where('is_public', false)->update(['visibility' => 'bureau']);

        Schema::table('library_files', function (Blueprint $table) {
            $table->dropColumn('is_public');
        });
    }

    public function down(): void
    {
        Schema::table('library_files', function (Blueprint $table) {
            $table->boolean('is_public')->default(false)->after('folder');
        });

        DB::table('library_files')->where('visibility', 'public')->update(['is_public' => true]);

        Schema::table('library_files', function (Blueprint $table) {
            $table->dropColumn('visibility');
        });
    }
};

```

### File: database/migrations/0001_01_01_000007_create_events_tables.php
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seasons', function (Blueprint $table) {
            $table->id();
            $table->year('year');
            $table->string('name');
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });

        Schema::create('season_holidays', function (Blueprint $table) {
            $table->id();
            $table->foreignId('season_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_adhoc')->default(false);
            $table->timestamps();
        });

        Schema::create('season_patterns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('season_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week'); // 0=Mon..6=Sun
            $table->time('start_time');
            $table->time('end_time')->nullable();
            $table->string('event_type');
            $table->string('title');
            $table->string('location')->nullable();
            $table->unsignedInteger('max_participants')->nullable();
            $table->string('color_hex', 7)->nullable();
            $table->timestamps();
        });

        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('color_hex', 7)->nullable();
            $table->string('event_type'); // pool, dive, training, theory, social
            $table->date('event_date');
            $table->time('event_time')->nullable();
            $table->time('end_time')->nullable();
            $table->date('end_date')->nullable();
            $table->string('location')->nullable();
            $table->text('description')->nullable();
            $table->foreignId('responsible_id')->nullable()->constrained('users');
            $table->unsignedInteger('max_participants')->nullable();
            $table->boolean('waiting_list_enabled')->default(true);
            $table->timestamp('inscription_open_at')->nullable();
            $table->boolean('inscriptions_closed')->default(false);
            $table->boolean('levels_display')->default(false);
            $table->boolean('confirmation_required')->default(false);
            $table->decimal('estimated_cost', 8, 2)->nullable();
            $table->date('deposit_1_date')->nullable();
            $table->decimal('deposit_1_amount', 8, 2)->nullable();
            $table->date('deposit_2_date')->nullable();
            $table->decimal('deposit_2_amount', 8, 2)->nullable();
            $table->date('deposit_3_date')->nullable();
            $table->decimal('deposit_3_amount', 8, 2)->nullable();
            $table->foreignId('instructor_id')->nullable()->constrained('users');
            $table->json('assistant_ids')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->date('permissions_expire_date')->nullable();
            $table->string('status')->default('scheduled'); // scheduled, cancelled, completed
            $table->foreignId('season_id')->nullable()->constrained();
            $table->string('participant_email')->nullable();
            $table->timestamps();

            $table->index('event_date');
            $table->index('status');
        });

        Schema::create('event_registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('confirmed'); // confirmed, waiting, cancelled
            $table->unsignedInteger('waiting_list_position')->nullable();
            $table->timestamp('checked_in_at')->nullable();
            $table->timestamp('checked_out_at')->nullable();
            $table->foreignId('checked_in_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->unique(['event_id', 'user_id']);
            $table->index(['event_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_registrations');
        Schema::dropIfExists('events');
        Schema::dropIfExists('season_patterns');
        Schema::dropIfExists('season_holidays');
        Schema::dropIfExists('seasons');
    }
};

```

### File: database/migrations/2026_03_22_045629_add_registration_opens_days_before_to_season_patterns.php
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('season_patterns', function (Blueprint $table) {
            $table->unsignedSmallInteger('registration_opens_days_before')->nullable()->after('max_participants');
        });
    }

    public function down(): void
    {
        Schema::table('season_patterns', function (Blueprint $table) {
            $table->dropColumn('registration_opens_days_before');
        });
    }
};

```

### File: database/migrations/2026_03_21_164523_create_push_subscriptions_table.php
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('endpoint', 500)->unique();
            $table->string('p256dh');
            $table->string('auth');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_subscriptions');
    }
};

```

### File: database/migrations/2026_03_20_220407_add_detailed_fields_to_equipment.php
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('equipment', function (Blueprint $table) {
            $table->string('club_id', 10)->nullable()->after('id');
            $table->string('brand')->nullable()->after('name');
            $table->string('manufacturer')->nullable()->after('brand');
            $table->string('threading', 20)->nullable()->after('manufacturer');
            $table->date('manufacture_date')->nullable()->after('threading');
            $table->decimal('weight_kg', 5, 1)->nullable()->after('manufacture_date');
            $table->string('volume', 20)->nullable()->after('weight_kg');
            $table->string('material', 20)->nullable()->after('volume');
            $table->unsignedSmallInteger('test_pressure_bar')->nullable()->after('material');
            $table->unsignedSmallInteger('working_pressure_bar')->nullable()->after('test_pressure_bar');
            $table->date('last_retest_date')->nullable()->after('working_pressure_bar');
            $table->date('next_retest_date')->nullable()->after('last_retest_date');
            $table->date('last_inventory_date')->nullable()->after('next_retest_date');
        });
    }

    public function down(): void
    {
        Schema::table('equipment', function (Blueprint $table) {
            $table->dropColumn([
                'club_id', 'brand', 'manufacturer', 'threading', 'manufacture_date',
                'weight_kg', 'volume', 'material', 'test_pressure_bar', 'working_pressure_bar',
                'last_retest_date', 'next_retest_date', 'last_inventory_date',
            ]);
        });
    }
};

```

### File: database/migrations/2026_03_19_090000_add_parental_consent_and_social_publish.php
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Parent-child relationships for minors
        Schema::create('guardian_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guardian_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('minor_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('relationship')->default('parent'); // parent, legal_guardian
            $table->timestamps();
            $table->unique(['guardian_user_id', 'minor_user_id']);
        });

        // Parental consent records
        Schema::create('parental_consents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('minor_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('granted_by')->constrained('users'); // the guardian
            $table->string('consent_type'); // events, photos, medical, general
            $table->boolean('granted')->default(true);
            $table->string('document_path')->nullable(); // signed consent form upload
            $table->timestamp('granted_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->index(['minor_user_id', 'consent_type']);
        });

        // Social media publish log
        Schema::create('social_publish_logs', function (Blueprint $table) {
            $table->id();
            $table->string('platform'); // facebook, instagram
            $table->morphs('publishable'); // event_photo, article, etc.
            $table->string('external_post_id')->nullable();
            $table->string('status'); // pending, published, failed
            $table->text('error_message')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        // Add gdpr_photo_consent flag directly on event_photos for quick checks
        if (!Schema::hasColumn('event_photos', 'gdpr_consent')) {
            Schema::table('event_photos', function (Blueprint $table) {
                $table->boolean('gdpr_consent')->default(false)->after('approved');
            });
        }

        // Retention policy setting
        \App\Models\ThemeSetting::firstOrCreate(
            ['key' => 'audit_retention_months'],
            ['value' => '24']
        );
        \App\Models\ThemeSetting::firstOrCreate(
            ['key' => 'fb_group_id'],
            ['value' => '']
        );
        \App\Models\ThemeSetting::firstOrCreate(
            ['key' => 'fb_group_is_closed'],
            ['value' => '0']
        );
        \App\Models\ThemeSetting::firstOrCreate(
            ['key' => 'social_auto_publish'],
            ['value' => '0']
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('social_publish_logs');
        Schema::dropIfExists('parental_consents');
        Schema::dropIfExists('guardian_links');
        if (Schema::hasColumn('event_photos', 'gdpr_consent')) {
            Schema::table('event_photos', fn(Blueprint $t) => $t->dropColumn('gdpr_consent'));
        }
    }
};

```

### File: database/migrations/0001_01_01_000002_create_jobs_table.php
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('jobs', function (Blueprint $table) {
            $table->id();
            $table->string('queue')->index();
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });

        Schema::create('job_batches', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->integer('total_jobs');
            $table->integer('pending_jobs');
            $table->integer('failed_jobs');
            $table->longText('failed_job_ids');
            $table->mediumText('options')->nullable();
            $table->integer('cancelled_at')->nullable();
            $table->integer('created_at');
            $table->integer('finished_at')->nullable();
        });

        Schema::create('failed_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->text('connection');
            $table->text('queue');
            $table->longText('payload');
            $table->longText('exception');
            $table->timestamp('failed_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('jobs');
        Schema::dropIfExists('job_batches');
        Schema::dropIfExists('failed_jobs');
    }
};

```

### File: database/migrations/2026_03_23_143155_add_inbound_email_fields_to_email_log.php
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_log', function (Blueprint $table) {
            $table->string('direction', 10)->default('outbound')->after('status'); // inbound, outbound, contact
            $table->string('alias')->nullable()->after('to_email'); // original alias (bureau@, event-5@, etc.)
            $table->boolean('authorized')->default(true)->after('direction');
        });
    }

    public function down(): void
    {
        Schema::table('email_log', function (Blueprint $table) {
            $table->dropColumn(['direction', 'alias', 'authorized']);
        });
    }
};

```

### File: database/migrations/2026_03_18_080000_add_document_library_and_instructor_bio.php
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Document library (FileGator equivalent)
        Schema::create('library_files', function (Blueprint $table) {
            $table->id();
            $table->string('filename');
            $table->string('original_name');
            $table->string('path');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->string('folder')->default('/');
            $table->boolean('is_public')->default(false);
            $table->text('description')->nullable();
            $table->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index('folder');
            $table->index('is_public');
        });

        // Instructor bio fields
        Schema::table('member_details', function (Blueprint $table) {
            $table->text('instructor_bio')->nullable()->after('active_instructor');
            $table->text('instructor_specialties')->nullable()->after('instructor_bio');
            $table->text('instructor_motivation')->nullable()->after('instructor_specialties');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('library_files');
        Schema::table('member_details', function (Blueprint $table) {
            $table->dropColumn(['instructor_bio', 'instructor_specialties', 'instructor_motivation']);
        });
    }
};

```

### File: database/migrations/0001_01_01_000001_create_cache_table.php
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('cache', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->mediumText('value');
            $table->integer('expiration');
        });

        Schema::create('cache_locks', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->string('owner');
            $table->integer('expiration');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cache');
        Schema::dropIfExists('cache_locks');
    }
};

```

### File: database/migrations/2026_03_18_120000_add_site_plan_and_fees_to_dive_sites.php
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dive_sites', function (Blueprint $table) {
            $table->string('site_plan_path')->nullable()->after('map_image_path');
            $table->decimal('entry_fee', 8, 2)->nullable()->after('website_url');
            $table->string('booking_url')->nullable()->after('entry_fee');
        });
    }

    public function down(): void
    {
        Schema::table('dive_sites', function (Blueprint $table) {
            $table->dropColumn(['site_plan_path', 'entry_fee', 'booking_url']);
        });
    }
};

```

### File: database/migrations/2026_03_23_160652_add_soft_deletes_to_critical_tables.php
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $tables = [
        'users',
        'member_details',
        'events',
        'articles',
        'documents',
        'equipment',
        'votes',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->softDeletes());
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->dropSoftDeletes());
        }
    }
};

```

### File: database/migrations/2026_03_21_161351_rename_cep_email_to_club_email_on_member_details.php
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('member_details', function (Blueprint $table) {
            $table->renameColumn('cep_email', 'club_email');
        });
    }

    public function down(): void
    {
        Schema::table('member_details', function (Blueprint $table) {
            $table->renameColumn('club_email', 'cep_email');
        });
    }
};

```

### File: database/migrations/2026_03_18_060000_add_article_types.php
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->string('article_type', 30)->default('news')->after('slug');
            $table->foreignId('vote_id')->nullable()->after('author_id')->constrained('votes')->nullOnDelete();
            $table->timestamp('expires_at')->nullable()->after('is_public');

            $table->index('article_type');
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->dropForeign(['vote_id']);
            $table->dropIndex(['article_type']);
            $table->dropIndex(['expires_at']);
            $table->dropColumn(['article_type', 'vote_id', 'expires_at']);
        });
    }
};

```

### File: database/migrations/2026_03_22_050252_add_iban_to_external_registrations.php
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('external_registrations', function (Blueprint $table) {
            $table->string('external_member_iban', 34)->nullable()->after('external_member_emergency_contact');
        });
    }

    public function down(): void
    {
        Schema::table('external_registrations', function (Blueprint $table) {
            $table->dropColumn('external_member_iban');
        });
    }
};

```

### File: database/migrations/2026_03_20_190340_add_refund_review_to_payment_expected.php
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_expected', function (Blueprint $table) {
            $table->boolean('refund_review_needed')->default(false)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('payment_expected', function (Blueprint $table) {
            $table->dropColumn('refund_review_needed');
        });
    }
};

```

### File: database/migrations/0001_01_01_000000_create_users_table.php
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::create('member_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->decimal('fee_multiplier', 4, 2)->default(1.00);
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('username')->unique()->nullable();
            $table->string('primary_email')->unique();
            $table->string('password')->nullable();
            $table->foreignId('role_id')->constrained('roles');
            $table->foreignId('status_id')->nullable()->constrained('member_statuses');
            $table->timestamp('email_verified_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });

        Schema::create('failed_login_attempts', function (Blueprint $table) {
            $table->id();
            $table->string('email')->index();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('attempted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('failed_login_attempts');
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
        Schema::dropIfExists('member_statuses');
        Schema::dropIfExists('roles');
    }
};

```

### File: database/migrations/2026_03_19_095004_add_registration_audit_fields.php
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_registrations', function (Blueprint $table) {
            $table->text('comment')->nullable()->after('status');
            $table->foreignId('registered_by')->nullable()->after('comment')->constrained('users');
            $table->timestamp('cancelled_at')->nullable()->after('registered_by');
            $table->foreignId('cancelled_by')->nullable()->after('cancelled_at')->constrained('users');
            $table->text('cancel_comment')->nullable()->after('cancelled_by');
        });

        Schema::table('email_log', function (Blueprint $table) {
            $table->foreignId('event_id')->nullable()->after('id')->constrained('events')->nullOnDelete();
            $table->string('from_name')->nullable()->after('to_email');
            $table->string('from_email')->nullable()->after('from_name');
        });
    }

    public function down(): void
    {
        Schema::table('event_registrations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('registered_by');
            $table->dropConstrainedForeignId('cancelled_by');
            $table->dropColumn(['comment', 'cancelled_at', 'cancel_comment']);
        });

        Schema::table('email_log', function (Blueprint $table) {
            $table->dropConstrainedForeignId('event_id');
            $table->dropColumn(['from_name', 'from_email']);
        });
    }
};

```

### File: database/migrations/2026_03_21_170903_fix_push_subscriptions_endpoint_column.php
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('push_subscriptions', function (Blueprint $table) {
            $table->string('endpoint', 500)->change();
        });

        try {
            Schema::table('push_subscriptions', function (Blueprint $table) {
                $table->unique('endpoint');
            });
        } catch (\Throwable) {
            // Index already exists
        }
    }

    public function down(): void
    {
        Schema::table('push_subscriptions', function (Blueprint $table) {
            $table->dropUnique(['endpoint']);
            $table->text('endpoint')->change();
        });
    }
};

```

### File: database/migrations/2026_03_22_062510_add_expected_return_date_to_equipment_loans.php
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('equipment_loans', function (Blueprint $table) {
            $table->date('expected_return_date')->nullable()->after('loaned_at');
            $table->date('reminder_sent_at')->nullable()->after('returned_by');
        });
    }

    public function down(): void
    {
        Schema::table('equipment_loans', function (Blueprint $table) {
            $table->dropColumn(['expected_return_date', 'reminder_sent_at']);
        });
    }
};

```

### File: database/migrations/2026_03_19_122800_add_has_faces_to_event_photos.php
```php
<?php

/**
 * Track face detection results on event photos.
 * Photos with faces are excluded from public/anonymous display.
 *
 * @author ClubCEP.eu
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_photos', function (Blueprint $table) {
            $table->boolean('has_faces')->nullable()->after('quality_score');
        });
    }

    public function down(): void
    {
        Schema::table('event_photos', function (Blueprint $table) {
            $table->dropColumn('has_faces');
        });
    }
};

```

### File: database/migrations/2026_03_18_090000_add_cert_type_to_documents.php
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->string('cert_type', 30)->nullable()->after('category');
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn('cert_type');
        });
    }
};

```

### File: database/migrations/2026_03_18_140000_add_trial_requests_and_safety_docs.php
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Trial dive appointment requests
        Schema::create('trial_requests', function (Blueprint $table) {
            $table->id();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->date('preferred_date')->nullable();
            $table->text('message')->nullable();
            $table->string('status')->default('pending'); // pending, confirmed, completed, cancelled
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('confirmed_date')->nullable();
            $table->text('admin_notes')->nullable();
            $table->timestamps();
        });

        // Safety documents per dive site (link to library folder)
        Schema::table('dive_sites', function (Blueprint $table) {
            $table->string('safety_docs_folder')->nullable()->after('site_plan_path');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trial_requests');
        Schema::table('dive_sites', function (Blueprint $table) {
            $table->dropColumn('safety_docs_folder');
        });
    }
};

```

### File: database/migrations/2026_03_18_100000_add_dive_sites_and_groups.php
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Dive sites — reusable location profiles
        Schema::create('dive_sites', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('country')->nullable();
            $table->string('region')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->integer('max_depth')->nullable(); // metres
            $table->string('water_type')->nullable(); // sea, lake, quarry, river, pool
            $table->text('conditions')->nullable();
            $table->text('marine_life')->nullable();
            $table->text('safety_notes')->nullable();
            $table->text('access_notes')->nullable();
            $table->string('image_path')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Link events to dive sites
        Schema::table('events', function (Blueprint $table) {
            $table->foreignId('dive_site_id')->nullable()->after('whatsapp_group_url')->constrained()->nullOnDelete();
        });

        // Configurable rules for dive group composition
        Schema::create('dive_group_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // human-readable rule name
            $table->string('scope'); // federation acronym or 'global'
            $table->string('diver_condition'); // e.g. 'no_cert', 'max_rank:20', 'max_rank:40', 'any'
            $table->string('dive_mode'); // 'supervised', 'autonomous', 'training', 'certification'
            $table->integer('min_leader_rank'); // minimum rank of group leader
            $table->string('leader_category'); // 'instructor' or 'diver' (guide de palanquée = diver cat rank>=70)
            $table->integer('max_depth')->nullable(); // depth limit for this rule
            $table->integer('max_group_size')->default(4);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Dive groups (palanquées) per event
        Schema::create('dive_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('name')->nullable(); // "Palanquée 1", etc.
            $table->string('dive_mode')->default('supervised'); // supervised, autonomous, training, certification
            $table->integer('planned_depth')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });

        // Members of each dive group
        Schema::create('dive_group_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dive_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role')->default('diver'); // leader, diver
            $table->timestamps();
            $table->unique(['dive_group_id', 'user_id']);
        });

        // Add caption to event_photos if not present
        if (!Schema::hasColumn('event_photos', 'caption')) {
            // already exists
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('dive_group_members');
        Schema::dropIfExists('dive_groups');
        Schema::dropIfExists('dive_group_rules');
        Schema::table('events', function (Blueprint $table) {
            $table->dropConstrainedForeignId('dive_site_id');
        });
        Schema::dropIfExists('dive_sites');
    }
};

```

### File: database/migrations/2026_03_18_130000_add_buddy_requests_and_food_options.php
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Buddy requests — "looking for buddies" board
        Schema::create('buddy_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('dive_site_id')->nullable()->constrained()->nullOnDelete();
            $table->string('location_text')->nullable(); // free text if no dive site
            $table->date('dive_date');
            $table->string('dive_time')->nullable(); // e.g. "morning", "10:00"
            $table->string('need_type'); // buddy, guide, dp (directeur de plongée)
            $table->text('description')->nullable();
            $table->integer('max_depth')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Responses to buddy requests
        Schema::create('buddy_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('buddy_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('message')->nullable();
            $table->string('status')->default('interested'); // interested, confirmed, declined
            $table->timestamps();
            $table->unique(['buddy_request_id', 'user_id']);
        });

        // Add food/restaurants field to dive sites
        Schema::table('dive_sites', function (Blueprint $table) {
            $table->text('food_options')->nullable()->after('facilities');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('buddy_responses');
        Schema::dropIfExists('buddy_requests');
        Schema::table('dive_sites', function (Blueprint $table) {
            $table->dropColumn('food_options');
        });
    }
};

```


## Directory: routes

### File: routes/console.php
```php
<?php

use App\Http\Middleware\SetLocale;
use App\Jobs\SendMedicalReminders;
use App\Jobs\WeeklyBackup;
use App\Models\Article;
use App\Models\AuditLog;
use App\Models\EquipmentLoan;
use App\Models\ThemeSetting;
use App\Models\Vote;
use App\Services\ArticleTranslationService;
use App\Services\PushNotificationService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Storage;

Schedule::job(new SendMedicalReminders)->dailyAt('08:00');
Schedule::job(new WeeklyBackup)->weeklyOn(0, '03:00'); // Sunday 3am

// Auto-translate one untranslated article per hour
Schedule::call(function () {
    $article = Article::whereDoesntHave('translations')->where('is_published', true)->oldest()->first();
    if ($article) {
        $locales = SetLocale::enabledLocales();
        app(ArticleTranslationService::class)->translateAll($article, $locales);
    }
})->hourly();

// Auto-open/close votes
Schedule::call(function () {
    $opened = Vote::where('status', 'draft')->where('opens_at', '<=', now())->get();
    foreach ($opened as $vote) {
        $vote->update(['status' => 'open']);
        app(PushNotificationService::class)->sendToAll(
            __('Vote Open'),
            $vote->title,
            route('vote.show', ['token' => 'check']) // members use their token
        );
    }
    Vote::where('status', 'open')->where('closes_at', '<=', now())->update(['status' => 'closed']);
})->everyMinute();

// Auto-purge audit logs per retention policy (monthly)
Schedule::call(function () {
    $months = (int) ThemeSetting::get('audit_retention_months', 24);
    if ($months > 0) {
        AuditLog::where('created_at', '<', now()->subMonths($months))->delete();
    }
})->monthlyOn(1, '04:00');

// Expired classifieds: unpublish monthly, delete after 3 months inactive
Schedule::call(function () {
    // Unpublish expired
    Article::where('article_type', 'classified')
        ->where('is_published', true)
        ->whereNotNull('expires_at')
        ->where('expires_at', '<', now())
        ->update(['is_published' => false]);

    // Delete (with images) after 3 months past expiry
    $stale = Article::where('article_type', 'classified')
        ->where('expires_at', '<', now()->subMonths(3))
        ->get();
    foreach ($stale as $ad) {
        if ($ad->featured_image) {
            Storage::disk('public')->delete($ad->featured_image);
        }
        $ad->delete();
    }
    if ($stale->count()) {
        Log::info("Classifieds cleanup: deleted {$stale->count()} ads expired >3 months.");
    }
})->monthlyOn(1, '05:00');

// Overdue equipment loan reminders
Schedule::call(function () {
    $thresholdDays = (int) ThemeSetting::get('equipment_loan_max_days', 30);
    $overdueLoans = EquipmentLoan::whereNull('returned_at')
        ->whereNotNull('expected_return_date')
        ->where('expected_return_date', '<', now())
        ->whereNull('reminder_sent_at')
        ->with(['user', 'equipment'])
        ->get();

    foreach ($overdueLoans as $loan) {
        $days = (int) $loan->expected_return_date->diffInDays(now());
        Log::info("Overdue loan: {$loan->equipment->name} → {$loan->user->name} ({$days}d overdue)");
        app(PushNotificationService::class)->sendToUser(
            $loan->user,
            __('Equipment Return Overdue'),
            __(':item was due back :date. Please return it.', [
                'item' => $loan->equipment->name,
                'date' => $loan->expected_return_date->format('d/m/Y'),
            ]),
            '/profile'
        );
        $loan->update(['reminder_sent_at' => now()]);
    }

    // Also flag loans without expected_return_date that exceed threshold
    EquipmentLoan::whereNull('returned_at')
        ->whereNull('expected_return_date')
        ->where('loaned_at', '<', now()->subDays($thresholdDays))
        ->whereNull('reminder_sent_at')
        ->with(['user', 'equipment'])
        ->each(function ($loan) {
            app(PushNotificationService::class)->sendToBureau(
                __('Long Equipment Loan'),
                __(':item loaned to :name on :date', [
                    'item' => $loan->equipment->name,
                    'name' => $loan->user->name,
                    'date' => $loan->loaned_at->format('d/m/Y'),
                ]),
                '/admin/equipment/'.$loan->equipment_id
            );
            $loan->update(['reminder_sent_at' => now()]);
        });
})->dailyAt('09:00');

```

### File: routes/api.php
```php
<?php

use App\Http\Controllers\Api\FederationApiController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Inter-club federation API (authenticated via X-Club-Key-Id / X-Club-Secret headers)
Route::prefix('federation')->middleware('throttle:30,1')->group(function () {
    Route::get('/events', [FederationApiController::class, 'events']);
    Route::post('/register', [FederationApiController::class, 'register']);
    Route::get('/register/{id}', [FederationApiController::class, 'status']);
    Route::delete('/register/{id}', [FederationApiController::class, 'cancel']);
});

// Developer instance check API (signed with developer private key)
Route::get('/instance/status', function (Request $request) {
    $signature = $request->header('X-Dev-Signature');
    $timestamp = $request->header('X-Dev-Timestamp');
    abort_unless($signature && $timestamp, 403);
    abort_unless(abs(time() - (int) $timestamp) < 300, 403, 'Timestamp expired');

    $pubKey = config('app.dev_public_key');
    abort_unless($pubKey, 404);

    $valid = openssl_verify($timestamp, base64_decode($signature), $pubKey, OPENSSL_ALGO_SHA256);
    abort_unless($valid === 1, 403, 'Invalid signature');

    $admin = User::whereHas('role', fn ($q) => $q->where('slug', 'bureau_master'))->first();

    return response()->json([
        'version' => config('app.version', '1.0.0'),
        'club_name' => config('app.name'),
        'admin_name' => $admin?->name,
        'admin_email' => $admin?->primary_email,
        'member_count' => User::count(),
        'active_member_count' => User::whereHas('status', fn ($q) => $q->where('slug', 'actif'))->count(),
    ]);
});

```

### File: routes/web.php
```php
<?php

use App\Http\Controllers\Admin\AnnualReportController;
use App\Http\Controllers\Admin\ArticleController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\BackupController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DiveGroupRuleController;
use App\Http\Controllers\Admin\DiveSiteController;
use App\Http\Controllers\Admin\EmailController;
use App\Http\Controllers\Admin\EquipmentController;
use App\Http\Controllers\Admin\GuardianController;
use App\Http\Controllers\Admin\GuideController;
use App\Http\Controllers\Admin\LibraryController;
use App\Http\Controllers\Admin\LinkController;
use App\Http\Controllers\Admin\MedicalExportController;
use App\Http\Controllers\Admin\MemberController;
use App\Http\Controllers\Admin\PartnershipController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\SeasonController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\ThumbnailController;
use App\Http\Controllers\Admin\TrialRequestController;
use App\Http\Controllers\Admin\VoteController;
use App\Http\Controllers\Auth\EuLoginController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\SocialAuthController;
use App\Http\Controllers\BuddyController;
use App\Http\Controllers\CalendarFeedController;
use App\Http\Controllers\ClassifiedController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\ContactMemberController;
use App\Http\Controllers\DiveDataController;
use App\Http\Controllers\DiveGroupController;
use App\Http\Controllers\DocumentBrowserController;
use App\Http\Controllers\DuesCalculatorController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\GdprController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\HomepageLayoutController;
use App\Http\Controllers\InstallController;
use App\Http\Controllers\InstructorAvailabilityController;
use App\Http\Controllers\MembersDirectoryController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\QrCodeController;
use App\Http\Controllers\StagingMailController;
use App\Http\Controllers\TrialController;
use App\Http\Controllers\VotePublicController;
use App\Http\Middleware\CheckLicense;
use App\Models\User;
use App\Models\UserEmail;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Route;

// Locale switch
Route::get('/locale/{locale}', function (string $locale) {
    if (in_array($locale, config('app.available_locales', ['en', 'fr', 'de', 'lb', 'pt', 'it', 'nl', 'es', 'pl', 'hu', 'ro', 'sk']))) {
        session(['locale' => $locale]);
        if (auth()->check()) {
            auth()->user()->update(['preferred_locale' => $locale]);
            auth()->user()->detail?->update(['preferred_language' => $locale]);
        }
    }

    return back();
})->name('locale.switch');

// Install wizard (only accessible when DB is empty)
Route::get('/install', [InstallController::class, 'index'])->name('install.index');
Route::post('/install', [InstallController::class, 'run'])->name('install.run');

// Public
Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/article/{slug}', [HomeController::class, 'showArticle'])->name('article.show');
Route::get('/trial', [TrialController::class, 'show'])->name('trial.show');
Route::post('/trial', [TrialController::class, 'store'])->middleware('throttle:3,1')->name('trial.store');
Route::get('/dues', [DuesCalculatorController::class, 'show'])->name('dues.show');
Route::post('/dues', [DuesCalculatorController::class, 'calculate'])->name('dues.calculate');
Route::get('/cotisation', fn () => redirect()->route('dues.show'))->name('cotisation');
Route::get('/qr/sepa-public', [QrCodeController::class, 'sepaPublic'])->name('qr.sepa.public');
Route::get('/qr/payment', [QrCodeController::class, 'signedPaymentQr'])->name('qr.payment.signed');
Route::get('/pay/verify', [QrCodeController::class, 'verifyPayment'])->name('payment.verify');
Route::get('/calendar.ics', [CalendarFeedController::class, 'ical'])->name('calendar.ics');
Route::get('/contact', fn () => view('contact'))->name('contact');
Route::post('/contact', [ContactController::class, 'send'])->middleware('throttle:5,1')->name('contact.send');

// Guest auth
Route::middleware('guest')->group(function () {
    Route::get('/register', [RegisterController::class, 'create'])->middleware(CheckLicense::class)->name('register');
    Route::post('/register', [RegisterController::class, 'store'])->middleware([CheckLicense::class, 'throttle:5,1']);
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:5,1');

    // Password reset
    Route::get('/forgot-password', fn () => view('auth.forgot-password'))->name('password.request');
    Route::post('/forgot-password', function (Request $request) {
        $request->validate(['email' => 'required|email']);
        Password::sendResetLink(['email' => $request->email]);

        return back()->with('success', __('Reset link sent if the email exists.'));
    })->middleware('throttle:5,1')->name('password.email');
    Route::get('/reset-password/{token}', fn ($token) => view('auth.reset-password', ['token' => $token]))->name('password.reset');
    Route::post('/reset-password', function (Request $request) {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|confirmed|min:8',
        ]);
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->forceFill(['password' => $password])->save();
            }
        );

        return $status === Password::PASSWORD_RESET
            ? redirect()->route('login')->with('success', __('Password reset!'))
            : back()->withErrors(['email' => __($status)]);
    })->middleware('throttle:3,1')->name('password.update');
});

// EU Login (CAS) — must be before the {provider} wildcard
Route::get('/auth/eulogin/redirect', [EuLoginController::class, 'redirect'])->name('auth.eulogin.redirect');
Route::get('/auth/eulogin/callback', [EuLoginController::class, 'callback'])->name('auth.eulogin.callback');

// OAuth
Route::get('/auth/{provider}/redirect', [SocialAuthController::class, 'redirect'])->name('auth.social.redirect');
Route::get('/auth/{provider}/callback', [SocialAuthController::class, 'callback'])->name('auth.social.callback');
Route::post('/auth/social/confirm-link', [SocialAuthController::class, 'confirmLink'])->middleware('auth')->name('auth.social.confirm-link');
Route::post('/auth/social/dismiss-link', [SocialAuthController::class, 'dismissLink'])->middleware('auth')->name('auth.social.dismiss-link');

// Email verification
Route::middleware('auth')->group(function () {
    Route::post('/push/subscribe', [PushSubscriptionController::class, 'store'])->name('push.subscribe');
    Route::post('/push/unsubscribe', [PushSubscriptionController::class, 'destroy'])->name('push.unsubscribe');

    Route::get('/email/verify', fn () => view('auth.verify-email'))->name('verification.notice');
    Route::get('/email/verify/{id}/{hash}', function (EmailVerificationRequest $request) {
        $request->fulfill();
        // Also mark user_emails as verified
        UserEmail::where('user_id', $request->user()->id)
            ->where('email', $request->user()->primary_email)
            ->update(['is_verified' => true]);

        return redirect()->route('profile.show')->with('success', __('Email verified!'));
    })->middleware('signed')->name('verification.verify');
    Route::post('/email/verification-notification', function (Request $request) {
        $request->user()->sendEmailVerificationNotification();

        return back()->with('success', __('Verification link sent!'));
    })->middleware('throttle:6,1')->name('verification.send');
});

// Logout
Route::post('/logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

// Password reset request (works for both guests and authenticated users)
Route::post('/request-password-reset', function (Request $request) {
    $request->validate(['email' => 'required|email']);
    Password::sendResetLink(['email' => $request->email]);

    return back()->with('success', __('Reset link sent if the email exists.'));
})->name('password.request.send');

// Authenticated + verified routes
Route::middleware(['auth', 'verified.email'])->group(function () {
    // Profile
    Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::post('/profile/info', [ProfileController::class, 'updateInfo'])->name('profile.update.info');
    Route::post('/profile/private', [ProfileController::class, 'updatePrivate'])->name('profile.update.private');
    Route::post('/profile/diving', [ProfileController::class, 'updateDiving'])->name('profile.update.diving');
    Route::post('/profile/federation-key/{licence}', [ProfileController::class, 'updateFederationKey'])->name('profile.update.federation-key');
    Route::post('/profile/language', [ProfileController::class, 'updateLanguage'])->name('profile.update.language');
    Route::post('/profile/document', [ProfileController::class, 'uploadDocument'])->name('profile.document.upload');
    Route::get('/profile/document/{document}', [ProfileController::class, 'downloadDocument'])->name('profile.document.download');
    Route::post('/profile/document/{document}/verify', [ProfileController::class, 'verifyCertificate'])->name('profile.document.verify');

    Route::post('/profile/avatar', [ProfileController::class, 'uploadAvatar'])->name('profile.avatar.upload');
    Route::delete('/profile/avatar', [ProfileController::class, 'deleteAvatar'])->name('profile.avatar.delete');

    // Certification levels
    Route::post('/profile/cert', [ProfileController::class, 'addCertification'])->name('profile.cert.add');
    Route::put('/profile/cert/{certLevel}', [ProfileController::class, 'updateCertification'])->name('profile.cert.update');
    Route::post('/profile/cert/{certLevel}/primary', [ProfileController::class, 'setPrimaryCert'])->name('profile.cert.primary');
    Route::delete('/profile/cert/{certLevel}', [ProfileController::class, 'removeCertification'])->name('profile.cert.remove');

    // Members directory (visible to all authenticated users)
    Route::get('/members', [MembersDirectoryController::class, 'directory'])->name('members.directory');
    Route::get('/members/trombinoscope', [MembersDirectoryController::class, 'trombinoscope'])->name('members.trombinoscope');

    // Contact member (no email exposed)
    Route::get('/members/{user}/contact', [ContactMemberController::class, 'create'])->name('contact.member');
    Route::post('/members/{user}/contact', [ContactMemberController::class, 'store'])->middleware('throttle:10,1')->name('contact.member.send');

    // Document browser (role-based visibility, upload for instructors/bureau)
    Route::get('/gallery', [DocumentBrowserController::class, 'gallery'])->name('gallery');
    Route::get('/documents', [DocumentBrowserController::class, 'index'])->name('documents.index');
    Route::get('/documents/{file}/download', [DocumentBrowserController::class, 'download'])->name('documents.download');
    Route::get('/documents/{file}/thumb', [DocumentBrowserController::class, 'thumb'])->name('documents.thumb');
    Route::post('/documents/upload', [DocumentBrowserController::class, 'upload'])->name('documents.upload');
    Route::post('/documents/folder', [DocumentBrowserController::class, 'createFolder'])->name('documents.create-folder');
    Route::put('/documents/{file}', [DocumentBrowserController::class, 'updateFile'])->name('documents.update');
    Route::delete('/documents/{file}', [DocumentBrowserController::class, 'destroy'])->name('documents.destroy');

    // GDPR
    Route::get('/privacy', [GdprController::class, 'consents'])->name('gdpr.consents');
    Route::post('/privacy/consent', [GdprController::class, 'updateConsent'])->name('gdpr.consent');
    Route::get('/privacy/export', [GdprController::class, 'exportData'])->name('gdpr.export');
    Route::get('/privacy/erasure', [GdprController::class, 'requestErasure'])->name('gdpr.erasure');
    Route::post('/privacy/erasure', [GdprController::class, 'confirmErasure'])->name('gdpr.erasure.confirm');

    // QR Codes
    Route::get('/qr/vcard', [QrCodeController::class, 'vcard'])->name('qr.vcard');
    Route::get('/qr/sepa/{payment}', [QrCodeController::class, 'sepa'])->name('qr.sepa');
    Route::get('/qr/federation/{licence}', [QrCodeController::class, 'federation'])->name('qr.federation');

    // Email management
    Route::post('/profile/email', [ProfileController::class, 'addEmail'])->name('profile.email.add');
    Route::post('/profile/email/{email}/primary', [ProfileController::class, 'setPrimaryEmail'])->name('profile.email.primary');
    Route::delete('/profile/email/{email}', [ProfileController::class, 'deleteEmail'])->name('profile.email.delete');

    // Events (calendar visible to all authenticated users)
    Route::get('/events', [EventController::class, 'index'])->name('events.index');

    // Classifieds (any member can post)
    Route::get('/classifieds', [ClassifiedController::class, 'index'])->name('classifieds.index');

    // Looking for Buddies
    Route::get('/buddies', [BuddyController::class, 'index'])->name('buddies.index');
    Route::post('/buddies', [BuddyController::class, 'store'])->name('buddies.store');
    Route::post('/buddies/{buddyRequest}/respond', [BuddyController::class, 'respond'])->name('buddies.respond');
    Route::post('/buddies/{buddyRequest}/close', [BuddyController::class, 'close'])->name('buddies.close');

    // Dive data import/export (UDDF + DAN DL7)
    Route::post('/dive-data/import-uddf', [DiveDataController::class, 'importUddf'])->name('dive-data.import-uddf');
    Route::get('/dive-data/export-uddf', [DiveDataController::class, 'exportUddf'])->name('dive-data.export-uddf');

    // Instructor Availability (bureau & instructors only)
    Route::middleware('role:bureau_master,bureau_finance,bureau_technical,instructor')->group(function () {
        Route::get('/availability', [InstructorAvailabilityController::class, 'index'])->name('availability.index');
        Route::post('/availability/toggle', [InstructorAvailabilityController::class, 'toggle'])->name('availability.toggle');
    });
    Route::get('/classifieds/create', [ClassifiedController::class, 'create'])->name('classifieds.create');
    Route::post('/classifieds', [ClassifiedController::class, 'store'])->name('classifieds.store');
    Route::get('/classifieds/{article}/edit', [ClassifiedController::class, 'edit'])->name('classifieds.edit');
    Route::put('/classifieds/{article}', [ClassifiedController::class, 'update'])->name('classifieds.update');
    Route::post('/classifieds/{article}/extend', [ClassifiedController::class, 'extend'])->name('classifieds.extend');
    Route::delete('/classifieds/{article}', [ClassifiedController::class, 'destroy'])->name('classifieds.destroy');

    // Article comments
    Route::post('/articles/{article}/comments', [CommentController::class, 'store'])->name('comments.store');
    Route::delete('/comments/{comment}', [CommentController::class, 'destroy'])->name('comments.destroy');
    Route::get('/events/create', [EventController::class, 'create'])->name('events.create');
    Route::post('/events', [EventController::class, 'store'])->name('events.store');
    Route::get('/events/{event}', [EventController::class, 'show'])->name('events.show');
    Route::get('/events/{event}/edit', [EventController::class, 'edit'])->name('events.edit');
    Route::put('/events/{event}', [EventController::class, 'update'])->name('events.update');
    Route::post('/events/{event}/register', [EventController::class, 'register'])->name('events.register');
    Route::post('/events/{event}/cancel-registration', [EventController::class, 'cancelRegistration'])->name('events.cancel-registration');
    Route::post('/events/{event}/cancel', [EventController::class, 'cancel'])->name('events.cancel');
    Route::post('/events/{event}/photos', [EventController::class, 'uploadPhoto'])->name('events.photo.upload');
    Route::delete('/events/{event}/photos/{photo}', [EventController::class, 'deletePhoto'])->name('events.photo.delete');

    // Dive groups (palanquées)
    Route::get('/events/{event}/dive-groups', [DiveGroupController::class, 'index'])->name('events.dive-groups');
    Route::post('/events/{event}/dive-groups', [DiveGroupController::class, 'store'])->name('events.dive-groups.store');
    Route::post('/dive-groups/{group}/members', [DiveGroupController::class, 'addMember'])->name('dive-groups.add-member');
    Route::delete('/dive-group-members/{member}', [DiveGroupController::class, 'removeMember'])->name('dive-groups.remove-member');
    Route::delete('/dive-groups/{group}', [DiveGroupController::class, 'destroy'])->name('dive-groups.destroy');
    Route::get('/events/{event}/dive-groups/validate', [DiveGroupController::class, 'validate_groups'])->name('events.dive-groups.validate');
    Route::get('/events/{event}/dive-groups/propose', [DiveGroupController::class, 'propose'])->name('events.dive-groups.propose');
    Route::post('/events/{event}/dive-groups/apply-proposal', [DiveGroupController::class, 'applyProposal'])->name('events.dive-groups.apply-proposal');
    Route::get('/events/{event}/dive-groups/suggest-swaps', [DiveGroupController::class, 'suggestSwaps'])->name('events.dive-groups.suggest-swaps');
    Route::get('/events/{event}/dive-groups/print', [DiveGroupController::class, 'printFiche'])->name('events.dive-groups.print');

    // Stop impersonation (must be outside bureau_master group — user is impersonated)
    Route::get('/admin/stop-impersonation', [MemberController::class, 'stopImpersonation'])->name('admin.stop-impersonation');

    // Admin routes (Bureau Master)
    Route::middleware('role:bureau_master')->prefix('admin')->name('admin.')->group(function () {
        Route::post('/homepage-layout', [HomepageLayoutController::class, 'saveLayout'])->name('homepage-layout.save');
        Route::get('/export-dan', [DiveDataController::class, 'exportDan'])->name('export-dan');
        Route::get('/members', [MemberController::class, 'index'])->name('members.index');
        Route::get('/members/{user}/profile', [ProfileController::class, 'show'])->name('profile.show');
        Route::post('/members/{user}/info', [ProfileController::class, 'updateInfo'])->name('profile.update.info');
        Route::post('/members/{user}/private', [ProfileController::class, 'updatePrivate'])->name('profile.update.private');
        Route::post('/members/{user}/impersonate', [MemberController::class, 'impersonate'])->name('impersonate');
        Route::post('/members/{user}/send-reset', function (User $user) {
            Password::sendResetLink(['email' => $user->primary_email]);

            return back()->with('success', __('Password reset link sent to :email', ['email' => $user->primary_email]));
        })->name('send-reset');

        Route::resource('articles', ArticleController::class)->except('show');
        Route::post('articles/{article}/translate', [ArticleController::class, 'translate'])->name('articles.translate');
        Route::resource('links', LinkController::class)->only(['index', 'store', 'destroy']);

        // Document Library
        Route::get('/library', [LibraryController::class, 'index'])->name('library.index');
        Route::post('/library/upload', [LibraryController::class, 'upload'])->name('library.upload');
        Route::put('/library/{file}', [LibraryController::class, 'update'])->name('library.update');
        Route::delete('/library/{file}', [LibraryController::class, 'destroy'])->name('library.destroy');
        Route::get('/library/{file}/download', [LibraryController::class, 'download'])->name('library.download');
        Route::get('/library/{file}/thumb', [ThumbnailController::class, 'show'])->name('library.thumb');
        Route::post('/library/folder', [LibraryController::class, 'createFolder'])->name('library.create-folder');
        Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
        Route::get('/audit-logs/export', [AuditLogController::class, 'export'])->name('audit-logs.export');
        Route::get('/audit-logs/{auditLog}', [AuditLogController::class, 'show'])->name('audit-logs.show');
        Route::post('/audit-logs/purge', [AuditLogController::class, 'purge'])->name('audit-logs.purge');
        Route::post('/audit-logs/retention', [AuditLogController::class, 'updateRetention'])->name('audit-logs.retention');

        // Backups
        Route::get('/backups', [BackupController::class, 'index'])->name('backups.index');
        Route::post('/backups', [BackupController::class, 'create'])->name('backups.create');
        Route::get('/backups/{filename}', [BackupController::class, 'show'])->name('backups.show')->where('filename', '.*\.tar\.gz');
        Route::get('/backups/{filename}/download', [BackupController::class, 'download'])->name('backups.download')->where('filename', '.*\.tar\.gz');
        Route::delete('/backups/{filename}', [BackupController::class, 'destroy'])->name('backups.destroy')->where('filename', '.*\.tar\.gz');

        // Trial requests
        Route::get('/trial-requests', [TrialRequestController::class, 'index'])->name('trial-requests.index');
        Route::put('/trial-requests/{trialRequest}', [TrialRequestController::class, 'update'])->name('trial-requests.update');

        // Guardians & Parental Consent
        Route::get('/guardians', [GuardianController::class, 'index'])->name('guardians.index');
        Route::post('/guardians/link', [GuardianController::class, 'linkGuardian'])->name('guardians.link');
        Route::delete('/guardians/{link}', [GuardianController::class, 'unlinkGuardian'])->name('guardians.unlink');
        Route::post('/guardians/consent', [GuardianController::class, 'storeConsent'])->name('guardians.consent');
        Route::delete('/guardians/consent/{consent}', [GuardianController::class, 'revokeConsent'])->name('guardians.consent.revoke');
        Route::get('/guardians/consent/{consent}/download', [GuardianController::class, 'downloadConsent'])->name('guardians.consent.download');

        // Dive Sites
        Route::get('/dive-sites', [DiveSiteController::class, 'index'])->name('dive-sites.index');
        Route::get('/dive-sites/create', [DiveSiteController::class, 'create'])->name('dive-sites.create');
        Route::post('/dive-sites', [DiveSiteController::class, 'store'])->name('dive-sites.store');
        Route::get('/dive-sites/{diveSite}/edit', [DiveSiteController::class, 'edit'])->name('dive-sites.edit');
        Route::put('/dive-sites/{diveSite}', [DiveSiteController::class, 'update'])->name('dive-sites.update');
        Route::delete('/dive-sites/{diveSite}', [DiveSiteController::class, 'destroy'])->name('dive-sites.destroy');

        // Dive Group Rules
        Route::get('/dive-group-rules', [DiveGroupRuleController::class, 'index'])->name('dive-group-rules.index');
        Route::post('/dive-group-rules', [DiveGroupRuleController::class, 'store'])->name('dive-group-rules.store');
        Route::put('/dive-group-rules/{rule}', [DiveGroupRuleController::class, 'update'])->name('dive-group-rules.update');
        Route::delete('/dive-group-rules/{rule}', [DiveGroupRuleController::class, 'destroy'])->name('dive-group-rules.destroy');

        // Annual Report
        Route::get('/annual-report', [AnnualReportController::class, 'show'])->name('annual-report');

        // Medical exports
        Route::get('/medical-export', [MedicalExportController::class, 'exportList'])->name('medical-export');
        Route::get('/medical-certificates', [MedicalExportController::class, 'downloadCertificates'])->name('medical-certificates');

        // Seasons
        Route::get('/seasons', [SeasonController::class, 'index'])->name('seasons.index');
        Route::get('/seasons/create', [SeasonController::class, 'create'])->name('seasons.create');
        Route::post('/seasons', [SeasonController::class, 'store'])->name('seasons.store');
        Route::get('/seasons/{season}', [SeasonController::class, 'show'])->name('seasons.show');
        Route::post('/seasons/{season}/activate', [SeasonController::class, 'activate'])->name('seasons.activate');
        Route::post('/seasons/{season}/holidays', [SeasonController::class, 'storeHoliday'])->name('seasons.holiday.store');
        Route::delete('/seasons/holidays/{holiday}', [SeasonController::class, 'destroyHoliday'])->name('seasons.holiday.destroy');
        Route::post('/seasons/{season}/patterns', [SeasonController::class, 'storePattern'])->name('seasons.pattern.store');
        Route::delete('/seasons/patterns/{pattern}', [SeasonController::class, 'destroyPattern'])->name('seasons.pattern.destroy');
        Route::get('/seasons/{season}/preview', [SeasonController::class, 'previewGeneration'])->name('seasons.preview');
        Route::post('/seasons/{season}/generate', [SeasonController::class, 'generateEvents'])->name('seasons.generate');

        // Settings
        Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
        Route::post('/settings/federation', [SettingsController::class, 'storeFederation'])->name('settings.federation.store');
        Route::put('/settings/federation/{federation}', [SettingsController::class, 'updateFederation'])->name('settings.federation.update');
        Route::delete('/settings/federation/{federation}', [SettingsController::class, 'destroyFederation'])->name('settings.federation.destroy');
        Route::post('/settings/status', [SettingsController::class, 'storeStatus'])->name('settings.status.store');
        Route::put('/settings/status/{status}', [SettingsController::class, 'updateStatus'])->name('settings.status.update');
        Route::post('/settings/medical-rule', [SettingsController::class, 'storeMedicalRule'])->name('settings.medical-rule.store');
        Route::put('/settings/medical-rule/{rule}', [SettingsController::class, 'updateMedicalRule'])->name('settings.medical-rule.update');
        Route::delete('/settings/medical-rule/{rule}', [SettingsController::class, 'destroyMedicalRule'])->name('settings.medical-rule.destroy');
        Route::post('/settings/maintenance-rule', [SettingsController::class, 'storeMaintenanceRule'])->name('settings.maintenance-rule.store');
        Route::put('/settings/maintenance-rule/{rule}', [SettingsController::class, 'updateMaintenanceRule'])->name('settings.maintenance-rule.update');
        Route::delete('/settings/maintenance-rule/{rule}', [SettingsController::class, 'destroyMaintenanceRule'])->name('settings.maintenance-rule.destroy');
        Route::post('/settings/theme', [SettingsController::class, 'updateTheme'])->name('settings.theme.update');
        Route::post('/settings/theme/preset', [SettingsController::class, 'applyPreset'])->name('settings.theme.preset');
        Route::post('/settings/theme/logo', [SettingsController::class, 'uploadLogo'])->name('settings.theme.logo');
        Route::post('/settings/membership-fee', [SettingsController::class, 'storeMembershipFee'])->name('settings.membership-fee.store');
        Route::delete('/settings/membership-fee/{fee}', [SettingsController::class, 'destroyMembershipFee'])->name('settings.membership-fee.destroy');

        // Dashboard
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard.index');
        Route::get('/dashboard/export', [DashboardController::class, 'exportCsv'])->name('dashboard.export');

        // Admin Guide
        Route::get('/guide', [GuideController::class, 'index'])->name('guide.index');
        Route::get('/guide/{section}', [GuideController::class, 'show'])->name('guide.show');

        // Payments
        Route::get('/payments', [PaymentController::class, 'index'])->name('payments.index');
        Route::get('/payments/components', [PaymentController::class, 'components'])->name('payments.components');
        Route::post('/payments/components', [PaymentController::class, 'storeComponent'])->name('payments.component.store');
        Route::delete('/payments/components/{component}', [PaymentController::class, 'destroyComponent'])->name('payments.component.destroy');
        Route::post('/payments/{user}/calculate', [PaymentController::class, 'calculateFee'])->name('payments.calculate');
        Route::post('/payments/{user}/generate', [PaymentController::class, 'generateFee'])->name('payments.generate');
        Route::post('/payments/generate-bulk', [PaymentController::class, 'generateBulkFees'])->name('payments.generate-bulk');
        Route::put('/payments/{payment}/adjust', [PaymentController::class, 'adjustComponents'])->name('payments.adjust');
        Route::get('/payments/reconciliation', [PaymentController::class, 'reconciliation'])->name('payments.reconciliation');
        Route::post('/payments/import-statement', [PaymentController::class, 'importStatement'])->name('payments.import-statement');
        Route::post('/payments/suggest-matches', [PaymentController::class, 'suggestMatches'])->name('payments.suggest-matches');
        Route::post('/payments/confirm/{transaction}', [PaymentController::class, 'confirmMatch'])->name('payments.confirm-match');
        Route::post('/payments/ignore/{transaction}', [PaymentController::class, 'ignoreTransaction'])->name('payments.ignore');

        // Equipment
        Route::get('/equipment', [EquipmentController::class, 'index'])->name('equipment.index');
        Route::get('/equipment/create', [EquipmentController::class, 'create'])->name('equipment.create');
        Route::post('/equipment', [EquipmentController::class, 'store'])->name('equipment.store');
        Route::get('/equipment/{equipment}', [EquipmentController::class, 'show'])->name('equipment.show');
        Route::put('/equipment/{equipment}', [EquipmentController::class, 'update'])->name('equipment.update');
        Route::post('/equipment/{equipment}/loan', [EquipmentController::class, 'loan'])->name('equipment.loan');
        Route::post('/equipment/return/{loan}', [EquipmentController::class, 'returnLoan'])->name('equipment.return');
        Route::post('/equipment/maintenance/{maintenance}/complete', [EquipmentController::class, 'completeMaintenance'])->name('equipment.maintenance.complete');

        // Email
        Route::get('/email', [EmailController::class, 'index'])->name('email.index');
        Route::post('/email/template', [EmailController::class, 'storeTemplate'])->name('email.template.store');
        Route::put('/email/template/{template}', [EmailController::class, 'updateTemplate'])->name('email.template.update');
        Route::delete('/email/template/{template}', [EmailController::class, 'destroyTemplate'])->name('email.template.destroy');
        Route::post('/email/preview', [EmailController::class, 'preview'])->name('email.preview');
        Route::post('/email/send', [EmailController::class, 'send'])->name('email.send');

        // Votes
        Route::get('/votes', [VoteController::class, 'index'])->name('votes.index');
        Route::get('/votes/create', [VoteController::class, 'create'])->name('votes.create');
        Route::post('/votes', [VoteController::class, 'store'])->name('votes.store');
        Route::get('/votes/{vote}', [VoteController::class, 'show'])->name('votes.show');
        Route::post('/votes/{vote}/tokens', [VoteController::class, 'generateTokens'])->name('votes.generate-tokens');
        Route::post('/votes/{vote}/open', [VoteController::class, 'open'])->name('votes.open');
        Route::post('/votes/{vote}/close', [VoteController::class, 'close'])->name('votes.close');
        Route::post('/votes/{vote}/cancel', [VoteController::class, 'cancel'])->name('votes.cancel');

        // Club Partnerships (inter-club federation)
        Route::get('/partnerships', [PartnershipController::class, 'index'])->name('partnerships.index');
        Route::get('/partnerships/create', [PartnershipController::class, 'create'])->name('partnerships.create');
        Route::post('/partnerships', [PartnershipController::class, 'store'])->name('partnerships.store');
        Route::delete('/partnerships/{partnership}', [PartnershipController::class, 'destroy'])->name('partnerships.destroy');
        Route::get('/partnerships/{partnership}/remote-events', [PartnershipController::class, 'remoteEvents'])->name('partnerships.remote-events');
        Route::get('/partnerships/registrations', [PartnershipController::class, 'registrations'])->name('partnerships.registrations');
        Route::post('/partnerships/registrations/{registration}/approve', [PartnershipController::class, 'approveRegistration'])->name('partnerships.registrations.approve');
        Route::post('/partnerships/registrations/{registration}/reject', [PartnershipController::class, 'rejectRegistration'])->name('partnerships.registrations.reject');
    });
});

// Offline page for PWA
Route::get('/offline', fn () => view('offline'))->name('offline');

// Staging mail viewer (only active when STAGING_MODE=true)
Route::prefix('staging-mail')->group(function () {
    Route::get('/', [StagingMailController::class, 'index'])->name('staging.mail.index');
    Route::get('/{mail}', [StagingMailController::class, 'show'])->name('staging.mail.show');
    Route::get('/{mail}/raw', [StagingMailController::class, 'raw'])->name('staging.mail.raw');
    Route::delete('/', [StagingMailController::class, 'clear'])->name('staging.mail.clear');
});

// Public voting (token-based, no login required)
Route::get('/vote/{token}', [VotePublicController::class, 'show'])->name('vote.show');
Route::post('/vote/{token}', [VotePublicController::class, 'cast'])->middleware('throttle:10,1')->name('vote.cast');

```

