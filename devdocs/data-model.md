## data-model.md — Database Schema (131 tables, MySQL/PostgreSQL)

Note: some tables appear duplicated in listings due to the dual legacy_roles/roles setup.

## Members & Auth

| Table | Purpose | Key Columns |
|-------|---------|-------------|
| `users` | Core user accounts | id, username, primary_email, password, role_id, status_id, email_verified_at, must_change_password |
| `member_details` | Extended profile (1:1 with users) | user_id, first_name, last_name, sex, date_of_birth, phone_*, country, iban, emergency_contact_*, cotisation_years (JSON), instructor_initial, instructor_color, active_instructor, bureau_member, locally_modified_at, synced_at |
| `member_statuses` | 6 statuses | id, name, slug (membre_de_droit, actif, fonctionnaire, honoraire, junior, famille) |
| `legacy_roles` | Old role table | id, name, slug |
| `roles` / `permissions` / `model_has_roles` / `model_has_permissions` / `role_has_permissions` | Spatie Permission (6 roles: member, instructor, bureau_master, bureau_finance, bureau_technical, public) |
| `user_emails` | Secondary email addresses | user_id, email, verified_at |
| `user_social_accounts` | OAuth providers | user_id, provider, provider_id |
| `user_certification_levels` | Member↔CertLevel pivot | user_id, certification_level_id, date_obtained |
| `guardian_links` | Minor↔guardian relationships | minor_id, guardian_id |
| `parental_consents` | Guardian consent for minors | guardian_link_id, document_path, signed_at |
| `gdpr_consents` | GDPR consent tracking | user_id, consent_type, granted_at |
| `failed_login_attempts` | Brute-force protection | email, ip, attempted_at |
| `password_reset_tokens` | Password reset flow | email, token, created_at |
| `sessions` | Active sessions | id, user_id, ip_address, last_activity |
| `push_subscriptions` | Web push (PWA) | user_id, endpoint, keys (JSON) |

## Federations & Certifications

| Table | Purpose | Key Columns |
|-------|---------|-------------|
| `federations` | 11 diving federations | id, acronym, full_name, visibility (active/recognized/invisible) |
| `certification_levels` | 105 cert levels across federations | id, federation_id, name, level_order, equivalence_group |
| `member_licences` | Member federation memberships | user_id, federation_id, licence_number, expiry_date, federation_key |
| `medical_compliance_rules` | Per-federation medical rules | federation_id, max_age_without_cert, cert_validity_months, age_brackets (JSON) |

## Events & Calendar

| Table | Purpose | Key Columns |
|-------|---------|-------------|
| `events` | All club activities | id, title, event_type, event_date, end_date, event_time, location, max_participants, status, trip_settlement_enabled, driver_bounty_total, local_daily_charge, settlement_status, dive_site_id, season_id |
| `event_registrations` | Who's signed up | event_id, user_id, status (confirmed/waiting/cancelled), transit_mode (van/fly/own), waiting_list_position, comment |
| `event_photos` | Event photo gallery | event_id, user_id, path, caption |
| `external_registrations` | Partner club registrations | event_id, partnership_id, external_member_name, external_medical_valid_until, status |
| `seasons` | Season definitions | id, name, start_date, end_date |
| `season_patterns` | Recurring event templates | season_id, event_type, day_of_week, time, location |
| `season_holidays` | Holiday exceptions | season_id, date, reason |
| `instructor_availabilities` | Instructor weekly calendar | user_id, date, activity_type, assigned_by |

## Dive Planning

| Table | Purpose | Key Columns |
|-------|---------|-------------|
| `dive_sites` | 13+ dive locations | name, country, latitude, longitude, max_depth, nearest_hyperbaric_chamber, hyperbaric_phone, safety_notes |
| `dive_groups` | Buddy palanquées per event | event_id, name, max_depth |
| `dive_group_members` | Members in each group | dive_group_id, user_id, is_leader |
| `dive_group_rules` | 14 pairing rules | rule_type, min_level, max_depth_diff, description |

## Trip Settlement

| Table | Purpose | Key Columns |
|-------|---------|-------------|
| `trip_participants` | Cost-sharing participants | event_id, user_id, driving_percentage (0-100), local_transit_days |
| `trip_receipts` | Expense submissions | event_id, user_id, amount, approved_amount, category (general/transit), image_path, status (pending/approved/rejected), reviewed_by, reviewed_at |

## Payments & Finance

| Table | Purpose | Key Columns |
|-------|---------|-------------|
| `membership_fees` | Annual fee definitions | season_year, status_slug, base_amount, age_discount_pct |
| `membership_fee_components` | Fee breakdown items | fee_id, label, amount |
| `payment_expected` | What members owe | user_id, type (membership/event), amount_due, communication, status (pending/partial/paid), event_id |
| `bank_transactions` | Imported bank statements | date, amount, communication, counterparty, matched_payment_id |

## Equipment

