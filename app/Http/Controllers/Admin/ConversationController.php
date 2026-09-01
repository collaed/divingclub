<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreConversationRequest;
use App\Models\EmailLog;
use App\Models\Event;
use App\Models\MailConversation;
use App\Services\ConversationService;
use App\Services\MailBalancer;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Mail;

class ConversationController extends Controller
{
    /** Show the "start a conversation" screen with ranked prior recipients. */
    public function index(): View
    {
        $recentAddresses = MailConversation::query()
            ->selectRaw('external_email, max(external_name) as external_name, sum(hit_count) as hits')
            ->groupBy('external_email')
            ->orderByDesc('hits')
            ->limit(50)
            ->get();

        $events = Event::query()
            ->whereDate('event_date', '>=', now()->subMonths(2))
            ->orderByDesc('event_date')
            ->limit(100)
            ->get(['id', 'title', 'event_date']);

        return view('admin.conversations.index', [
            'recentAddresses' => $recentAddresses,
            'events' => $events,
        ]);
    }

    /** Start a proxied conversation and send the first message. */
    public function store(StoreConversationRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $initiator = $request->user();

        $conversation = ConversationService::start(
            $initiator,
            $data['external_email'],
            $data['subject'],
            isset($data['event_id']) ? (int) $data['event_id'] : null,
            $data['external_name'] ?? null,
        );

        $fromAddress = config('club.noreply_address');
        $fromName = trim($initiator->name.' via '.config('app.name'));
        $replyTo = array_values(array_filter([
            $conversation->sas_alias,
            config('club.log_mailbox'),
        ]));

        MailBalancer::configureForNext();

        Mail::html($data['message'], function ($mail) use ($conversation, $data, $fromAddress, $fromName, $replyTo): void {
            $mail->to($conversation->external_email)
                ->from($fromAddress, $fromName)
                ->subject($data['subject']);
            foreach ($replyTo as $address) {
                $mail->replyTo($address);
            }
        });

        EmailLog::create([
            'event_id' => $conversation->event_id,
            'user_id' => $initiator->id,
            'to_email' => $conversation->external_email,
            'alias' => $conversation->sas_alias,
            'from_email' => $fromAddress,
            'from_name' => $fromName,
            'subject' => $data['subject'],
            'body' => $data['message'],
            'status' => 'sent',
            'direction' => 'contact',
        ]);

        return redirect()
            ->route('admin.conversations.index')
            ->with('success', __('Message sent to :email.', ['email' => $conversation->external_email]));
    }
}
