<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * An admin-defined mailing alias: members matching every selected criterion
 * (AND'd, see MailAliasCriteria) receive mail sent to this alias's tag. See
 * MailAliasService::resolve(), which checks this table before falling back
 * to its own hardcoded aliases.
 *
 * @property int $id
 * @property string $tag
 * @property string $label
 * @property array<int, string> $criteria
 * @property string $auth_level
 * @property bool $enabled
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class MailAliasDefinition extends Model
{
    protected $fillable = ['tag', 'label', 'criteria', 'auth_level', 'enabled'];

    protected function casts(): array
    {
        return ['criteria' => 'array', 'enabled' => 'boolean'];
    }
}
