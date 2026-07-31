<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\EmailLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class ContactMemberController extends Controller
{
    public function create(User $user): RedirectResponse|View
    {
        abort_if($user->id === auth()->id(), 403);

        return view('contact-member', ['target' => $user]);
    }

    public function store(Request $request, User $user): RedirectResponse
    {
        abort_if($user->id === auth()->id(), 403);

        $data = $request->validate([
            'subject' => 'required|string|max:200',
            'message' => 'required|string|max:5000',
        ]);

        $sender = auth()->user();
        $replyTo = $sender->primary_email;
        $senderName = $sender->username ?? $sender->primary_email;

        Mail::raw($data['message'], function ($mail) use ($user, $replyTo, $senderName, $data): void {
            $mail->to($user->primary_email)
                ->replyTo($replyTo, $senderName)
                ->subject($data['subject']);
        });

        EmailLog::create([
            'user_id' => $sender->id,
            'to_email' => $user->primary_email,
            'from_email' => $replyTo,
            'from_name' => $senderName,
            'subject' => $data['subject'],
            'body' => $data['message'],
            'status' => 'sent',
            'direction' => 'contact',
        ]);

        return back()->with('success', __('Message sent to :name.', ['name' => $user->username]));
    }
}
