<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * A predefined comment reviewers can pick from when validating with
 * comments or rejecting a medical certificate — admin-configurable so the
 * bureau doesn't retype the same reasons ("scan illegible", "date missing")
 * every time. Reviewers can still type free text instead.
 *
 * @property int $id
 * @property string $text
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class MedicalReviewComment extends Model
{
    protected $fillable = ['text', 'sort_order'];
}
