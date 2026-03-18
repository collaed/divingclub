<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class EquipmentMaintenance extends Model
{
    protected $table = 'equipment_maintenance';
    protected $guarded = ['id'];
    protected function casts(): array { return ['due_date' => 'date', 'completed_at' => 'date', 'is_mandatory' => 'boolean']; }
    public function equipment() { return $this->belongsTo(Equipment::class); }
    public function completedByUser() { return $this->belongsTo(User::class, 'completed_by'); }
}
