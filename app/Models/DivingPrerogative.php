<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string $type
 * @property int $max_depth
 * @property bool $requires_adult
 */
class DivingPrerogative extends Model
{
    protected $fillable = ['code', 'name', 'type', 'max_depth', 'requires_adult'];

    protected function casts(): array
    {
        return ['requires_adult' => 'boolean'];
    }

    /** @return BelongsToMany<CertificationLevel, $this> */
    public function certificationLevels(): BelongsToMany
    {
        return $this->belongsToMany(CertificationLevel::class, 'certification_prerogatives');
    }
}
