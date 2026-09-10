<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFederationRequest;
use App\Models\CertificationLevel;
use App\Models\Federation;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class FederationController extends Controller
{
    public function index(): View
    {
        return view('admin.federations.index', [
            'federations' => Federation::withCount(['certificationLevels', 'licences'])
                ->orderBy('acronym')->get(),
        ]);
    }

    public function store(StoreFederationRequest $request): RedirectResponse
    {
        Federation::create($request->validated());

        return back()->with('success', __('Federation added.'));
    }

    /** Save every federation row in one submit. */
    public function bulkUpdate(Request $request): RedirectResponse
    {
        $rows = $request->validate([
            'fed' => 'array',
            'fed.*.acronym' => 'required|string|max:20',
            'fed.*.full_name' => 'required|string|max:255',
            'fed.*.visibility' => 'required|in:active,recognized,invisible',
        ])['fed'] ?? [];

        $acronyms = array_map('mb_strtoupper', array_column($rows, 'acronym'));
        if (count($acronyms) !== count(array_unique($acronyms))) {
            return back()->withErrors(['fed' => __('Two federations cannot share an acronym.')]);
        }

        Federation::whereKey(array_keys($rows))->get()->each(
            fn (Federation $f) => $f->update($rows[$f->id])
        );

        return back()->with('success', __('Federations saved.'));
    }

    public function destroy(Federation $federation): RedirectResponse
    {
        if ($federation->licences()->exists()) {
            return back()->withErrors(['fed' => __(':acronym still has member licences — reassign or remove them first.', ['acronym' => $federation->acronym])]);
        }
        if ($federation->certificationLevels()->whereHas('users')->exists()) {
            return back()->withErrors(['fed' => __(':acronym still has certification levels held by members — clear those first.', ['acronym' => $federation->acronym])]);
        }

        $federation->certificationLevels()->delete();
        $federation->delete();

        return back()->with('success', __('Federation deleted.'));
    }

    public function show(Federation $federation): View
    {
        return view('admin.federations.show', [
            'federation' => $federation,
            'levels' => $federation->certificationLevels()
                ->withCount('users')
                ->orderBy('category')->orderBy('rank')->get(),
            'categories' => ['diver', 'instructor', 'specialty'],
        ]);
    }

    public function storeLevel(Request $request, Federation $federation): RedirectResponse
    {
        $data = $request->validate([
            'code' => 'required|string|max:30',
            'name' => 'required|string|max:255',
            'category' => 'required|in:diver,instructor,specialty',
            'rank' => 'required|integer|min:0|max:65535',
            'equivalence_group' => 'nullable|string|max:30',
        ]);

        if ($federation->certificationLevels()->where('code', $data['code'])->exists()) {
            return back()->withErrors(['level' => __('This federation already has a level with that code.')]);
        }

        $federation->certificationLevels()->create($data);

        return back()->with('success', __('Certification level added.'));
    }

    /** Save every certification-level row for this federation in one submit. */
    public function bulkUpdateLevels(Request $request, Federation $federation): RedirectResponse
    {
        $rows = $request->validate([
            'lvl' => 'array',
            'lvl.*.code' => 'required|string|max:30',
            'lvl.*.name' => 'required|string|max:255',
            'lvl.*.category' => 'required|in:diver,instructor,specialty',
            'lvl.*.rank' => 'required|integer|min:0|max:65535',
            'lvl.*.equivalence_group' => 'nullable|string|max:30',
        ])['lvl'] ?? [];

        $codes = array_map('mb_strtoupper', array_column($rows, 'code'));
        if (count($codes) !== count(array_unique($codes))) {
            return back()->withErrors(['level' => __('Two levels cannot share a code.')]);
        }

        $federation->certificationLevels()->whereKey(array_keys($rows))->get()->each(
            fn (CertificationLevel $l) => $l->update($rows[$l->id])
        );

        return back()->with('success', __('Certification levels saved.'));
    }

    public function destroyLevel(Federation $federation, CertificationLevel $level): RedirectResponse
    {
        abort_unless($level->federation_id === $federation->id, 404);

        if ($level->users()->exists()) {
            return back()->withErrors(['level' => __(':code is held by :n member(s) — remove it from their profiles first.', ['code' => $level->code, 'n' => $level->users()->count()])]);
        }

        $level->delete();

        return back()->with('success', __('Certification level deleted.'));
    }
}
