<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EquipmentMaintenanceRule;
use App\Models\ThemeSetting;
use App\Services\ThemeService;
use App\Models\Federation;
use App\Models\MedicalComplianceRule;
use App\Models\MemberStatus;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function index()
    {
        return view('admin.settings.index', [
            'federations' => Federation::orderBy('acronym')->get(),
            'statuses' => MemberStatus::orderBy('name')->get(),
            'medicalRules' => MedicalComplianceRule::with('federation')->orderBy('federation_id')->orderBy('age_bracket_low')->get(),
            'maintenanceRules' => EquipmentMaintenanceRule::orderBy('equipment_type')->get(),
            'themeSettings' => ThemeSetting::all_settings(),
            'themePresets' => ThemeService::presets(),
            'membershipFees' => \App\Models\MembershipFee::with('status')->orderBy('season_year', 'desc')->orderBy('status_id')->get(),
        ]);
    }

    // --- Federations ---
    public function storeFederation(Request $request)
    {
        $v = $request->validate(['acronym' => 'required|string|max:20|unique:federations', 'full_name' => 'required|string|max:255']);
        Federation::create($v);
        return back()->with('success', __('Federation added.'));
    }

    public function updateFederation(Request $request, Federation $federation)
    {
        $v = $request->validate(['acronym' => 'required|string|max:20|unique:federations,acronym,' . $federation->id, 'full_name' => 'required|string|max:255']);
        $federation->update($v);
        return back()->with('success', __('Federation updated.'));
    }

    public function destroyFederation(Federation $federation)
    {
        $federation->delete();
        return back()->with('success', __('Federation deleted.'));
    }

    // --- Member Statuses ---
    public function storeStatus(Request $request)
    {
        $v = $request->validate(['name' => 'required|string|max:100', 'slug' => 'required|string|max:50|unique:member_statuses', 'description' => 'nullable|string']);
        MemberStatus::create($v);
        return back()->with('success', __('Status added.'));
    }

    public function updateStatus(Request $request, MemberStatus $status)
    {
        $v = $request->validate(['name' => 'required|string|max:100', 'description' => 'nullable|string']);
        $status->update($v);
        return back()->with('success', __('Status updated.'));
    }

    // --- Medical Compliance Rules ---
    public function storeMedicalRule(Request $request)
    {
        $v = $request->validate([
            'federation_id' => 'required|exists:federations,id',
            'age_bracket_low' => 'required|integer|min:0',
            'age_bracket_high' => 'required|integer|min:0|gte:age_bracket_low',
            'cert_type' => 'required|string|in:gp,ent,cardio,ophthalmologist,other',
            'validity_months' => 'required|integer|min:1',
        ]);
        MedicalComplianceRule::create($v);
        return back()->with('success', __('Medical rule added.'));
    }

    public function updateMedicalRule(Request $request, MedicalComplianceRule $rule)
    {
        $v = $request->validate([
            'federation_id' => 'required|exists:federations,id',
            'age_bracket_low' => 'required|integer|min:0',
            'age_bracket_high' => 'required|integer|min:0|gte:age_bracket_low',
            'cert_type' => 'required|string|in:gp,ent,cardio,ophthalmologist,other',
            'validity_months' => 'required|integer|min:1',
        ]);
        $rule->update($v);
        return back()->with('success', __('Medical rule updated.'));
    }

    public function destroyMedicalRule(MedicalComplianceRule $rule)
    {
        $rule->delete();
        return back()->with('success', __('Medical rule deleted.'));
    }

    // --- Equipment Maintenance Rules ---
    public function storeMaintenanceRule(Request $request)
    {
        $v = $request->validate([
            'equipment_type' => 'required|string|max:100',
            'maintenance_name' => 'required|string|max:255',
            'interval_months' => 'required|integer|min:1',
            'is_mandatory' => 'boolean',
            'regulation_reference' => 'nullable|string|max:255',
        ]);
        $v['is_mandatory'] = $request->boolean('is_mandatory');
        EquipmentMaintenanceRule::create($v);
        return back()->with('success', __('Maintenance rule added.'));
    }

    public function updateMaintenanceRule(Request $request, EquipmentMaintenanceRule $rule)
    {
        $v = $request->validate([
            'equipment_type' => 'required|string|max:100',
            'maintenance_name' => 'required|string|max:255',
            'interval_months' => 'required|integer|min:1',
            'is_mandatory' => 'boolean',
            'regulation_reference' => 'nullable|string|max:255',
        ]);
        $v['is_mandatory'] = $request->boolean('is_mandatory');
        $rule->update($v);
        return back()->with('success', __('Maintenance rule updated.'));
    }

    public function destroyMaintenanceRule(EquipmentMaintenanceRule $rule)
    {
        $rule->delete();
        return back()->with('success', __('Maintenance rule deleted.'));
    }

    // --- Membership Fees (absolute amounts per status per year) ---
    public function storeMembershipFee(Request $request)
    {
        $v = $request->validate([
            'season_year' => 'required|string|max:10',
            'status_id' => 'required|exists:member_statuses,id',
            'amount' => 'required|numeric|min:0',
            'label' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);
        \App\Models\MembershipFee::updateOrCreate(
            ['season_year' => $v['season_year'], 'status_id' => $v['status_id']],
            $v
        );
        return back()->with('success', __('Membership fee saved.'));
    }

    public function destroyMembershipFee(\App\Models\MembershipFee $fee)
    {
        $fee->delete();
        return back()->with('success', __('Membership fee deleted.'));
    }

    public function updateTheme(Request $request)
    {
        $allowed = ['primary_color','secondary_color','accent_color','header_gradient_start','header_gradient_end','footer_bg','body_bg','body_color','logo_text','logo_emoji','logo_accent_text','logo_plain_text','club_full_name','layout_width','card_style','header_bubbles','preset','club_iban','club_bic','club_email','club_address','club_phone','club_country','warehouse_address','warehouse_lat','warehouse_lon','club_short_code','social_auto_publish','fb_group_is_closed','fb_group_id','license_key'];
        foreach ($allowed as $key) {
            if ($request->has($key)) ThemeSetting::set($key, $request->input($key));
        }
        \Illuminate\Support\Facades\Cache::forget('theme_css');
        \Illuminate\Support\Facades\Cache::forget('theme_settings');
        return back()->with('success', __('Theme updated.'));
    }

    public function applyPreset(Request $request)
    {
        $presets = ThemeService::presets();
        $name = $request->input('preset');
        if (!isset($presets[$name])) return back()->with('error', __('Unknown preset.'));
        foreach ($presets[$name] as $k => $v) ThemeSetting::set($k, $v);
        ThemeSetting::set('preset', $name);
        \Illuminate\Support\Facades\Cache::forget('theme_css');
        \Illuminate\Support\Facades\Cache::forget('theme_settings');
        return back()->with('success', __('Preset applied: ') . $name);
    }

    public function uploadLogo(Request $request)
    {
        $request->validate(['logo' => 'required|image|max:2048']);
        $path = $request->file('logo')->store('theme', 'public');
        ThemeSetting::set('logo_image', $path);
        \Illuminate\Support\Facades\Cache::forget('theme_settings');
        return back()->with('success', __('Logo uploaded.'));
    }
}
