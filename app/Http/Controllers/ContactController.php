<?php

namespace App\Http\Controllers;

use App\Models\ThemeSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class ContactController extends Controller
{
    public function send(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'subject' => 'required|string|max:255',
            'message' => 'required|string|max:5000',
        ]);

        // Honeypot check
        if ($request->filled('website')) {
            return back()->with('success', __('Message sent. Thank you!'));
        }

        $to = ThemeSetting::get('club_email', config('mail.from.address'));

        Mail::raw(
            "From: {$data['name']} <{$data['email']}>\n\n{$data['message']}",
            fn ($msg) => $msg->to($to)
                ->replyTo($data['email'], $data['name'])
                ->subject("[Contact] {$data['subject']}")
        );

        return back()->with('success', __('Message sent. Thank you!'));
    }
}
