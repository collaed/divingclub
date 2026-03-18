<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DiveGroupRule;
use App\Models\Federation;
use Illuminate\Http\Request;

class DiveGroupRuleController extends Controller
{
    public function index()
    {
        $rules = DiveGroupRule::orderBy('scope')->orderBy('dive_mode')->orderBy('min_leader_rank')->get();
        $federations = Federation::orderBy('acronym')->pluck('acronym');
        return view('admin.dive-group-rules.index', compact('rules', 'federations'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'scope' => 'required|string|max:50',
            'diver_condition' => 'required|string|max:50',
            'dive_mode' => 'required|in:supervised,autonomous,training,certification',
            'min_leader_rank' => 'required|integer|min:0',
            'leader_category' => 'required|in:instructor,diver',
            'max_depth' => 'nullable|integer|min:1|max:300',
            'max_group_size' => 'required|integer|min:1|max:10',
            'description' => 'nullable|string',
        ]);
        DiveGroupRule::create($data);
        return back()->with('success', __('Rule created.'));
    }

    public function update(Request $request, DiveGroupRule $rule)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'scope' => 'required|string|max:50',
            'diver_condition' => 'required|string|max:50',
            'dive_mode' => 'required|in:supervised,autonomous,training,certification',
            'min_leader_rank' => 'required|integer|min:0',
            'leader_category' => 'required|in:instructor,diver',
            'max_depth' => 'nullable|integer|min:1|max:300',
            'max_group_size' => 'required|integer|min:1|max:10',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);
        $data['is_active'] = $request->boolean('is_active');
        $rule->update($data);
        return back()->with('success', __('Rule updated.'));
    }

    public function destroy(DiveGroupRule $rule)
    {
        $rule->delete();
        return back()->with('success', __('Rule deleted.'));
    }
}
