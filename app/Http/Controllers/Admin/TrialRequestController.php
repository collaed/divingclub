<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ThemeSetting;
use App\Models\TrialRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

/**
 * @author ClubCEP.eu
 */
class TrialRequestController extends Controller
{
    public function index(): View
    {
        $requests = TrialRequest::orderByDesc('created_at')->get();

        return view('admin.trial-requests.index', compact('requests'));
    }

    public function update(Request $request, TrialRequest $trialRequest): RedirectResponse
    {
        $data = $request->validate([
            'status' => 'required|in:pending,confirmed,completed,cancelled',
            'confirmed_date' => 'nullable|date',
            'admin_notes' => 'nullable|string|max:1000',
        ]);

        $oldStatus = $trialRequest->status;

        if ($data['status'] === 'confirmed') {
            $data['confirmed_by'] = auth()->id();
        }

        $trialRequest->update($data);

        // Email the applicant on status change
        if ($oldStatus !== $data['status']) {
            $this->notifyApplicant($trialRequest);
        }

        return back()->with('success', __('Trial request updated.'));
    }

    private function notifyApplicant(TrialRequest $tr): void
    {
        $clubName = ThemeSetting::get('club_full_name', config('app.name'));

        $body = match ($tr->status) {
            'confirmed' => __("Dear :name,\n\nYour trial dive request with :club has been confirmed for :date.\n\nPlease bring: swimsuit, towel, and a positive attitude! We provide all diving equipment.\n\nIf you have any questions, reply to this email.\n\nSee you soon!\n:club", [
                'name' => $tr->first_name,
                'club' => $clubName,
                'date' => $tr->confirmed_date?->format('d/m/Y') ?? __('a date to be confirmed'),
            ]),
            'cancelled' => __("Dear :name,\n\nUnfortunately, your trial dive request with :club has been cancelled.\n\nIf you'd like to reschedule, please submit a new request on our website or reply to this email.\n\nBest regards,\n:club", [
                'name' => $tr->first_name,
                'club' => $clubName,
            ]),
            'completed' => __("Dear :name,\n\nThank you for your trial dive with :club! We hope you enjoyed the experience.\n\nIf you'd like to join the club, you can register on our website.\n\nHappy bubbles!\n:club", [
                'name' => $tr->first_name,
                'club' => $clubName,
            ]),
            default => null,
        };

        if ($body) {
            $subject = match ($tr->status) {
                'confirmed' => __('Trial Dive Confirmed — :club', ['club' => $clubName]),
                'cancelled' => __('Trial Dive Update — :club', ['club' => $clubName]),
                'completed' => __('Thank You — :club', ['club' => $clubName]),
            };

            Mail::raw($body, fn ($m) => $m->to($tr->email)->subject($subject));
        }
    }
}
