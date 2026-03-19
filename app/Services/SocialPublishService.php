<?php

namespace App\Services;

use App\Models\EventPhoto;
use App\Models\SocialPublishLog;
use App\Models\ThemeSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class SocialPublishService
{
    /**
     * Check if a photo is eligible for auto-publish.
     * Conditions: author GDPR consent + club FB group is closed + auto-publish enabled + admin approved photo
     */
    public function isEligible(EventPhoto $photo): bool
    {
        if (!$photo->gdpr_consent) return false;
        if (!$photo->approved) return false;
        if (ThemeSetting::get('social_auto_publish') !== '1') return false;
        if (ThemeSetting::get('fb_group_is_closed') !== '1') return false;
        if (!config('services.facebook.page_token')) return false;

        // Already published?
        return !SocialPublishLog::where('publishable_type', EventPhoto::class)
            ->where('publishable_id', $photo->id)
            ->where('status', 'published')
            ->exists();
    }

    /**
     * Publish a photo to Facebook group.
     */
    public function publishToFacebook(EventPhoto $photo): SocialPublishLog
    {
        $log = SocialPublishLog::create([
            'platform' => 'facebook',
            'publishable_type' => EventPhoto::class,
            'publishable_id' => $photo->id,
            'status' => 'pending',
        ]);

        if (!$this->isEligible($photo)) {
            $log->update(['status' => 'failed', 'error_message' => 'Not eligible for publishing']);
            return $log;
        }

        $groupId = ThemeSetting::get('fb_group_id');
        $token = config('services.facebook.page_token');

        if (!$groupId || !$token) {
            $log->update(['status' => 'failed', 'error_message' => 'Missing Facebook group ID or token']);
            return $log;
        }

        try {
            $photoPath = Storage::disk('public')->path($photo->path);
            $event = $photo->event;
            $message = $event->title . ($photo->caption ? "\n" . $photo->caption : '');

            $response = Http::attach('source', file_get_contents($photoPath), basename($photo->path))
                ->post("https://graph.facebook.com/v19.0/{$groupId}/photos", [
                    'message' => $message,
                    'access_token' => $token,
                ]);

            if ($response->successful()) {
                $log->update([
                    'status' => 'published',
                    'external_post_id' => $response->json('id'),
                    'published_at' => now(),
                ]);
            } else {
                $log->update([
                    'status' => 'failed',
                    'error_message' => $response->json('error.message', $response->body()),
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('Facebook publish failed', ['photo' => $photo->id, 'error' => $e->getMessage()]);
            $log->update(['status' => 'failed', 'error_message' => $e->getMessage()]);
        }

        return $log;
    }

    /**
     * Process all unpublished eligible photos.
     */
    public function processQueue(): int
    {
        $published = 0;
        $photos = EventPhoto::where('gdpr_consent', true)
            ->where('approved', true)
            ->whereDoesntHave('socialPublishLogs', fn($q) => $q->where('status', 'published'))
            ->with('event')
            ->limit(10)
            ->get();

        foreach ($photos as $photo) {
            if ($this->isEligible($photo)) {
                $result = $this->publishToFacebook($photo);
                if ($result->status === 'published') $published++;
            }
        }

        return $published;
    }
}
