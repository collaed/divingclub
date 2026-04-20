<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\PaginatesFromRequest;
use App\Http\Controllers\Controller;
use App\Models\Equipment;
use App\Models\EquipmentLoan;
use App\Models\EquipmentMaintenance;
use App\Models\EquipmentMaintenanceRule;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EquipmentController extends Controller
{
    use PaginatesFromRequest;

    public function index(Request $request): View
    {
        $sortable = ['name' => 'name', 'type' => 'type', 'status' => 'status', 'short_number' => 'short_number', 'location' => 'location', 'last_seen_at' => 'last_seen_at'];
        $sort = $sortable[$request->get('sort')] ?? 'name';
        $dir = $request->get('dir', 'asc') === 'desc' ? 'desc' : 'asc';

        $equipment = Equipment::with(['currentLoan.user.detail'])
            ->when($request->type, fn ($q, $t) => $q->where('type', $t))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->location, fn ($q, $l) => $q->where('location', $l))
            ->when($request->size, fn ($q, $s) => $q->where('name', 'ILIKE', "%{$s}%"))
            ->when($request->input('search'), fn ($q, $s) => $q->where(function ($w) use ($s) {
                $w->where('name', 'ILIKE', "%{$s}%")
                    ->orWhere('serial_number', 'ILIKE', "%{$s}%")
                    ->orWhere('short_number', 'ILIKE', "%{$s}%")
                    ->orWhere('club_id', 'ILIKE', "%{$s}%");
            }))
            ->when($request->get('sort') === 'loaned_to', function ($q) use ($dir) {
                return $q->orderByRaw("CASE WHEN status = 'on_loan' THEN 0 ELSE 1 END $dir");
            }, fn ($q) => $q->orderBy($sort, $dir))
            ->paginate($this->perPage(30))->withQueryString();

        return view('admin.equipment.index', compact('equipment'));
    }

    public function create(): View
    {
        return view('admin.equipment.form', ['item' => new Equipment]);
    }

    public function store(Request $request): View
    {
        $v = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:bcd,regulator,tank,wetsuit,mask,fins,computer,other',
            'serial_number' => 'nullable|string|max:100',
            'purchase_date' => 'nullable|date',
            'condition' => 'required|in:new,good,fair,poor',
            'notes' => 'nullable|string',
        ]);

        $item = Equipment::create($v);

        // Auto-generate maintenance tasks from rules
        $rules = EquipmentMaintenanceRule::where('equipment_type', $item->type)->get();
        foreach ($rules as $rule) {
            EquipmentMaintenance::create([
                'equipment_id' => $item->id,
                'maintenance_name' => $rule->maintenance_name,
                'due_date' => now()->addMonths($rule->interval_months),
                'is_mandatory' => $rule->is_mandatory,
            ]);
        }

        return redirect()->route('admin.equipment.index')->with('success', __('Equipment added with :count maintenance tasks.', ['count' => $rules->count()]));
    }

    public function show(Equipment $equipment): View
    {
        $equipment->load(['maintenanceTasks' => fn ($q) => $q->orderBy('due_date'), 'loans' => fn ($q) => $q->with('user.detail')->orderByDesc('loaned_at')]);
        $members = User::with('detail')->whereHas('detail')->get()->sortBy(fn ($u) => $u->detail?->last_name);

        return view('admin.equipment.show', compact('equipment', 'members'));
    }

    public function update(Request $request, Equipment $equipment): RedirectResponse
    {
        $v = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|string',
            'serial_number' => 'nullable|string|max:100',
            'condition' => 'required|in:new,good,fair,poor',
            'status' => 'required|in:available,on_loan,maintenance_required,retired',
            'notes' => 'nullable|string',
            'location' => 'nullable|string|max:100',
            'short_number' => 'nullable|string|max:10',
            'is_loanable' => 'boolean',
            'is_child_sized' => 'boolean',
            'is_cold_water' => 'boolean',
        ]);
        $equipment->update($v);

        return back()->with('success', __('Equipment updated.'));
    }

    public function loan(Request $request, Equipment $equipment): RedirectResponse
    {
        if (! $equipment->isAvailable()) {
            return back()->with('error', __('Equipment is not available for loan.'));
        }

        $request->validate(['user_id' => 'required|exists:users,id', 'expected_return_date' => 'nullable|date|after:today']);

        EquipmentLoan::create([
            'equipment_id' => $equipment->id,
            'user_id' => $request->user_id,
            'loaned_at' => now(),
            'expected_return_date' => $request->expected_return_date,
            'loaned_by' => auth()->id(),
        ]);
        $equipment->update(['status' => 'on_loan']);

        return back()->with('success', __('Equipment loaned.'));
    }

    public function returnLoan(EquipmentLoan $loan): RedirectResponse
    {
        $loan->update(['returned_at' => now(), 'returned_by' => auth()->id()]);

        $status = ($loan->equipment instanceof Equipment && $loan->equipment->hasOverdueMaintenance()) ? 'maintenance_required' : 'available';
        $loan->equipment->update(['status' => $status, 'last_seen_at' => now()]);

        return back()->with('success', __('Equipment returned.'));
    }

    public function quickLoan(Request $request): RedirectResponse
    {
        $request->validate(['user_id' => 'required|exists:users,id']);
        $userId = $request->user_id;
        $loaned = 0;

        foreach (array_filter($request->input('equipment_ids', [])) as $id) {
            $eq = Equipment::findOrFail($id);
            if ($eq->isAvailable()) {
                EquipmentLoan::create([
                    'equipment_id' => $eq->id,
                    'user_id' => $userId,
                    'loaned_at' => now(),
                    'loaned_by' => auth()->id(),
                ]);
                $eq->update(['status' => 'on_loan', 'last_seen_at' => now()]);
                $loaned++;
            }
        }

        return back()->with('success', __(':count item(s) loaned.', ['count' => $loaned]));
    }

    public function completeMaintenance(EquipmentMaintenance $maintenance): RedirectResponse
    {
        $maintenance->update(['completed_at' => now(), 'completed_by' => auth()->id()]);

        // Schedule next maintenance
        $rule = EquipmentMaintenanceRule::where('equipment_type', $maintenance->equipment->type)
            ->where('maintenance_name', $maintenance->maintenance_name)->first();

        if ($rule) {
            EquipmentMaintenance::create([
                'equipment_id' => $maintenance->equipment_id,
                'maintenance_name' => $maintenance->maintenance_name,
                'due_date' => now()->addMonths($rule->interval_months),
                'is_mandatory' => $rule->is_mandatory,
            ]);
        }

        // Update equipment status if no more overdue
        if (! $maintenance->equipment->hasOverdueMaintenance() && $maintenance->equipment->status === 'maintenance_required') {
            $maintenance->equipment->update(['status' => $maintenance->equipment->currentLoan ? 'on_loan' : 'available']);
        }

        return back()->with('success', __('Maintenance completed. Next scheduled.'));
    }
}
