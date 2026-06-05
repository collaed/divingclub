<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string|null $name
 * @property string|null $slug
 * @property string|null $subject
 * @property string|null $body
 * @property string|null $locale
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class EmailTemplate extends Model
{
    protected $fillable = ['name', 'slug', 'subject', 'body', 'locale'];
}
