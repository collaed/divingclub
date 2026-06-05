<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string|null $equipment_type
 * @property string|null $maintenance_name
 * @property string|null $interval_months
 * @property bool $is_mandatory
 * @property string|null $regulation_reference
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class EquipmentMaintenanceRule extends Model
{
    protected $fillable = ['equipment_type', 'maintenance_name', 'interval_months', 'is_mandatory', 'regulation_reference'];

    protected function casts(): array
    {
        return ['is_mandatory' => 'boolean'];
    }
}
