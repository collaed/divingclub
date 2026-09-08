<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string $method
 * @property string $path
 * @property string|null $route_name
 * @property int|null $status
 * @property string|null $ip_address
 * @property Carbon|null $created_at
 * @property-read User|null $user
 */
class PageVisit extends Model
{
    /** @use HasFactory<\Database\Factories\PageVisitFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'method', 'path', 'route_name', 'status', 'ip_address'];

    protected function casts(): array
    {
        return [
            'status' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
