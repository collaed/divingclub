<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EquipmentMaintenanceRule extends Model
{
    protected $fillable = ['equipment_type', 'maintenance_name', 'interval_months', 'is_mandatory', 'regulation_reference'];

    protected function casts(): array
    {
        return ['is_mandatory' => 'boolean'];
    }
}
