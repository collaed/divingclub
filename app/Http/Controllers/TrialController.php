<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreTrialRequest;
use App\Models\TrialRequest;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Mail;

class TrialController extends Controller
{
    public function show(): RedirectResponse|View
    {
        return view('trial.show');
    }

    public function store(StoreTrialRequest $request): RedirectResponse
    {
        $data = $request->validated();
        unset($data['website']);

        // Timestamp check
        if (now()->timestamp - (int) $request->input('_ts', 0) < 3) {
            return back()->with('error', __('Please try again.'));
        }

        // Where the request came from (e.g. an article's inline form) — for the
        // bureau, kept out of the visitor-facing message.
        $source = trim((string) ($data['source'] ?? ''));
        unset($data['source']);
        if ($source !== '') {
            $data['admin_notes'] = $source;
        }

        $trialRequest = TrialRequest::create($data);

        $this->notifyBureau($trialRequest);

        return back()->with('success', __('Your request has been submitted! We will contact you to confirm a date and time.'));
    }

    private function notifyBureau(TrialRequest $trialRequest): void
    {
        $bureauEmails = User::role(['bureau_master', 'bureau_finance', 'bureau_technical'])
            ->pluck('primary_email');

        if ($bureauEmails->isEmpty()) {
            return;
        }

        Mail::raw(
            __(':name :last_name is interested in a trial dive (:email:phone).', [
                'name' => $trialRequest->first_name,
                'last_name' => $trialRequest->last_name,
                'email' => $trialRequest->email,
                'phone' => $trialRequest->phone ? ', '.$trialRequest->phone : '',
            ]),
            fn ($m) => $m->to($bureauEmails->all())->subject(__('New trial dive request'))
        );
    }
}
