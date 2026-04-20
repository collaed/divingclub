<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int|null $club_id
 * @property string|null $name
 * @property string|null $short_number
 * @property string|null $brand
 * @property string|null $manufacturer
 * @property string|null $threading
 * @property Carbon|null $manufacture_date
 * @property float $weight_kg
 * @property string|null $volume
 * @property string|null $material
 * @property string|null $test_pressure_bar
 * @property string|null $working_pressure_bar
 * @property Carbon|null $last_retest_date
 * @property Carbon|null $next_retest_date
 * @property Carbon|null $last_inventory_date
 * @property string|null $type
 * @property string|null $serial_number
 * @property Carbon|null $purchase_date
 * @property string|null $condition
 * @property string|null $status
 * @property bool $is_loanable
 * @property bool $is_child_sized
 * @property bool $is_cold_water
 * @property Carbon|null $last_seen_at
 * @property string|null $notes
 * @property string|null $location
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read EquipmentLoan|null $currentLoan
 * @property-read Collection $loans
 */
class Equipment extends Model
{
    use SoftDeletes;

    protected $table = 'equipment';

    protected $fillable = ['club_id', 'name', 'short_number', 'brand', 'manufacturer', 'threading', 'manufacture_date', 'weight_kg', 'volume', 'material', 'test_pressure_bar', 'working_pressure_bar', 'last_retest_date', 'next_retest_date', 'last_inventory_date', 'type', 'serial_number', 'purchase_date', 'condition', 'status', 'is_loanable', 'is_child_sized', 'is_cold_water', 'last_seen_at', 'notes', 'location'];

    protected function casts(): array
    {
        return [
            'purchase_date' => 'date',
            'manufacture_date' => 'date',
            'last_retest_date' => 'date',
            'next_retest_date' => 'date',
            'last_inventory_date' => 'date',
            'last_seen_at' => 'date',
            'weight_kg' => 'decimal:1',
            'is_loanable' => 'boolean',
            'is_child_sized' => 'boolean',
            'is_cold_water' => 'boolean',
        ];
    }

    /** @return HasMany<EquipmentMaintenance, $this> */
    public function maintenanceTasks(): HasMany
    {
        return $this->hasMany(EquipmentMaintenance::class);
    }

    /** @return HasMany<EquipmentLoan, $this> */
    public function loans(): HasMany
    {
        return $this->hasMany(EquipmentLoan::class);
    }

    /** @return HasOne<EquipmentLoan, $this> */
    public function currentLoan(): HasOne
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

    public function lastSeenDate(): ?string
    {
        $lastLoan = $this->loans()->max('loaned_at');
        $lastReturn = $this->loans()->max('returned_at');
        $inventory = $this->last_inventory_date?->format('Y-m-d');

        return collect([$lastLoan, $lastReturn, $inventory])->filter()->max();
    }
}
