## cms-articles.md — Content Management System

## Overview

13 article types with image galleries, threaded comments, auto-translation to 15 locales, soft deletes, and classifieds with 30-day auto-expiry. Articles can embed votes (trip proposals).

## Data Model

| Table | Purpose | Key Columns |
|-------|---------|-------------|
| `articles` | Content entries | id, title, slug (unique), article_type, body (longtext), featured_image, is_published, is_public, expires_at, author_id, vote_id, sort_order, deleted_at |
| `article_translations` | Per-locale translations | article_id, locale, title, body, auto_translated, stale, source_hash, source_word_count, translated_word_count, retries, flagged_at, flag_reason |
| `article_images` | Gallery images | article_id, file_path, alt_text, caption, layout_hint, sort_order |
| `article_comments` | Threaded comments | article_id, user_id, parent_id (self-ref), body |

## Article Types (13)

| Slug | Icon | Color | Label |
|------|------|-------|-------|
| `news` | 📰 | #0d6efd | News |
| `history` | 🏛️ | #6f42c1 | Club History |
| `safety` | 🛟 | #dc3545 | Safety |
| `training` | 🎓 | #198754 | Training |
| `regulation` | 📋 | #6c757d | Regulation |
| `trip_report` | 🌊 | #0dcaf0 | Trip Report |
| `trip_proposal` | 🗺️ | #fd7e14 | Trip Proposal |
| `environment` | 🌿 | #20c997 | Environment |
| `gear` | 🤿 | #0077be | Gear |
| `classified` | 🏷️ | #ffc107 | Classified |
| `faq` | ❓ | #adb5bd | FAQ |
| `newsletter` | 📬 | #e83e8c | Newsletter |
| `video` | 🎬 | #e74c3c | Video |

Defined in `Article::TYPES` constant. `Article::MEMBER_TYPES = ['classified']` — only classifieds can be created by regular members.

## Controllers

### `Admin\ArticleController` (Bureau)
Full CRUD for all article types. On update, calls `ArticleTranslationService::markStaleIfChanged()` to flag translations for refresh.

### `HomeController` (Public/Member)
Displays published articles on the homepage and individual article pages. Shows translations in tabbed UI matching user's preferred locale.

### Member classifieds
Members can create/edit/delete their own classified ads. Auto-expiry at 30 days via `expires_at` field, cleaned by `CleanupClassifieds` job.

## Translation Pipeline

### `ArticleTranslationService`

- `translate(Article, targetLocale, sourceLocale='fr')` → creates/updates ArticleTranslation
- `translateAll(Article, locales[], sourceLocale)` → translates to all enabled locales
- `markStaleIfChanged(Article)` → marks existing translations as stale when source changes

### Translation Logic
1. Compute `source_hash` (xxh3 of title|body)
2. Skip if existing translation is not stale and hash matches
3. Call Google Translate API (free `gtx` endpoint)
4. Handle chunking for texts >4500 chars (split on paragraph boundaries, 300ms delay between chunks)
5. Preserve template variables (`{{ ... }}`) and media tags via placeholder substitution
6. Validate word count ratio — flag if suspicious
7. Map `pt` locale to `pt-PT` (European Portuguese)

### `ProcessTranslations` Job
Runs **hourly**. Picks up articles with stale/missing translations and processes them.

## Comments

- Threaded via `parent_id` self-referencing FK
- Sanitized with `HtmlSanitizer::clean($body, 'comment')` preset
- Indexed on `(article_id, created_at)` for efficient loading

## Image Gallery

- `layout_hint` field: controls display (full-width, thumbnail, inline)
- Images ordered by `sort_order`
- Stored via Storage disk

## Vote Embedding

Articles with `vote_id` display an inline voting widget. Used for trip proposals where members vote on destination preferences directly within the article.

## Visibility

- `is_published` — draft vs live
- `is_public` — visible to guests vs members-only
- `expires_at` — auto-hides after date (classifieds use 30-day default)
- `sort_order` — controls ordering on homepage (negative = hidden from homepage stream)
