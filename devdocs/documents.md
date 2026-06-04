## documents.md — Document Library & Photo Gallery

## Overview

Two-tier file system: admin full management (LibraryController) and member-facing browsing with visibility filtering (DocumentBrowserController). Also includes event photo gallery with quality scoring.

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
