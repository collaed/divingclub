<?php

/**
 * Dive data import/export: UDDF upload, UDDF download, DAN DL7 export.
 *
 * Members can upload UDDF files from their dive computers to populate
 * dive logs. Bureau can export all club dive data as UDDF or DAN DL7
 * for research submission.
 *
 * @author ClubCEP.eu
 */

namespace App\Http\Controllers;

use App\Models\DiveGroupMember;
use App\Models\Event;
use App\Services\DanExportService;
use App\Services\UddfService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DiveDataController extends Controller
{
    /**
     * Upload a UDDF file and match dives to events by date.
     * Members upload their own; bureau can upload for any member.
     */
    public function importUddf(Request $request): RedirectResponse
    {
        $request->validate(['uddf_file' => 'required|file|mimes:xml,uddf|max:10240']);

        $xml = file_get_contents($request->file('uddf_file')->getRealPath());
        $service = new UddfService;
        $parsed = $service->parse($xml);

        $matched = 0;
        foreach ($parsed['dives'] as $dive) {
            // Try to match to an event on the same date
            $event = Event::whereDate('event_date', $dive['datetime']->toDateString())->first();
            if (! $event) {
                continue;
            }

            // Find user's dive group in this event
            $dgm = DiveGroupMember::whereHas('diveGroup', fn ($q) => $q->where('event_id', $event->id))
                ->where('user_id', auth()->id())
                ->first();

            if (! $dgm) {
                continue;
            }

            // Update the dive group with actual data
            $group = $dgm->diveGroup;
            $group->update(array_filter([
                'actual_depth' => $dive['max_depth'] ?: null,
                'actual_duration' => $dive['duration_minutes'] ?: null,
            ]));

            $matched++;
        }

        return back()->with('success', __(':count dive(s) matched and updated from UDDF file.', ['count' => $matched]));
    }

    /** Export a user's dive history as UDDF XML. */
    public function exportUddf(Request $request): Response
    {
        $user = $request->user();
        $memberships = DiveGroupMember::where('user_id', $user->id)
            ->with(['diveGroup.event.diveSite'])
            ->get();

        $xml = (new UddfService)->export($user, $memberships);

        return response($xml, 200, [
            'Content-Type' => 'application/xml',
            'Content-Disposition' => 'attachment; filename="divelog_'.$user->id.'.uddf"',
        ]);
    }

    /** Export all club dive data as DAN DL7 (bureau only). */
    public function exportDan(Request $request): Response
    {
        $year = $request->input('year', now()->year);

        $memberships = DiveGroupMember::with(['user.detail', 'diveGroup.event.diveSite'])
            ->whereHas('diveGroup.event', fn ($q) => $q->whereYear('event_date', $year))
            ->get();

        $dl7 = (new DanExportService)->export($memberships);

        return response($dl7, 200, [
            'Content-Type' => 'text/plain',
            'Content-Disposition' => 'attachment; filename="dan_export_'.$year.'.dl7"',
        ]);
    }
}
