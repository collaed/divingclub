<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DiveSite;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DiveSiteController extends Controller
{
    public function index()
    {
        $sites = DiveSite::orderBy('name')->get();
        return view('admin.dive-sites.index', compact('sites'));
    }

    public function create()
    {
        return view('admin.dive-sites.form', ['site' => new DiveSite]);
    }

    public function store(Request $request)
    {
        $data = $this->validate($request);
        if ($request->hasFile('image')) $data['image_path'] = $request->file('image')->store('dive-sites', 'public');
        if ($request->hasFile('map_image')) $data['map_image_path'] = $request->file('map_image')->store('dive-sites', 'public');
        if ($request->hasFile('site_plan')) $data['site_plan_path'] = $request->file('site_plan')->store('dive-sites', 'public');
        DiveSite::create($data);
        return redirect()->route('admin.dive-sites.index')->with('success', __('Dive site created.'));
    }

    public function edit(DiveSite $diveSite)
    {
        return view('admin.dive-sites.form', ['site' => $diveSite]);
    }

    public function update(Request $request, DiveSite $diveSite)
    {
        $data = $this->validate($request);
        foreach (['image' => 'image_path', 'map_image' => 'map_image_path', 'site_plan' => 'site_plan_path'] as $field => $col) {
            if ($request->hasFile($field)) {
                if ($diveSite->$col) Storage::disk('public')->delete($diveSite->$col);
                $data[$col] = $request->file($field)->store('dive-sites', 'public');
            }
        }
        $diveSite->update($data);
        return redirect()->route('admin.dive-sites.index')->with('success', __('Dive site updated.'));
    }

    public function destroy(DiveSite $diveSite)
    {
        if ($diveSite->image_path) Storage::disk('public')->delete($diveSite->image_path);
        $diveSite->delete();
        return back()->with('success', __('Dive site deleted.'));
    }

    private function validate(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:255',
            'country' => 'nullable|string|max:100',
            'region' => 'nullable|string|max:100',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'max_depth' => 'nullable|integer|min:1|max:300',
            'water_type' => 'nullable|in:' . implode(',', DiveSite::WATER_TYPES),
            'conditions' => 'nullable|string',
            'marine_life' => 'nullable|string',
            'safety_notes' => 'nullable|string',
            'access_notes' => 'nullable|string',
            'facilities' => 'nullable|string',
            'nearest_hospital' => 'nullable|string',
            'website_url' => 'nullable|url|max:500',
            'entry_fee' => 'nullable|numeric|min:0',
            'booking_url' => 'nullable|url|max:500',
            'image' => 'nullable|image|max:5120',
            'map_image' => 'nullable|image|max:5120',
            'site_plan' => 'nullable|file|mimes:jpg,jpeg,png,gif,svg,pdf|max:10240',
            'is_active' => 'boolean',
        ]);
    }
}
