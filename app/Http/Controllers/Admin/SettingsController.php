<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFederationRequest;
use App\Http\Requests\StoreMaintenanceRuleRequest;
use App\Http\Requests\StoreMedicalRuleRequest;
use App\Http\Requests\StoreMembershipFeeRequest;
use App\Models\EquipmentMaintenanceRule;
use App\Models\Federation;
use App\Models\MedicalComplianceRule;
use App\Models\MembershipFee;
use App\Models\MemberStatus;
use App\Models\ThemeSetting;
use App\Services\LicenseService;
use App\Services\ThemeService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class SettingsController extends Controller
{
    public function index(): View
    {
        return view('admin.settings.index', [
            'federations' => Federation::orderBy('acronym')->get(),
            'statuses' => MemberStatus::orderBy('name')->get(),
            'medicalRules' => MedicalComplianceRule::with('federation')->orderBy('federation_id')->orderBy('age_bracket_low')->get(),
            'maintenanceRules' => EquipmentMaintenanceRule::orderBy('equipment_type')->get(),
            'themeSettings' => ThemeSetting::all_settings(),
            'themePresets' => ThemeService::presets(),
            'membershipFees' => MembershipFee::with('status')->orderBy('season_year', 'desc')->orderBy('status_id')->get(),
        ]);
    }

    // --- Federations ---
    public function storeFederation(StoreFederationRequest $request): RedirectResponse
    {
        Federation::create($request->validated());

        return back()->with('success', __('Federation added.'));
    }

    public function updateFederation(StoreFederationRequest $request, Federation $federation): RedirectResponse
    {
        $federation->update($request->validated());

        return back()->with('success', __('Federation updated.'));
    }

    public function destroyFederation(Federation $federation): RedirectResponse
    {
        $federation->delete();

        return back()->with('success', __('Federation deleted.'));
    }

    // --- Member Statuses ---
    public function storeStatus(Request $request): RedirectResponse
    {
        $v = $request->validate(['name' => 'required|string|max:100', 'slug' => 'required|string|max:50|unique:member_statuses', 'description' => 'nullable|string']);
        MemberStatus::create($v);

        return back()->with('success', __('Status added.'));
    }

    public function updateStatus(Request $request, MemberStatus $status): RedirectResponse
    {
        $v = $request->validate(['name' => 'required|string|max:100', 'description' => 'nullable|string']);
        $status->update($v);

        return back()->with('success', __('Status updated.'));
    }

    // --- Medical Compliance Rules ---
    public function storeMedicalRule(StoreMedicalRuleRequest $request): RedirectResponse
    {
        MedicalComplianceRule::create($request->validated());

        return back()->with('success', __('Medical rule added.'));
    }

    public function updateMedicalRule(StoreMedicalRuleRequest $request, MedicalComplianceRule $rule): RedirectResponse
    {
        $rule->update($request->validated());

        return back()->with('success', __('Medical rule updated.'));
    }

    public function destroyMedicalRule(MedicalComplianceRule $rule): RedirectResponse
    {
        $rule->delete();

        return back()->with('success', __('Medical rule deleted.'));
    }

    // --- Equipment Maintenance Rules ---
    public function storeMaintenanceRule(StoreMaintenanceRuleRequest $request): RedirectResponse
    {
        $v = $request->validated();
        $v['is_mandatory'] = $request->boolean('is_mandatory');
        EquipmentMaintenanceRule::create($v);

        return back()->with('success', __('Maintenance rule added.'));
    }

    public function updateMaintenanceRule(StoreMaintenanceRuleRequest $request, EquipmentMaintenanceRule $rule): RedirectResponse
    {
        $v = $request->validated();
        $v['is_mandatory'] = $request->boolean('is_mandatory');
        $rule->update($v);

        return back()->with('success', __('Maintenance rule updated.'));
    }

    public function destroyMaintenanceRule(EquipmentMaintenanceRule $rule): RedirectResponse
    {
        $rule->delete();

        return back()->with('success', __('Maintenance rule deleted.'));
    }

    // --- Membership Fees ---
    public function storeMembershipFee(StoreMembershipFeeRequest $request): RedirectResponse
    {
        $v = $request->validated();
        MembershipFee::updateOrCreate(
            ['season_year' => $v['season_year'], 'status_id' => $v['status_id']],
            $v
        );

        return back()->with('success', __('Membership fee saved.'));
    }

    public function destroyMembershipFee(MembershipFee $fee): RedirectResponse
    {
        $fee->delete();

        return back()->with('success', __('Membership fee deleted.'));
    }

    public function updateTheme(Request $request): RedirectResponse
    {
        $allowed = ['primary_color', 'secondary_color', 'accent_color', 'header_gradient_start', 'header_gradient_end', 'footer_bg', 'body_bg', 'body_color', 'logo_text', 'logo_emoji', 'logo_accent_text', 'logo_plain_text', 'club_full_name', 'layout_width', 'card_style', 'header_bubbles', 'preset', 'club_iban', 'club_bic', 'club_email', 'club_address', 'club_phone', 'club_country', 'warehouse_address', 'warehouse_lat', 'warehouse_lon', 'club_short_code', 'social_auto_publish', 'fb_group_is_closed', 'fb_group_id', 'fb_publish_enabled', 'ig_publish_enabled', 'ig_account_id', 'license_key', 'ui_style', 'ui_show_icons', 'training_locations', 'social_facebook', 'social_instagram', 'social_youtube', 'social_tiktok', 'social_whatsapp', 'social_x', 'newsletter_article_base_url', 'default_locale'];

        // Handle enabled_locales checkbox array separately
        if ($request->has('enabled_locales')) {
            $locales = array_intersect($request->input('enabled_locales', []), array_keys(config('languages', [])));
            ThemeSetting::set('enabled_locales', json_encode(array_values($locales)));
        }
        foreach ($allowed as $key) {
            if ($request->has($key)) {
                ThemeSetting::set($key, $request->input($key));
            }
        }
        Cache::forget('theme_css');
        Cache::forget('theme_settings');
        if ($request->has('license_key')) {
            LicenseService::flushCache();
        }

        return back()->with('success', __('Theme updated.'));
    }

    public function applyPreset(Request $request): RedirectResponse
    {
        $presets = ThemeService::presets();
        $name = $request->input('preset');
        if (! isset($presets[$name])) {
            return back()->with('error', __('Unknown preset.'));
        }
        foreach ($presets[$name] as $k => $v) {
            ThemeSetting::set($k, $v);
        }
        ThemeSetting::set('preset', $name);
        Cache::forget('theme_css');
        Cache::forget('theme_settings');

        return back()->with('success', __('Preset applied: ').$name);
    }

    public function uploadLogo(Request $request): RedirectResponse
    {
        $request->validate(['logo' => 'required|image|max:2048']);
        $path = $request->file('logo')->store('theme', 'public');
        ThemeSetting::set('logo_image', $path);
        Cache::forget('theme_settings');

        return back()->with('success', __('Logo uploaded.'));
    }
}
