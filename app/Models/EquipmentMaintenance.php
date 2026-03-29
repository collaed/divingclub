<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
