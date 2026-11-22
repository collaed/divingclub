<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\Auditable;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $requester_id
 * @property int $invitee_id
 * @property string $status
 * @property Carbon|null $confirmed_at
 */
class FamilyInvite extends Model
{
    use Auditable;

    protected $fillable = ['requester_id', 'invitee_id', 'status', 'confirmed_at'];

    protected function casts(): array
    {
        return ['confirmed_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    /** @return BelongsTo<User, $this> */
    public function invitee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invitee_id');
    }
}
