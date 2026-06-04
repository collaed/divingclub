## newsletters.md — Newsletter System

## Overview

Rich HTML newsletters composed from article slots, with themed backgrounds, approval workflow, and multi-language delivery. Separate from the email system — newsletters are content publications, not transactional emails.

## Data Model

| Table | Purpose | Key Columns |
|-------|---------|-------------|
| `newsletters` | Newsletter issues | id, title, month (YYYY-MM), background_image, slots (JSON), decorations (JSON), status, created_by, sent_at |
| `newsletter_approvals` | Bureau approval records | newsletter_id, user_id, approved, comment |

### Status Flow

```
draft → pending → approved → sent
         ↓
       draft (withdrawn)
```

### Slots Schema (JSON)

Each newsletter has 1–5 content slots:
```json
[
  {"position": 1, "article_id": 42, "article_type": "news", "teaser": "Short preview...", "custom_url": "", "slug": "spring-dive-recap"}
]
```

## Controllers

### `Admin\NewsletterController` (Bureau only)

| Method | Route | Purpose |
|--------|-------|---------|
| `index` | `GET /admin/newsletters` | List all newsletters with approvals |
| `create` | `GET /admin/newsletters/create` | Compose form — select articles for slots |
| `store` | `POST /admin/newsletters` | Save draft |
| `show` | `GET /admin/newsletters/{id}` | Preview with rendered HTML |
| `edit` | `GET /admin/newsletters/{id}/edit` | Edit draft (blocked if sent) |
| `update` | `PUT /admin/newsletters/{id}` | Update draft |
| `submit` | `POST /admin/newsletters/{id}/submit` | Submit for approval (draft → pending) |
| `withdraw` | `POST /admin/newsletters/{id}/withdraw` | Creator pulls back (pending → draft) |
| `approve` | `POST /admin/newsletters/{id}/approve` | Bureau member approves (3 required) |
| `send` | `POST /admin/newsletters/{id}/send` | Send to all verified members |

## Approval Workflow

1. Creator composes newsletter (selects articles, background, decorations)
2. Creator submits for approval → status becomes `pending`
3. Three different bureau members must approve (creator cannot self-approve)
4. Once 3 approvals reached → status becomes `approved`
5. Any bureau member sends → queues emails for all verified users

## Sending

- Builds per-user email HTML with the user's preferred locale
- French version always included; if user prefers another language, a translated version is appended below a separator
- Each email logged in `email_log` with template_slug `newsletter-{id}`
- Dispatched via queue; respects staging mode capture

## Background Images

- **Presets**: stored in `public/images/newsletter/<theme>/` (git-tracked)
- **Custom uploads**: stored via `Storage::disk('public')` in `newsletters/`
- **Generated artwork**: stored in `storage/app/public/newsletters/` — never in `public/`

## Services

### `NewsletterStencilSlicer`
Renders the newsletter HTML using background images, slot articles, and decoration overlays. Produces the final email-safe HTML.

## Model: `Newsletter`

- Casts: `slots` → array, `decorations` → array, `sent_at` → datetime
- `creator()` → BelongsTo User
- `approvals()` → HasMany NewsletterApproval
- `approvalCount()` → count of approved=true
- `slotArticles()` → resolves article_ids from slots JSON, eager-loads translations and images
