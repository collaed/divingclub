## pwa-push.md — PWA & Push Notifications

## Overview

The application is installable as a Progressive Web App with offline page support and push notifications via the Web Push protocol (VAPID). Uses `minishlink/web-push` library.

## PWA Components

| File | Purpose |
|------|---------|
| `public/manifest.json` | Web app manifest (name, icons, theme color, start_url) |
| `public/sw.js` | Service worker — caching, offline fallback page |
| `public/offline.html` | Displayed when user is offline and page not cached |
| `public/images/icon-192.png` | PWA icon (192×192) |
| `public/images/icon-512.png` | PWA icon (512×512) |

## Push Notification Architecture

### Data Model

| Table | Purpose | Key Columns |
|-------|---------|-------------|
| `push_subscriptions` | Browser push endpoints | user_id, endpoint (unique, varchar 500), p256dh, auth |

### Configuration

```env
WEBPUSH_PUBLIC_KEY=BL...    # VAPID public key
WEBPUSH_PRIVATE_KEY=...     # VAPID private key
```

Keys generated once via: `vendor/bin/generate-vapid-keys` (or stored in config).

## `PushNotificationService`

| Method | Purpose |
|--------|---------|
| `sendToUser(User, title, body, ?url, ?icon)` | Push to one user |
| `sendToUsers(Collection, title, body, ?url, ?icon)` | Push to multiple users |
| `sendToRole(roleSlug, title, body, ?url)` | Push to all users with a role |
| `sendToBureau(title, body, ?url)` | Push to bureau_master + bureau_finance + bureau_technical |
| `sendToAll(title, body, ?url)` | Push to all users with active subscriptions |

### Payload Format
```json
{
  "title": "New Event",
  "body": "Pool training Wednesday 18:30",
  "url": "/events/42",
  "icon": "/images/icon-192.png"
}
```

### Stale Subscription Cleanup
After each `flush()`, expired subscriptions (410 Gone from browser vendor) are automatically deleted from the database.

## Subscription Flow

1. User clicks "Enable notifications" in their profile
2. Browser requests notification permission
3. If granted, browser creates a push subscription (endpoint + keys)
4. Frontend POSTs subscription data to the server
5. Server stores in `push_subscriptions` table

## Service Worker (`sw.js`)

- Caches the offline page and essential assets on install
- Intercepts fetch requests — serves cached version if network fails
- Listens for `push` events — displays notification with title, body, icon
- Handles `notificationclick` — navigates to the `url` in the payload

## When Push Is Sent

- Event registration changes (new participant, cancellation)
- Medical certificate expiry reminders
- Newsletter published
- Bureau announcements
- Vote opened/closed

## Graceful Degradation

If VAPID keys are not configured (`WEBPUSH_PUBLIC_KEY` empty), `PushNotificationService` silently no-ops. All `sendTo*` methods return immediately without error.
