## routes.md — Route Map (341 routes)

All routes in `routes/web.php` unless noted. API routes in `routes/api.php`.

## Public (no auth)

| Prefix | Controller | Routes | Purpose |
|--------|-----------|--------|---------|
| `/` | HomeController | ~15 | Landing pages (home2, home3), about pages, schedule, values, history, bureau, instructors, contact |
| `/login` | Auth\LoginController | 3 | Login form, authenticate, lockout |
| `/register` | Auth\RegisterController | 2 | Registration form, store |
| `/auth/{provider}` | Auth\SocialAuthController | 2 | OAuth redirect + callback (Google, Facebook, Microsoft, GitHub, LinkedIn) |
| `/eulogin` | Auth\EuLoginController | 2 | EU Login CAS authentication |
| `/password/reset` | Auth\PasswordResetController | 4 | Forgot password flow |
| `/trial` | TrialController | 2 | Public free trial request form |
| `/dues-calculator` | DuesCalculatorController | 1 | Public fee estimation |
| `/calendar/feed` | CalendarFeedController | 1 | iCal feed (.ics) |
| `/qr/*` | QrCodeController | 3 | SEPA QR codes (public, signed, federation) |
| `/install` | InstallController | 3 | First-time setup wizard |

## Authenticated (auth + verified.email)

| Prefix | Controller | Routes | Purpose |
|--------|-----------|--------|---------|
| `/profile` | ProfileController | ~10 | View/edit profile, update info/password/diving/language |
| `/profile/document` | ProfileDocumentController | 4 | Upload/download/verify documents |
| `/profile/avatar` | ProfileAvatarController | 2 | Upload/delete avatar |
| `/profile/certifications` | ProfileCertificationController | 4 | Manage certifications |
| `/profile/emails` | ProfileEmailController | 4 | Secondary emails |
| `/events` | EventController | ~15 | List, show, create, edit, register, cancel, upload photos |
| `/events/{id}/settlement` | TripSettlementController | 10 | Receipt submission, treasurer management, close ledger |
| `/events/{id}/dive-groups` | DiveGroupController | 10 | Dive group management, proposals, print |
| `/classifieds` | ClassifiedController | 7 | CRUD classifieds (30-day auto-expiry) |
| `/articles/{id}/comments` | CommentController | 2 | Post/delete comments |
| `/documents` | DocumentBrowserController | 3 | Browse document library |
| `/members` | MembersDirectoryController | 2 | Member directory (privacy-gated) |
| `/buddy` | BuddyController | 4 | Buddy requests/responses |
| `/votes/{token}` | VotePublicController | 2 | Cast vote via token |
| `/gdpr` | GdprController | 3 | Data export, erasure request |
| `/instructor-calendar` | InstructorAvailabilityController | 3 | View/toggle availability |
| `/push` | PushSubscriptionController | 2 | Subscribe/unsubscribe web push |
| `/contact` | ContactController | 2 | Contact form |
| `/homepage/layout` | HomepageLayoutController | 2 | Widget layout (bureau) |

## Admin (auth + role:bureau_*)

| Prefix | Controller | Routes | Purpose |
|--------|-----------|--------|---------|
| `/admin/dashboard` | Admin\DashboardController | 2 | Stats, charts, export |
| `/admin/members` | Admin\MemberController | ~12 | CRUD, impersonate, manage details |
| `/admin/settings` | Admin\SettingsController | ~20 | Club identity, federations, medical rules, maintenance rules, themes |
| `/admin/email` | Admin\EmailController | ~8 | Compose, send, templates, logs, approve/reject inbound |
| `/admin/email-stats` | Admin\EmailStatsController | 1 | Delivery statistics |
| `/admin/equipment` | Admin\EquipmentController | ~10 | CRUD, loan, return, quick-loan, maintenance |
| `/admin/dive-sites` | Admin\DiveSiteController | 6 | CRUD dive sites |
| `/admin/dive-group-rules` | Admin\DiveGroupRuleController | 4 | CRUD pairing rules |
| `/admin/articles` | Admin\ArticleController | 7 | CRUD articles, trigger translation |
| `/admin/newsletters` | Admin\NewsletterController | ~10 | Compose, preview, approve, send, manage themes |
| `/admin/votes` | Admin\VoteController | ~8 | CRUD votes, manage options, view results |
| `/admin/payments` | Admin\PaymentController | ~8 | Fee management, reconciliation, import statements |
| `/admin/library` | Admin\LibraryController | ~8 | Document library CRUD (folders, upload, visibility) |
| `/admin/backups` | Admin\BackupController | 5 | Create, list, download, inspect, delete |
| `/admin/audit-logs` | Admin\AuditLogController | 5 | View, export, purge, retention |
| `/admin/partnerships` | Admin\PartnershipController | 6 | CRUD partner clubs |
| `/admin/seasons` | Admin\SeasonController | 6 | Season management, patterns, holidays |
| `/admin/trial-requests` | Admin\TrialRequestController | 4 | Manage public trial requests |
| `/admin/guardians` | Admin\GuardianController | 5 | Minor/guardian links, consent |
| `/admin/guide` | Admin\GuideController | 2 | In-app admin guide (24 pages) |
| `/admin/annual-report` | Admin\AnnualReportController | 1 | Generated annual report |
| `/admin/roles` | Admin\RolePermissionController | 3 | Role/permission management |
| `/admin/links` | Admin\LinkController | 4 | Quick links management |

## API (routes/api.php)

| Prefix | Controller | Auth | Purpose |
|--------|-----------|------|---------|
| `/api/federation/events` | Api\FederationApiController | API key | List federated events |
| `/api/federation/register` | Api\FederationApiController | API key | Register external member |
| `/api/federation/register/{id}` | Api\FederationApiController | API key | Cancel/status check |

## Middleware Applied

- **All web routes**: StagingBasicAuth, SetLocale, EnsureInstalled
- **Authenticated**: + auth, verified.email (EnsureEmailVerified checks email_verified_at + must_change_password)
- **Admin**: + role:bureau_master,bureau_finance,bureau_technical (via CheckRole middleware)
- **API**: stateless, validated via X-Club-Key-Id + X-Club-Secret headers
