<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * An admin-defined mailing alias: members matching every selected criterion
 * (AND'd, see MailAliasCriteria) receive mail sent to any of this alias's
 * tags. See MailAliasService::resolve(), which checks this table before
 * falling back to its own hardcoded aliases.
 *
 * A definition can carry several tags (e.g. "bureau" + "board") so that
 * synonyms share one criteria/auth_level and can never drift apart — see
 * MailAliasController's "tags" field. include_user_ids/exclude_user_ids add
 * named member exceptions on top of (or instead of) the criteria match —
 * see MailAliasCriteria::matchingUsers(). include_contact_ids adds external,
 * non-member addresses (App\Models\MailListContact) the same way — see
 * MailAliasService::resolveDefinition().
 *
 * @property int $id
 * @property array<int, string> $tags
 * @property string $label
 * @property array<int, string> $criteria
 * @property array<int, int> $include_user_ids
 * @property array<int, int> $exclude_user_ids
 * @property array<int, int> $include_contact_ids
 * @property string $auth_level
 * @property bool $enabled
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class MailAliasDefinition extends Model
{
    /**
     * Who may send to this alias:
     *  - bureau / bureau_or_instructor: the usual admin/instructor gate.
     *  - group_or_bureau: a member the alias itself resolves to, or bureau.
     *  - any_member: any registered club member.
     *  - open: no authentication at all — any Internet address.
     *
     * @var array<string, string>
     */
    public const AUTH_LEVELS = [
        'bureau' => 'Bureau only',
        'bureau_or_instructor' => 'Bureau or instructor',
        'group_or_bureau' => 'A member of this group, or bureau',
        'any_member' => 'Any club member',
        'open' => 'Anyone (no sender check)',
    ];

    protected $fillable = ['tags', 'label', 'criteria', 'include_user_ids', 'exclude_user_ids', 'include_contact_ids', 'auth_level', 'enabled'];

    /** Default raw (pre-cast) values — create() omitting these still gets a valid empty JSON array, not NULL. */
    protected $attributes = ['include_user_ids' => '[]', 'exclude_user_ids' => '[]', 'include_contact_ids' => '[]'];

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'criteria' => 'array',
            'include_user_ids' => 'array',
            'exclude_user_ids' => 'array',
            'include_contact_ids' => 'array',
            'enabled' => 'boolean',
        ];
    }
}
