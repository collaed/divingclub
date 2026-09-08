## documents.md — Document Library & Photo Gallery

## Overview

Two-tier file system: admin full management (LibraryController) and member-facing browsing with visibility filtering (DocumentBrowserController). Also includes event photo gallery with quality scoring.

Separately, each member has **personal documents** (medical certificates,
certification scans, insurance) — a different table, disk and controller from
the club library. See below.

## Personal Documents (`documents` table)

Per-member files: medical certificates, certification scans, insurance papers.
Not part of the club library and never browsable by other members.

| | |
|---|---|
| Table | `documents` — `user_id`, `category` (`certification` / `medical` / `insurance` / `other`), `cert_type`, `file_path`, `original_filename`, `mime_type`, `size_bytes`, `date_established`, `expiry_date`, `is_verified` / `verified_by` / `verified_at`, `is_current`, `superseded_by`, `is_compliant`, `compliance_notes`, `reminder_{30,15,7,0}_sent_at`. `SoftDeletes` + `Auditable`. |
| Disk | `Storage::disk('local')` — root `storage_path('app/private')` (→ `/mnt/data/<env>/pics/private/` on the servers). **Not** symlinked from `public/`; Caddy cannot reach it. |
| Path | `documents/{user_id}/{filename}` |
| Filename | `Str::slug("{last_name} {first_name} {cert_type ?? category} {date}").{ext}` (deterministic — re-upload for the same person/type/date overwrites). |
| Legacy | `storage/app/private/medical/` holds imported certs with no `documents` row (per-member sub-dirs). |
| Upload | `POST /profile/document` — `auth` + `verified.email`; `mimetypes:application/pdf,image/jpeg,image/png`, `max:10240` KB. Bureau may upload for another member via `target_user_id`. |
| Download / view | `GET /profile/document/{document}` and `…/view` — 403 unless `$document->user_id === $viewer->id` **or** `$viewer->isBureau()`. |
| Verify | `POST /profile/document/{document}/verify` — bureau only. |
| `serve => true` route | `local` disk registers `GET /storage/{path}`, but for a private-visibility disk `ServeFile` demands a valid signed URL → unsigned = **404 (prod)** / 403 (staging). The app never generates one for docs. |
| GDPR erasure | `GdprController::confirmErasure()` deletes the physical file for every **DB-tracked** doc then soft-deletes the rows. Legacy `private/medical/` files without a row are **not** touched. |

Medical-specific flow (compliance evaluation, OCR, reminders, federation
export) is in `medical.md`.

## Data Model

| Table | Purpose | Key Columns |
|-------|---------|-------------|
| `library_files` | Club documents/files | id, filename, original_name, path, mime_type, size, folder, visibility, description, uploaded_by |
| `event_photos` | Event photo gallery | id, event_id, uploaded_by, path, file_hash, thumbnail_path, caption, quality_score, view_count, has_faces, approved, gdpr_consent |

## Visibility Levels (4)

| Level | Who can see |
|-------|-------------|
| `public` | Everyone (including guests) |
| `members` | Authenticated members |
| `instructors` | Instructors + bureau |
| `bureau` | Bureau roles only |

Enforced via `LibraryFile::visibleTo($user)` scope.

## Storage

- **Library files**: `Storage::disk('local')` in `library/` — NOT publicly accessible, served via download controller
- **Event photos**: `Storage::disk('public')` in `event-photos/{event_id}/`

## Controllers

### `Admin\LibraryController` (Bureau — full management)

| Method | Route | Purpose |
|--------|-------|---------|
| `index` | `GET /admin/library` | Browse/search files by folder |
| `upload` | `POST /admin/library/upload` | Upload multiple files (max 50MB each) |
| `update` | `PUT /admin/library/{file}` | Change visibility, folder, description |
| `destroy` | `DELETE /admin/library/{file}` | Delete single file |
| `download` | `GET /admin/library/{file}/download` | Download file |
| `downloadZip` | `POST /admin/library/zip` | Download selected files as ZIP |
| `createFolder` | `POST /admin/library/folder` | Create folder (redirect) |
| `bulkDelete` | `POST /admin/library/bulk-delete` | AJAX bulk delete |
| `rename` | `POST /admin/library/{file}/rename` | Rename file |
| `move` | `POST /admin/library/{file}/move` | Move to another folder |

### `DocumentBrowserController` (Member-facing)

| Method | Route | Purpose |
|--------|-------|---------|
| `index` | `GET /documents` | Browse library with visibility filtering |
| `upload` | `POST /documents/upload` | Upload (instructors/bureau only) |
| `createFolder` | `POST /documents/folder` | Create folder with placeholder |
| `updateFile` | `PUT /documents/{file}` | Update visibility/folder |
| `destroy` | `DELETE /documents/{file}` | Delete file |
| `download` | `GET /documents/{file}/download` | Download with access check |
| `gallery` | `GET /gallery` | Photo gallery grouped by event |
| `galleryEvent` | `GET /gallery/{event}` | Single event photos |
| `galleryUpload` | `POST /gallery/{event}/upload` | Upload event photos |

## Folder System

- Virtual folders stored as `library_files.folder` string (e.g. `/Training/PN1`)
- Sidebar shows folder tree built from distinct folder values
- Empty folders use a `.folder` placeholder file (`original_name = '.folder'`, `mime_type = 'inode/directory'`)
- Placeholder auto-deleted when real files are uploaded to the folder

## Incoming Folder

Files uploaded to a folder containing "incoming" in its name are auto-copied to `storage/app/incoming/` for processing by the `incoming:process` scheduled command (document matching, import processing).

## Photo Gallery

- Photos require `approved = true` and `gdpr_consent = true` to display
- `quality_score` (0–100) used for ordering (best photos first)
- `has_faces` flag for GDPR face detection awareness
- `file_hash` (SHA-64) for deduplication
- Member view groups photos by event with cover image + count
- Paginated at 12 events per page

## Access Control

- `LibraryFile::canManage($user)` — returns true for bureau/instructors
- `LibraryFile::visibleTo($user)` — scope filtering by user's role level
- Download route verifies visibility before serving file
