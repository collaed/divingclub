<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\MailListContact;
use Illuminate\Contracts\View\View;

class MailListUnsubscribeController extends Controller
{
    public function show(string $token): View
    {
        $contact = MailListContact::where('unsubscribe_token', $token)->firstOrFail();

        if ($contact->subscribed) {
            $contact->update(['subscribed' => false]);
        }

        return view('mail-list.unsubscribed', ['email' => $contact->email]);
    }
}
