<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ImportFfessmBrevetsRequest;
use App\Services\FfessmBrevetImportService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class FfessmBrevetImportController extends Controller
{
    public function __construct(private FfessmBrevetImportService $importer) {}

    public function index(): View
    {
        return view('admin.ffessm-brevet-import.index');
    }

    public function store(ImportFfessmBrevetsRequest $request): RedirectResponse
    {
        $summaries = [];
        foreach ($request->file('files') as $file) {
            try {
                $summaries[] = ['file' => $file->getClientOriginalName()] + $this->importer->import($file->getRealPath(), $file->getClientOriginalName());
            } catch (\RuntimeException $e) {
                return back()->withErrors(['files' => $file->getClientOriginalName().': '.$e->getMessage()]);
            }
        }

        $certs = array_sum(array_column($summaries, 'certs_created')) + array_sum(array_column($summaries, 'certs_updated'));
        $licences = array_sum(array_column($summaries, 'licences_updated'));
        $unmatched = [];
        $unmatchedLevels = [];
        foreach ($summaries as $summary) {
            $unmatched = [...$unmatched, ...$summary['unmatched_names'], ...$summary['ambiguous_names']];
            $unmatchedLevels = [...$unmatchedLevels, ...$summary['unmatched_levels']];
        }
        $unmatched = array_values(array_unique($unmatched));
        $unmatchedLevels = array_values(array_unique($unmatchedLevels));

        $message = __(':certs certification(s) recorded, :licences licence number(s) updated across :files file(s).', [
            'certs' => $certs, 'licences' => $licences, 'files' => count($summaries),
        ]);
        if ($unmatched !== [] || $unmatchedLevels !== []) {
            $message .= ' '.__('Not applied — needs a manual look: :names', [
                'names' => implode('; ', array_filter([
                    $unmatched !== [] ? count($unmatched).' '.__('unmatched/ambiguous name(s)').' ('.implode(', ', $unmatched).')' : null,
                    $unmatchedLevels !== [] ? count($unmatchedLevels).' '.__('unrecognised level(s)').' ('.implode(', ', $unmatchedLevels).')' : null,
                ])),
            ]);

            return back()->with('warning', $message);
        }

        return back()->with('success', $message);
    }
}
