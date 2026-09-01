<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\Auditable;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A mail alias owned by (or associated with) a member.
 *
 * Types:
 *   - member:     the member's stable, human-readable club alias (e.g. sas.jdupont)
 *   - sas_static: a legacy static vanity alias retained for reference
 *   - sas_conv:   a per-conversation proxy alias (sas+conv.{token})
 *
 * @author  ClubCEP.eu
 *
 * @property int $id
 * @property int|null $user_id
 * @property string $alias
 * @property string $type
 * @property bool $active
 * @property int $hit_count
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $user
 */
class MailAlias extends Model
{
    use Auditable;
    use HasFactory;

    protected $fillable = ['user_id', 'alias', 'type', 'active', 'hit_count'];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'hit_count' => 'integer',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
