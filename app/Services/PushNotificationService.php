<?php

namespace App\Services;

use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Support\Collection;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

class PushNotificationService
{
    private ?WebPush $webPush = null;

    public function __construct()
    {
        $publicKey = config('webpush.public_key');
        $privateKey = config('webpush.private_key');

        if ($publicKey && $privateKey) {
            try {
                $this->webPush = new WebPush([
                    'VAPID' => [
                        'subject' => config('app.url'),
                        'publicKey' => $publicKey,
                        'privateKey' => $privateKey,
                    ],
                ]);
                $this->webPush->setAutomaticPadding(false);
            } catch (\Exception $e) {
                // Invalid VAPID keys — push disabled
            }
        }
    }

    /**
     * Send a push notification to a single user.
     */
    public function sendToUser(User $user, string $title, string $body, ?string $url = null, ?string $icon = null): void
    {
        $this->sendToUsers(collect([$user]), $title, $body, $url, $icon);
    }

    /**
     * Send a push notification to multiple users.
     */
    public function sendToUsers(Collection $users, string $title, string $body, ?string $url = null, ?string $icon = null): void
    {
        if (! $this->webPush) {
            return;
        }

        $payload = json_encode([
            'title' => $title,
            'body' => $body,
            'url' => $url ?? '/',
            'icon' => $icon ?? '/images/icon-192.png',
        ]);

        $subscriptions = PushSubscription::whereIn('user_id', $users->pluck('id'))->get();

        foreach ($subscriptions as $sub) {
            $this->webPush->queueNotification(
                Subscription::create([
                    'endpoint' => $sub->endpoint,
                    'keys' => ['p256dh' => $sub->p256dh, 'auth' => $sub->auth],
                ]),
                $payload
            );
        }

        $stale = [];
        foreach ($this->webPush->flush() as $report) {
            if ($report->isSubscriptionExpired()) {
                $stale[] = $report->getEndpoint();
            }
        }

        if ($stale) {
            PushSubscription::whereIn('endpoint', $stale)->delete();
        }
    }

    /**
     * Send to all users with a given role.
     */
    public function sendToRole(string $roleSlug, string $title, string $body, ?string $url = null): void
    {
        $users = User::whereHas('role', fn ($q) => $q->where('slug', $roleSlug))->get();
        $this->sendToUsers($users, $title, $body, $url);
    }

    /**
     * Send to all bureau members.
     */
    public function sendToBureau(string $title, string $body, ?string $url = null): void
    {
        $users = User::role(['bureau_master', 'bureau_finance', 'bureau_technical'])->get();
        $this->sendToUsers($users, $title, $body, $url);
    }

    /**
     * Send to all members (everyone with a push subscription).
     */
    public function sendToAll(string $title, string $body, ?string $url = null): void
    {
        $users = User::whereHas('pushSubscriptions')->get();
        $this->sendToUsers($users, $title, $body, $url);
    }
}
