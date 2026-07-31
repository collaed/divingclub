<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ContactFormRequest;
use App\Models\ThemeSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Mail;

class ContactController extends Controller
{
    public function send(ContactFormRequest $request): RedirectResponse
    {
        // Honeypot check
        if ($request->filled('website')) {
            return back()->with('success', __('Message sent. Thank you!'));
        }

        $data = $request->validated();
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
