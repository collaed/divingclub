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
     * Check base eligibility (shared across platforms).
     */
    private function isBaseEligible(EventPhoto $photo): bool
    {
        if (! $photo->gdpr_consent || ! $photo->approved) {
            return false;
        }

        if (ThemeSetting::get('social_auto_publish') !== '1') {
            return false;
        }

        if ($photo->uploader?->hasPublicPhotosBanned()) {
            return false;
        }

        return true;
    }

    /**
     * Check if eligible for Facebook (private group).
     */
    public function isEligible(EventPhoto $photo): bool
    {
        return $this->isEligibleForFacebook($photo);
    }

    public function isEligibleForFacebook(EventPhoto $photo): bool
    {
        if (! $this->isBaseEligible($photo)) {
            return false;
        }

        if (ThemeSetting::get('fb_publish_enabled', '1') !== '1') {
            return false;
        }

        if (ThemeSetting::get('fb_group_is_closed') !== '1') {
            return false;
        }

        if (! config('services.facebook.page_token')) {
            return false;
        }

        return ! $this->alreadyPublished($photo, 'facebook');
    }

    /**
     * Check if eligible for Instagram (public — stricter rules).
     * No faces allowed since Instagram is public.
     */
    public function isEligibleForInstagram(EventPhoto $photo): bool
    {
        if (! $this->isBaseEligible($photo)) {
            return false;
        }

        if (ThemeSetting::get('ig_publish_enabled') !== '1') {
            return false;
        }

        if (! config('services.instagram.access_token')) {
            return false;
        }

        if (! ThemeSetting::get('ig_account_id')) {
            return false;
        }

        // Instagram is public: exclude photos with detected faces
        if ($photo->has_faces) {
            return false;
        }

        return ! $this->alreadyPublished($photo, 'instagram');
    }

    private function alreadyPublished(EventPhoto $photo, string $platform): bool
    {
        return SocialPublishLog::where('publishable_type', EventPhoto::class)
            ->where('publishable_id', $photo->id)
            ->where('platform', $platform)
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

        if (! $this->isEligibleForFacebook($photo)) {
            $log->update(['status' => 'failed', 'error_message' => 'Not eligible for Facebook']);

            return $log;
        }

        $groupId = ThemeSetting::get('fb_group_id');
        $token = config('services.facebook.page_token');

        try {
            $photoPath = Storage::disk('public')->path($photo->path);
            $message = $this->buildCaption($photo);

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
     * Publish a photo to Instagram via Graph API (container → publish flow).
     */
    public function publishToInstagram(EventPhoto $photo): SocialPublishLog
    {
        $log = SocialPublishLog::create([
            'platform' => 'instagram',
            'publishable_type' => EventPhoto::class,
            'publishable_id' => $photo->id,
            'status' => 'pending',
        ]);

        if (! $this->isEligibleForInstagram($photo)) {
            $log->update(['status' => 'failed', 'error_message' => 'Not eligible for Instagram']);

            return $log;
        }

        $accountId = ThemeSetting::get('ig_account_id');
        $token = config('services.instagram.access_token');
        $imageUrl = url('storage/'.$photo->path);
        $caption = $this->buildCaption($photo);

        try {
            // Step 1: Create media container
            $containerResponse = Http::post("https://graph.facebook.com/v19.0/{$accountId}/media", [
                'image_url' => $imageUrl,
                'caption' => $caption,
                'access_token' => $token,
            ]);

            if (! $containerResponse->successful()) {
                $log->update([
                    'status' => 'failed',
                    'error_message' => 'Container: '.$containerResponse->json('error.message', $containerResponse->body()),
                ]);

                return $log;
            }

            $containerId = $containerResponse->json('id');

            // Step 2: Publish the container
            $publishResponse = Http::post("https://graph.facebook.com/v19.0/{$accountId}/media_publish", [
                'creation_id' => $containerId,
                'access_token' => $token,
            ]);

            if ($publishResponse->successful()) {
                $log->update([
                    'status' => 'published',
                    'external_post_id' => $publishResponse->json('id'),
                    'published_at' => now(),
                ]);
            } else {
                $log->update([
                    'status' => 'failed',
                    'error_message' => 'Publish: '.$publishResponse->json('error.message', $publishResponse->body()),
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('Instagram publish failed', ['photo' => $photo->id, 'error' => $e->getMessage()]);
            $log->update(['status' => 'failed', 'error_message' => $e->getMessage()]);
        }

        return $log;
    }

    /**
     * Process all unpublished eligible photos across all platforms.
     */
    public function processQueue(): int
    {
        $published = 0;
        $photos = EventPhoto::where('gdpr_consent', true)
            ->where('approved', true)
            ->with(['event', 'uploader.detail'])
            ->limit(10)
            ->get();

        foreach ($photos as $photo) {
            if ($this->isEligibleForFacebook($photo)) {
                $result = $this->publishToFacebook($photo);
                if ($result->status === 'published') {
                    $published++;
                }
            }

            if ($this->isEligibleForInstagram($photo)) {
                $result = $this->publishToInstagram($photo);
                if ($result->status === 'published') {
                    $published++;
                }
            }
        }

        return $published;
    }

    private function buildCaption(EventPhoto $photo): string
    {
        $event = $photo->event;

        return $event->title.($photo->caption ? "\n".$photo->caption : '');
    }
}
