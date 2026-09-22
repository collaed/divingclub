<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $slug
 * @property string $label
 * @property string $kind
 * @property string|null $direction
 * @property string|null $description
 * @property bool $proposed
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class LedgerTag extends Model
{
    public const KIND_FIXED = 'fixed';

    public const KIND_VARIABLE = 'variable';

    public const DIRECTION_IN = 'in';

    public const DIRECTION_OUT = 'out';

    protected $fillable = ['slug', 'label', 'kind', 'direction', 'description', 'proposed', 'sort_order'];

    protected function casts(): array
    {
        return ['proposed' => 'boolean'];
    }
}
