## social-publishing.md — Social Media Auto-Publishing

## Overview

Automatically publishes approved event photos to Facebook (closed group) and Instagram (public). Different eligibility rules per platform: Facebook allows faces (private group), Instagram excludes faces (public). Uses Meta Graph API v19.0.

## Data Model

| Table | Purpose | Key Columns |
|-------|---------|-------------|
| `social_publish_logs` | Publication tracking | id, platform, publishable_type, publishable_id, external_post_id, status, error_message, published_at |
| `event_photos` (fields) | Source content | has_faces, approved, gdpr_consent |

## Platforms

### Facebook (Closed Group)
- Publishes to club's private Facebook group
- **Allows photos with faces** (group is closed, members only)
- Config: `fb_group_id`, `fb_publish_enabled`, `fb_group_is_closed` (must be '1')
- Credentials: `config('services.facebook.page_token')`

### Instagram (Public)
- Publishes to club's Instagram account
- **Excludes photos with faces** (`has_faces = true` → ineligible)
- Uses two-step container→publish flow (Graph API requirement)
- Config: `ig_account_id`, `ig_publish_enabled`
- Credentials: `config('services.instagram.access_token')`
- Requires publicly accessible image URL for container creation

## Eligibility Rules

### Base Eligibility (both platforms)
1. `photo.gdpr_consent = true`
2. `photo.approved = true`
3. `ThemeSetting::get('social_auto_publish') = '1'`
4. Uploader has not set `public_photos_banned`

### Facebook-specific
5. `fb_publish_enabled = '1'`
6. `fb_group_is_closed = '1'` (safety check — only post to closed groups)
7. `page_token` configured
8. Not already published to Facebook

### Instagram-specific
5. `ig_publish_enabled = '1'`
6. `access_token` configured
7. `ig_account_id` configured
8. **`photo.has_faces = false`** (critical — public platform, no faces without explicit consent)
9. Not already published to Instagram

## Service: `SocialPublishService`

| Method | Purpose |
|--------|---------|
| `isEligibleForFacebook(EventPhoto)` | Check FB eligibility |
| `isEligibleForInstagram(EventPhoto)` | Check IG eligibility |
| `publishToFacebook(EventPhoto)` | Upload photo to FB group |
| `publishToInstagram(EventPhoto)` | Two-step IG publish |
| `processQueue()` | Batch publish up to 10 eligible photos |

### `processQueue()` Flow
1. Query up to 10 approved+consented EventPhotos
2. For each photo, check Facebook eligibility → publish if eligible
3. For each photo, check Instagram eligibility → publish if eligible
4. Return count of successful publications

### Facebook Publish Flow
```
POST https://graph.facebook.com/v19.0/{group_id}/photos
  - multipart: source (file contents), message (caption), access_token
  → Success: save external_post_id, status=published
  → Failure: save error_message, status=failed
```

### Instagram Publish Flow (two-step)
```
Step 1: POST /v19.0/{account_id}/media
  - image_url (public URL), caption, access_token
  → Returns container_id

Step 2: POST /v19.0/{account_id}/media_publish
  - creation_id (container_id), access_token
  → Returns published media_id
```

## Log Status Flow

```
pending → published (success)
pending → failed (API error, not eligible)
```

## Caption Format

```
{event.title}
{photo.caption}   ← if present
```

## Configuration (theme_settings keys)

| Key | Purpose |
|-----|---------|
| `social_auto_publish` | Master switch (1/0) |
| `fb_publish_enabled` | Facebook toggle |
| `fb_group_id` | Facebook group ID |
| `fb_group_is_closed` | Safety flag — only publish to closed groups |
| `ig_publish_enabled` | Instagram toggle |
| `ig_account_id` | Instagram Business Account ID |

## Scheduled Execution

Not a standalone scheduled job — triggered by `processQueue()` which can be called from a command or scheduled task. The daily 09:00 social publish is configured in the schedule.
