<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Equipment extends Model
{
    use SoftDeletes;

    protected $table = 'equipment';

    protected $fillable = ['club_id', 'name', 'short_number', 'brand', 'manufacturer', 'threading', 'manufacture_date', 'weight_kg', 'volume', 'material', 'test_pressure_bar', 'working_pressure_bar', 'last_retest_date', 'next_retest_date', 'last_inventory_date', 'type', 'serial_number', 'purchase_date', 'condition', 'status', 'is_loanable', 'notes', 'location'];

    protected function casts(): array
    {
        return [
            'purchase_date' => 'date',
            'manufacture_date' => 'date',
            'last_retest_date' => 'date',
            'next_retest_date' => 'date',
            'last_inventory_date' => 'date',
            'weight_kg' => 'decimal:1',
            'is_loanable' => 'boolean',
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
