## services.md — Business Logic Services (23 classes)

## Core Domain Services

### FeeCalculationService
Computes annual membership fees. Formula: `base_amount × status_modifier × age_discount + optional_components`.
- `calculate(User, seasonYear, optionalSlugs)` → {total, components[], communication}
- `breakdown(User, seasonYear, optionalSlugs)` → detailed line items
- `createPaymentExpected(User, seasonYear, optionalSlugs)` → persists the expected payment
- `buildCommunication(User, seasonYear, optionals)` → SEPA communication string

### MedicalComplianceService
Enforces per-federation medical certificate rules. Checks age brackets, expiry dates.
- `isCompliant(User, ?atDate)` → bool (can they dive on that date?)
- `getStatus(User, ?atDate)` → {compliant, expires_at, days_remaining, rule_source}
- `evaluateCertificate(Document)` → processes an uploaded medical cert, updates expiry tracking

### TripSettlementService
5-step cost-splitting for long trips. See `devdocs/trip-settlement.md`.
- `calculate(Event)` → {global_pool, transit_pool, local_subsidy, driver_bounties, participants[]}

### BankReconciliationService
Matches bank statement lines to expected payments using fuzzy string matching on communication fields.
- `parseStatement(text)` → array of parsed transactions
- `parsePdfStatement(pdfPath)` → same, from PDF
- `suggestMatches()` → proposed transaction↔payment matches
- `confirmMatch(BankTransaction)` → marks as reconciled

### DiveGroupProposalService
Auto-generates buddy palanquées respecting 14 rules (cert level gaps, depth limits, leader requirements).
- `propose(Event, ?maxDepth)` → array of suggested groups

### SwapSuggestionService
Suggests member swaps between dive groups to improve homogeneity.
- `suggest(Event)` → array of {from_group, to_group, member, improvement_score}

## Email & Communication

### MailBalancer
Load-balances outbound email across 3 SMTP providers (Mailjet, SES, SMTP). Tracks daily quotas.
- `nextProvider()` → provider name with remaining quota
- `configureForNext()` → configures Laravel's mailer for next send
- `todayCounts()` → sends per provider today
- `totalRemaining()` → total sends still available today

### MailAliasService
Resolves inbound email aliases to recipient lists. See `devdocs/email.md`.
- `resolve(alias)` → {emails[], label, auth_level}
- `resolveMultiple(aliases)` → merged result across multiple aliases
- `isAuthorized(senderEmail, alias)` → can this person send to this alias?
- `eventMailto(eventId)` → generates the mailto address for an event

### InboundMailFilter
Strips signatures, quoted replies, corporate disclaimers from forwarded emails. Three-tier: DOM-based HTML stripping, config dictionary anchors, standard delimiters.
- `filter(body, ?eventId, ?senderEmail)` → {body, needs_review, review_reason}

### PushNotificationService
Web push notifications (VAPID/PWA).
- `sendToUser(User, title, body, ?url, ?icon)` → delivers push
- `sendToRole(roleSlug, title, body)` → all users with that role
- `sendToBureau(title, body)` → bureau members

### ArticleTranslationService
Auto-translates articles to all 15 locales via Google Translate API.
- `translate(Article, targetLocale, sourceLocale)` → ArticleTranslation
- `translateAll(Article, locales, sourceLocale)` → translates to all
- `markStaleIfChanged(Article)` → flags existing translations as needing re-translation

### SocialPublishService
Auto-publishes event photos to Facebook/Instagram.
- `isEligible(EventPhoto)` → checks quality, faces, recency
- `publishToFacebook(EventPhoto)` / `publishToInstagram(EventPhoto)`
- `processQueue()` → processes pending eligible photos

## Equipment & Facilities

### BackupService
Full database + files backup with manifest, retention, admin UI.
- `create(includeFiles)` → creates ZIP backup with manifest JSON
- `list()` → available backups with sizes and dates
- `readManifest(path)` → backup metadata
- `prune(keep)` → deletes old backups beyond retention count

### LicenseService
RSA-signed license verification. Free tier ≤100 members, paid above.
- `needsLicense()` → bool (member count > 100?)
- `isValid()` → bool (license signature valid and not expired?)
- `verify(licenseString)` → validates signature against public key
- `memberCount()` → current active member count

### ThemeService
6 visual presets (Ocean, Coral, Lagoon, Abyss, Tropical, Arctic) + custom colors + dark mode + 3 site layouts (Default, Professional, Minimal).
- `css()` → generated CSS variables string
- `settings()` → current theme config from DB
- `presets()` → available preset definitions

### ImageQualityService
Scores uploaded images for quality (resolution, clarity). Used by social publish eligibility.
- `score(filePath)` → integer 0-100

### FaceDetectionService
Detects faces in photos (for social media eligibility and profile photo validation).
- `hasFaces(imagePath)` → bool
- `detect(imagePath)` → array of face bounding boxes

### NewsletterStencilSlicer
Splits a newsletter artwork image into themed header/footer/separator assets.
- `slice(inputPath, outputDir)` → array of generated asset paths
- `extractPrimaryColor(inputPath)` → hex color from the artwork

## Infrastructure

### ScheduleHeartbeat
Monitors scheduled job execution. Stores last beat time and payload.
- `beat(task, ?message, ?durationMs)` → records successful execution
- `fail(task, message)` → records failure
- `all()` → all heartbeats with status

### UpdateService
Checks for and applies git-based updates.
- `checkForUpdate()` → {current_commit, latest_commit, behind_count}
- `applyUpdate()` → git pull + migrate + cache clear
- `currentCommit()` → {hash, message, date}

### UddfService
Imports/exports dive logs in UDDF (Universal Dive Data Format) XML.
- `parse(xmlContent)` → parsed dive log array
- `export(User, diveGroupMembers)` → UDDF XML string

### DanExportService
Exports dive group data for DAN (Divers Alert Network) reporting.
- `export(diveGroupMembers)` → formatted export string

### EmailStatsService
Aggregates email delivery statistics.
- `forDate(date)` → {sent, failed, bounced, by_provider}

## Dependencies Between Services

- `EventController` → `MedicalComplianceService` (registration gate)
- `EventController` → `TripSettlementService` (via TripSettlementController)
- `PaymentController` → `FeeCalculationService`, `BankReconciliationService`
- `EmailController` → `MailBalancer`, `MailAliasService`
- `PollInboundMail` → `MailAliasService`, `InboundMailFilter`
- `NewsletterController` → `NewsletterStencilSlicer`, `MailBalancer`
- `DiveGroupController` → `DiveGroupProposalService`, `SwapSuggestionService`
- `BackupController` → `BackupService`
- `ProfileDocumentController` → `MedicalComplianceService`
