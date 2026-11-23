<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MailListContact;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MailListContactController extends Controller
{
    public function index(): View
    {
        return view('admin.mail-list-contacts.index', [
            'contacts' => MailListContact::orderBy('email')->get(),
        ]);
    }

    /** Bulk-add from a textarea: one entry per line, "email" or "Name <email>". */
    public function store(Request $request): RedirectResponse|JsonResponse
    {
        abort_unless(auth()->user()?->hasRole('bureau_master'), 403);

        $v = $request->validate(['entries' => 'required|string']);

        $added = 0;
        $skipped = 0;

        foreach (preg_split('/\r?\n/', $v['entries']) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            [$name, $email] = $this->parseEntry($line);
            if (! $email || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $skipped++;

                continue;
            }

            $contact = MailListContact::firstOrCreate(
                ['email' => $email],
                ['name' => $name]
            );

            $contact->wasRecentlyCreated ? $added++ : $skipped++;
        }

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'added' => $added,
                'skipped' => $skipped,
                'html' => view('admin.mail-list-contacts._rows', [
                    'contacts' => MailListContact::orderBy('email')->get(),
                    'isBureauMaster' => true,
                ])->render(),
            ]);
        }

        return back()->with('success', __(':added added, :skipped skipped (already on the list or invalid).', ['added' => $added, 'skipped' => $skipped]));
    }

    public function toggle(Request $request, MailListContact $mailListContact): RedirectResponse|JsonResponse
    {
        abort_unless(auth()->user()?->hasRole('bureau_master'), 403);

        $mailListContact->update(['subscribed' => ! $mailListContact->subscribed]);

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'subscribed' => $mailListContact->subscribed]);
        }

        return back()->with('success', __('Updated.'));
    }

    public function destroy(Request $request, MailListContact $mailListContact): RedirectResponse|JsonResponse
    {
        abort_unless(auth()->user()?->hasRole('bureau_master'), 403);

        $mailListContact->delete();

        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return back()->with('success', __('Contact removed.'));
    }

    /** @return array{0: ?string, 1: ?string} [name, email] */
    private function parseEntry(string $line): array
    {
        if (preg_match('/^(.*)<(.+)>$/', $line, $m)) {
            return [trim($m[1]) ?: null, trim($m[2])];
        }

        return [null, $line];
    }
}
