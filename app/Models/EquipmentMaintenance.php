<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $equipment_id
 * @property string|null $maintenance_name
 * @property Carbon|null $due_date
 * @property Carbon|null $completed_at
 * @property string|null $completed_by
 * @property string|null $notes
 * @property bool $is_mandatory
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class EquipmentMaintenance extends Model
{
    protected $table = 'equipment_maintenance';

    protected $fillable = ['equipment_id', 'maintenance_name', 'due_date', 'completed_at', 'completed_by', 'notes', 'is_mandatory'];

    protected function casts(): array
    {
        return ['due_date' => 'date', 'completed_at' => 'date', 'is_mandatory' => 'boolean'];
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    public function completedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }
}
