<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $recipient_id
 * @property string|null $ip_address
 * @property string|null $country_code
 * @property string|null $user_agent
 * @property Carbon|null $opened_at
 */
class DocumentDispatchOpen extends Model
{
    public $timestamps = false;

    protected $fillable = ['recipient_id', 'ip_address', 'country_code', 'user_agent', 'opened_at'];

    protected function casts(): array
    {
        return ['opened_at' => 'datetime'];
    }

    /** @return BelongsTo<DocumentDispatchRecipient, $this> */
    public function recipient(): BelongsTo
    {
        return $this->belongsTo(DocumentDispatchRecipient::class, 'recipient_id');
    }
}
