<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * An action item extracted from a compte-rendu, shown on the kanban board.
 *
 * @property int $id
 * @property string $title
 * @property string|null $responsible
 * @property string|null $context
 * @property string $status
 * @property string $source_document_name
 * @property string|null $source_document_folder
 * @property Carbon|null $source_document_date
 * @property Carbon|null $discarded_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class KanbanCard extends Model
{
    public const STATUS_TODO = 'todo';

    public const STATUS_DOING = 'doing';

    public const STATUS_DONE = 'done';

    /** @var array<int, string> */
    public const STATUSES = [self::STATUS_TODO, self::STATUS_DOING, self::STATUS_DONE];

    protected $fillable = ['title', 'responsible', 'context', 'status', 'source_document_name', 'source_document_folder', 'source_document_date', 'discarded_at'];

    protected function casts(): array
    {
        return ['source_document_date' => 'date', 'discarded_at' => 'datetime'];
    }

    public function scopeActive(Builder $query): void
    {
        $query->whereNull('discarded_at');
    }
}
