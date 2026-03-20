<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Equipment extends Model
{
    protected $table = 'equipment';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'purchase_date' => 'date',
            'manufacture_date' => 'date',
            'last_retest_date' => 'date',
            'next_retest_date' => 'date',
            'last_inventory_date' => 'date',
            'weight_kg' => 'decimal:1',
        ];
    }

    public function maintenanceTasks()
    {
        return $this->hasMany(EquipmentMaintenance::class);
    }

    public function loans()
    {
        return $this->hasMany(EquipmentLoan::class);
    }

    public function currentLoan()
    {
        return $this->hasOne(EquipmentLoan::class)->whereNull('returned_at');
    }

    public function hasOverdueMaintenance(): bool
    {
        return $this->maintenanceTasks()->where('is_mandatory', true)->whereNull('completed_at')->where('due_date', '<', now())->exists();
    }

    public function isAvailable(): bool
    {
        return $this->status === 'available';
    }

    public function needsRetest(): bool
    {
        return $this->next_retest_date && $this->next_retest_date->isPast();
    }
}
