<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line in a card's progress log — who wrote it and when, shown next to
 * their initials rather than repeating their full name on every line.
 *
 * @property int $id
 * @property int $kanban_card_id
 * @property int|null $user_id
 * @property string $body
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class KanbanCardComment extends Model
{
    protected $fillable = ['kanban_card_id', 'user_id', 'body'];

    /** @return BelongsTo<KanbanCard, $this> */
    public function card(): BelongsTo
    {
        return $this->belongsTo(KanbanCard::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
