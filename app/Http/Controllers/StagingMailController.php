<?php

namespace App\Http\Controllers;

use App\Models\EmailLog;
use Illuminate\Http\Request;

class StagingMailController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(config('app.staging_mode'), 404);

        $mails = EmailLog::where('status', 'staging_captured')
            ->orderByDesc('created_at')
            ->paginate(25);

        return view('staging.mailbox', compact('mails'));
    }

    public function show(EmailLog $mail)
    {
        abort_unless(config('app.staging_mode'), 404);
        abort_unless($mail->status === 'staging_captured', 404);

        return view('staging.mail-show', compact('mail'));
    }

    public function raw(EmailLog $mail)
    {
        abort_unless(config('app.staging_mode'), 404);
        abort_unless($mail->status === 'staging_captured', 404);

        return response($mail->body)->header('Content-Type', 'text/html');
    }

    public function clear()
    {
        abort_unless(config('app.staging_mode'), 404);

        EmailLog::where('status', 'staging_captured')->delete();

        return back()->with('success', 'Staging mailbox cleared.');
    }
}