| Table | Purpose | Key Columns |
|-------|---------|-------------|
| `equipment` | Inventory items | id, name, type (tank/bcd/regulator/other), status (available/on_loan/maintenance_required/retired), short_number, size, location |
| `equipment_loans` | Active/past loans | equipment_id, user_id, loaned_at, returned_at, expected_return_date, loaned_by |
| `equipment_maintenance` | Scheduled tasks | equipment_id, maintenance_name, due_date, completed_at, is_mandatory |
| `equipment_maintenance_rules` | Interval definitions | equipment_type, maintenance_name, interval_months, is_mandatory, regulation_reference |

## Content & CMS

| Table | Purpose | Key Columns |
|-------|---------|-------------|
| `articles` | 13 article types | id, title, body, article_type, is_public, author_id, expires_at |
| `article_images` | Gallery images per article | article_id, path, caption, sort_order |
| `article_comments` | Threaded comments | article_id, user_id, parent_id, body |
| `article_translations` | Auto-translated content | article_id, locale, title, body |
| `links` | Quick links / resources | title, url, category, sort_order |

## Email & Communication

| Table | Purpose | Key Columns |
|-------|---------|-------------|
| `email_log` | All sent/received emails | event_id, to_email, from_email, subject, body, status, direction (inbound/outbound), authorized |
| `email_templates` | Reusable email templates | slug, subject, body, variables (JSON) |
| `newsletters` | Rich HTML newsletters | title, month, status (draft/approved/sent), slots (JSON), created_by |
| `newsletter_approvals` | Approval workflow | newsletter_id, user_id, approved_at |
| `social_publish_logs` | Auto-publish to social media | article_id, platform, published_at |

## Voting

| Table | Purpose | Key Columns |
|-------|---------|-------------|
| `votes` | Polls and elections | id, title, type (simple/election), is_anonymous, max_choices, opens_at, closes_at, status |
| `vote_options` | Choices per vote | vote_id, label, sort_order |
| `vote_tokens` | One-time voting tokens | vote_id, user_id, token, used_at |
| `vote_ballots` | Cast votes | vote_id, vote_option_id, token_id (null if simple vote) |

## Documents & Library

| Table | Purpose | Key Columns |
|-------|---------|-------------|
| `documents` | Member-uploaded documents | user_id, category (certification/medical/insurance/other), file_path, status, date_established |
| `library_files` | Bureau document library | folder, original_name, file_path, uploaded_by, visibility (public/members/bureau) |

## Partners & External

| Table | Purpose | Key Columns |
|-------|---------|-------------|
| `club_partnerships` | Inter-club partnerships | name, base_url, api_key_id, api_secret_hash, is_active, last_sync_at |
| `trial_requests` | Public free trial requests | name, email, phone, message, status, honeypot fields |

## System & Infrastructure

| Table | Purpose | Key Columns |
|-------|---------|-------------|
| `audit_logs` | Action audit trail ($timestamps = false) | user_id, action, model_type, model_id, changes (JSON), created_at |
| `theme_settings` | Club customization | key, value (club_name, primary_color, logo_path...) |
| `schedule_heartbeats` | Job monitoring | task_name, last_beat_at, payload |
| `sync_runs` | Legacy sync history | started_at, finished_at, status, counts (JSON), error |
| `cache` / `cache_locks` | Laravel cache driver |
| `jobs` / `job_batches` / `failed_jobs` | Laravel queue |
| `migrations` | Migration state |

## Legacy (Joomla bridge, read-only)

| Table | Purpose |
|-------|---------|
| `jos_users` | Old Joomla user accounts |
| `jos_comprofiler` | Old Joomla profile data |

## Key Relationships

- `User` → hasOne `MemberDetail`, hasMany `EventRegistration`, hasMany `Document`, hasMany `MemberLicence`, hasMany `UserEmail`
- `Event` → hasMany `EventRegistration`, hasMany `EventPhoto`, hasMany `TripParticipant`, hasMany `TripReceipt`, belongsTo `DiveSite`, belongsTo `Season`
- `EventRegistration` → belongsTo `Event`, belongsTo `User`, has `transit_mode`
- `Equipment` → hasMany `EquipmentLoan`, hasMany `EquipmentMaintenance`
- `Article` → hasMany `ArticleImage`, hasMany `ArticleComment`, hasMany `ArticleTranslation`
- `Vote` → hasMany `VoteOption`, hasMany `VoteToken`, hasMany `VoteBallot`
- `Federation` → hasMany `CertificationLevel`, hasMany `MedicalComplianceRule`

## JSON Columns

- `member_details.cotisation_years` — array of integers [2023, 2024, 2025]
- `events.assistant_ids` — array of user IDs
- `newsletters.slots` — article slot assignments
- `payment_expected.components` — fee breakdown [{label, amount}]
- `audit_logs.changes` — before/after JSON diff
- `medical_compliance_rules.age_brackets` — [{min_age, max_age, validity_months}]
