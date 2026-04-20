<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\PaginatesFromRequest;
use App\Models\EmailLog;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class StagingMailController extends Controller
{
    use PaginatesFromRequest;

    public function index(Request $request): View
    {
        abort_unless(config('app.staging_mode'), 404);

        $mails = EmailLog::where('status', 'staging_captured')
            ->orderByDesc('created_at')
            ->paginate($this->perPage(25));

        return view('staging.mailbox', compact('mails'));
    }

    public function show(EmailLog $mail): View
    {
        abort_unless(config('app.staging_mode'), 404);
        abort_unless($mail->status === 'staging_captured', 404);

        return view('staging.mail-show', compact('mail'));
    }

    public function raw(EmailLog $mail): RedirectResponse
    {
        abort_unless(config('app.staging_mode'), 404);
        abort_unless($mail->status === 'staging_captured', 404);

        return response($mail->body)->header('Content-Type', 'text/html');
    }

    public function clear(): RedirectResponse
    {
        abort_unless(config('app.staging_mode'), 404);

        EmailLog::where('status', 'staging_captured')->delete();

        return back()->with('success', 'Staging mailbox cleared.');
    }
}
