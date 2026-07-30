# DivingClub — User Journeys

Scenarios ordered from simplest (anonymous visitor) to most complex (system setup from scratch).

---

## Journey 1 — Anonymous Visitor Browses the Site

**Actor:** Someone who found the club via search or word of mouth.

1. Lands on the **Welcome page** — sees club branding, feature highlights, Register/Login buttons
2. Opens the **About** dropdown in the nav bar:
   - Reads **Training Schedule** — pool times, locations, levels
   - Reads **Our Values** — safety, inclusivity, environment
   - Reads **Club History** — founding story, milestones
   - Reads **The Bureau** — who runs the club
   - Reads **Our Instructors** — instructor cards with bios, specialties, motivation
   - Reads **Our Members** — membership breakdown figures
   - Reads **Contact & Social** — email, address, Facebook, Instagram, WhatsApp
3. Uses the **Dues Calculator** — enters a hypothetical status, sees estimated fee and SEPA QR code
4. Switches language via the **language selector** (e.g. FR → DE → LB)
5. Reads any **public article** linked from the home page (articles marked `is_public=true`)
6. Decides to register → clicks **Register**

**Accessible without login:** Welcome, About pages (7), Dues Calculator, public articles, Contact, language switch, iCal feed, Offline page (PWA), Password Reset.

---

## Journey 2 — New User Registers

**Actor:** The visitor from Journey 1.

1. Fills in the **registration form**: first name, last name, email, password
   - (Or clicks an **OAuth button**: Google, Facebook, Microsoft)
   - Honeypot + timestamp CAPTCHA runs silently in the background
2. Sees the **"Verify your email"** page — a verification link was sent
3. Checks inbox, clicks the verification link
4. Redirected to **My Profile** with a success message
5. The account now has role `member` and status `actif` (or whatever the admin assigns)
6. All member features are now unlocked

**Forgot password?** See Journey 49.

---

## Journey 3 — New Member Completes Their Profile

**Actor:** A freshly registered, verified member.

1. Opens **My Profile → Info tab**
   - Fills in: nationality, sex, date of birth, phone numbers, club email
   - Uploads a **profile photo**
2. Opens **Private Info tab**
   - Fills in: full address (street, postal code, city, country), emergency contact
3. Opens **Diving tab**
   - Adds their **federation licence** (e.g. FFESSM)
   - Adds their **certification level** (e.g. FFESSM N2) with obtained date
   - Sets it as **primary certification**
4. Opens **Renewal tab**
   - Sees their FFESSM licence details
   - Taps the **📷 Scan QR** button → phone camera opens
   - Points at their FFESSM membership card QR code
   - System reads the URL (`https://l.ffessm.fr/c.asp?id={number}_{KEY}`) and extracts the 6-char key
   - Key auto-fills → clicks **Save**
   - FFESSM InfoLicencié link and verification QR code now appear on their profile
5. Opens **Medical Cert tab**
   - Uploads their medical certificate (PDF or photo)
   - Selects the **type** (GP, ENT, Sports Medicine)
   - Sets the **exam date**
   - Sees status: "Not yet verified" — waits for bureau confirmation
6. Opens **Language tab** — sets preferred language to French

---

## Journey 4 — Member Browses and Participates

**Actor:** A confirmed member with a verified medical certificate.

1. Logs in → lands on the **Home page**
   - Sees latest articles as cards with type badges and colored borders
   - Sidebar shows quick links and upcoming events
2. Clicks an article → reads the full **article detail page**
   - Scrolls through the **image gallery**
   - Writes a **comment** at the bottom
   - Replies to another member's comment (threaded, max 3 levels)
3. Opens **Calendar** → browses events in month/week/day view
4. Clicks a **pool training event**
   - Sees details, instructor, location (Google Maps link)
   - Clicks **Register** — registration confirmed
   - (If the event were full, they'd join the **waiting list**)
5. Opens **Info → Members Directory** — searches for a fellow member
6. Opens **Info → Trombinoscope** — browses the photo grid
7. Opens **Info → Documents** — downloads a public file (e.g. club statutes PDF)
8. Opens **Gallery** — browses event photos in a lightbox grid
9. Opens **Privacy** — toggles photo publication consent, downloads their data as JSON
10. Subscribes to the **iCal feed** (`/calendar.ics`) in their phone calendar app — events sync automatically
11. Imports dive logs from their dive computer via **UDDF import** (see Journey 41)

---

## Journey 5 — Member Posts a Classified Ad

**Actor:** A member who wants to sell their old BCD.

1. Clicks **Classifieds** in the nav
2. Sees the classifieds page — "My Classifieds" section is empty
3. Clicks **Post a Classified**
4. Fills in: title ("Selling Mares BCD, size M"), description (rich text), uploads a photo
5. Submits → redirected to classifieds page, sees their ad in "My Classifieds" with status **Active** and expiry date (30 days from now)
6. Other members see the ad in the "All Classifieds" section
7. After 25 days, the status changes to **Expiring soon** (yellow badge)
8. Member clicks **Extend** → expiry pushed 30 more days
9. After the ad sells, member clicks **Delete** to remove it
10. If the ad expires without action, it disappears from the public list; member can click **Renew** to reactivate

---

## Journey 6 — Member Cancels an Event Registration

**Actor:** A member who registered for a dive trip but can't make it.

1. Opens **My Profile → Registrations tab** — sees their upcoming registrations
2. Clicks the event → opens the **event detail page**
3. Clicks **Cancel Registration** → confirms
4. If a payment was pending, it's automatically deleted
5. If the event had a waiting list, the next person is auto-promoted

---

## Journey 7 — Instructor Fills In Their Bio

**Actor:** A member with the `instructor` role (or `active_instructor` flag).

1. Opens **My Profile → Diving tab**
2. Scrolls past certifications to the **Instructor Profile** section (only visible to instructors)
3. Fills in:
   - **Experience & Background**: "Diving since 2005, FFESSM N4 instructor, 500+ logged dives, Red Sea, Mediterranean, Atlantic"
   - **Specialties & Interests**: "Wreck diving, underwater photography, Nitrox"
   - **What motivates you?**: "Sharing the passion, helping beginners gain confidence underwater"
4. Clicks **Save**
5. Their profile card now appears on the public **Our Instructors** page — visible to everyone, including non-registered visitors

---

## Journey 8 — Member Votes in a Simple Poll (Trip Proposal)

**Actor:** A member who received a vote token by email.

1. Receives an email: "Vote on the summer trip destination"
2. Clicks the link → opens the **vote page** (no login required, token is authentication)
3. Sees the trip proposal description and options:
   - 🇭🇷 Croatia — Vis Island
   - 🇪🇬 Egypt — Red Sea
   - 🇲🇹 Malta — Gozo
4. This is a **simple vote** with **multi-select** and **public results** enabled
5. Checks two options (Croatia + Malta) → clicks **Submit Vote**
6. Sees the **Thank You** page with live results (progress bars showing percentages)
7. Changes their mind → clicks the link again → sees their current selections → unchecks Malta, checks Egypt → clicks **Update Vote**
8. Results update in real time

**Alternatively:** The vote is embedded in a **trip proposal article** — the member reads the article and votes inline without leaving the page.

---

## Journey 9 — Member Votes in the Annual Secret Ballot

**Actor:** A member voting for the new bureau.

1. Receives an email: "Annual General Meeting — Bureau Election"
2. Clicks the link → opens the **vote page**
3. Sees a warning: **"Your vote is anonymous and irreversible"**
4. This is an **election** — single-select, no change allowed, results hidden
5. Selects one candidate → clicks **Cast Vote (irreversible)**
6. Sees the **Thank You** page — no results shown (election mode)
7. Cannot vote again — revisiting the link shows "You have already voted"
8. After the vote closes, the admin announces results at the AGM

---

## Journey 10 — Bureau Member Verifies a Medical Certificate

**Actor:** A bureau member (bureau_master role).

1. Opens **Administration → Members** → clicks **View** on a member
2. Opens their **Medical Cert tab**
3. Sees an uploaded certificate with status "not verified"
4. Reviews the document (downloads it)
5. Adjusts the **cert type** dropdown if needed (e.g. member selected GP but it's actually an ENT cert)
6. Corrects the **exam date** if the member entered it wrong
7. Clicks **Verify** → certificate is marked verified, compliance rules are re-evaluated, expiry date is calculated

---

## Journey 11 — Bureau Exports Medical Data for the Federation

**Actor:** Bureau master preparing the annual federation submission.

1. Opens **Administration → Members**
2. Clicks the **🏥 Medical Export** dropdown
3. Selects the federation (e.g. **FFESSM**) from the dropdown
4. Clicks **📋 Member List (CSV)**
   - Downloads a semicolon-delimited CSV with columns: Date Demande (empty), NOM, Prénom, Date de naissance, sexe, n° Rue, Pays, CP, Localité, Date Examen Médical
   - Opens in Excel, fills in the "Date Demande" column, sends to federation
5. Clicks **📦 Certificates (ZIP)**
   - Downloads a ZIP with all current medical certificates for FFESSM members
   - Files named: `DUPONT Jean 42 GP.pdf`, `DUPONT Jean 42 ENT.pdf` (no collisions)
   - Attaches to the federation submission

---

## Journey 12 — Bureau Manages the Document Library

**Actor:** Bureau master organizing club files.

1. Opens **Administration → Document Library**
2. Creates folders: `/minutes`, `/statutes`, `/insurance`, `/photos`
3. Navigates to `/minutes` → uploads the latest AGM minutes (PDF), marks as **Public**
4. Navigates to `/insurance` → uploads the club's liability insurance, marks as **Private** (bureau-only)
5. Members can now see and download the AGM minutes via **Info → Documents**
6. The insurance document is only visible in the admin library

---

## Journey 13 — Bureau Creates a Season and Generates Events

**Actor:** Bureau master setting up the new training year.

1. Opens **Administration → Seasons → Create**
2. Enters: year 2026, start September 1, end June 30
3. On the season detail page, adds **weekly patterns**:
   - Tuesday 19:30–21:00, Pool Training, Bonnevoie, all levels
   - Thursday 19:30–21:00, Pool Training, Mersch, beginners
   - Saturday 10:00–12:00, Pool Training, Bonnevoie, advanced
4. Adds **holidays**: Toussaint (Oct 26–Nov 3), Christmas (Dec 21–Jan 5), Carnival (Feb 28–Mar 4), Easter (Apr 11–Apr 27)
5. Clicks **Preview** → sees all generated dates, holidays shown in red
6. Clicks **Generate** → 58 events created in one click
7. Clicks **Activate** → this becomes the current season
8. Members now see all events in the **Calendar**

---

## Journey 14 — Bureau Manages Payments

**Actor:** Bureau master handling annual dues.

1. Opens **Administration → Payments**
2. For each member, clicks **Calculate** → system computes: base fee × status multiplier + optional add-ons
3. Clicks **Generate** → creates a payment record with a unique communication string
4. Sends a **bulk email** (via Administration → Email) to all members with their amount and communication string
5. Members pay via bank transfer using the communication string
6. Bureau opens **Reconciliation** → pastes the bank statement
7. System **auto-matches** transactions to payments using fuzzy matching on communication strings
8. Bureau reviews matches → clicks **Confirm** for correct matches, **Ignore** for unrelated transactions
9. Dashboard shows updated revenue figures

---

## Journey 15 — Bureau Publishes Content

**Actor:** Bureau master writing a news article.

1. Opens **Administration → Articles → Create**
2. Selects type: **News** (or Safety, Training, Trip Report, etc.)
3. Fills in title, writes body in rich text editor
4. Uploads a **featured image**
5. Adds **gallery images**: a full-width panorama, two half-width action shots, three third-width detail photos — each with captions
6. Toggles **Published** and **Public** (visible without login)
7. Saves → article appears on the home page with a blue News badge

**For a trip proposal:**
8. Selects type: **Trip Proposal**
9. Creates a **vote** first (Administration → Votes → Create) with destinations as options, multi-select enabled, public results
10. Back in the article form, attaches the vote via the dropdown
11. Saves → article appears with an embedded vote form

---

## Journey 16 — Bureau Sends a Club-Wide Email

**Actor:** Bureau master announcing the Christmas party.

1. Opens **Administration → Email**
2. Selects a saved **template** (or writes from scratch)
3. Sets subject: "🎄 Christmas Party — December 14"
4. Writes body using variables: "Dear {{first_name}}, you're invited..."
5. Selects recipient group: **All active members**
6. Clicks **Preview** → sees the email rendered with sample data
7. Clicks **Send** → emails queued and sent
8. Checks the **Send Log** tab to confirm delivery

---

## Journey 17 — Bureau Runs the Annual Election

**Actor:** Bureau master organizing the AGM vote.

1. Opens **Administration → Votes → Create**
2. Sets title: "Bureau Election 2026"
3. Selects mode: **Election (anonymous, irreversible)**
4. Leaves "Allow multiple selections" unchecked (single candidate)
5. Leaves "Show results publicly" unchecked (secret ballot)
6. Adds candidate names as options
7. Sets opens/closes dates (or leaves blank for manual control)
8. Saves → clicks **Generate Tokens** → one unique token per eligible member
9. Sends tokens via email (Administration → Email, or the system sends them automatically)
10. Members vote (see Journey 9)
11. After the deadline, clicks **Close Vote**
12. Views results on the **vote detail page** — only the admin sees the totals
13. Announces results at the AGM

---

## Journey 18 — Bureau Manages Equipment

**Actor:** Bureau master tracking club gear.

1. Opens **Administration → Equipment → Create**
2. Adds: "Regulator Aqualung Titan", serial number, purchase date
3. Defines **maintenance rules** in Settings: regulator service every 12 months (mandatory)
4. When a member needs gear: opens the equipment detail → clicks **Loan** → selects member
5. When returned: clicks **Return**
6. When maintenance is due: the equipment shows a warning → clicks **Complete Maintenance** → next service auto-scheduled 12 months out
7. Loan history is tracked for each item

---

## Journey 19 — Bureau Master: Full System Setup from Scratch

**Actor:** The person deploying and configuring the system for the first time.

### Phase 1 — Deployment
1. Clones the repository, runs `composer install`, `npm ci && npm run build`
2. Copies `.env.example` → `.env`, sets database credentials, `APP_URL`, mail settings
3. Runs `php artisan key:generate`
4. Runs `php artisan migrate` → creates all 48 tables
5. Runs `php artisan db:seed` → creates the admin user, roles, statuses
6. Runs `php artisan db:seed --class=CertificationLevelSeeder` → 105 certification levels across 11 federations
7. Runs `php artisan db:seed --class=CepSeeder` → 38 articles with translations, 7 public stub articles
8. Runs `php artisan storage:link` → makes uploads accessible
9. Starts the server: `php artisan serve` (or configures Nginx/Apache)

### Phase 2 — Initial Configuration
10. Logs in as admin (`admin@divingclub.eu` / `password`)
11. Opens **Administration → Settings**:
    - **Federations**: verifies the 11 pre-seeded federations are correct, adds any missing ones
    - **Member Statuses**: verifies the 6 French-slug statuses (actif, fonctionnaire, honoraire, junior, famille, membre_de_droit) and their fee multipliers
    - **Membership Fees**: sets the base fee per status per season year (e.g. Actif 2026 = €150)
    - **Medical Compliance Rules**: adds rules per federation (e.g. FFESSM: GP cert every 12 months for under-40, GP + ENT every 12 months for 40+)
    - **Equipment Maintenance Rules**: adds rules (e.g. regulator service every 12 months, BCD inspection every 24 months)
    - **Theme & Appearance**: picks a preset (Ocean), customizes colors, uploads the club logo, sets club full name "Club Européen de Plongée"
    - **Site Layout**: selects the layout variant (Default for a playful club feel, Professional for a federation-style corporate look, or Minimal for a modern SaaS feel) — takes effect immediately for all visitors
    - **Article Type Backgrounds**: sets subtle background colors for each article type
    - **Banking**: enters the club IBAN, BIC, beneficiary name for SEPA QR codes
12. Reads the **Admin Guide** (14 pages) for reference

### Phase 3 — Content Setup
13. Opens **Administration → Articles** → edits each pinned stub:
    - **Training Schedule**: fills in actual pool times, locations, holiday breaks
    - **Our Values**: writes the club's actual values statement
    - **Contact & Social**: enters real email, address, Facebook/Instagram/WhatsApp links
    - **Club History**: writes the founding story, key milestones with dates
    - **The Bureau**: fills in current president, VP, treasurer, secretary, technical director
    - **Our Members**: adds current membership figures from the dashboard
    - (Our Instructors is auto-populated once instructors fill in their bios)
14. Opens **Administration → Links** → adds quick links for the home sidebar (federation websites, pool schedules, etc.)
15. Opens **Administration → Document Library** → creates folders, uploads club statutes, insurance docs, AGM minutes

### Phase 4 — Season & Events
16. Creates the first **season** (see Journey 13)
17. Generates events from weekly patterns
18. Manually creates any one-off events (open water trips, social events)

### Phase 5 — Members
19. Shares the registration URL with existing club members
20. As members register, reviews their profiles in **Administration → Members**
21. Assigns correct **roles** (instructor, assistant, bureau_member) and **statuses**
22. Can **impersonate** any member to see the system from their perspective (yellow banner shown, all actions audit-logged)
23. Verifies uploaded **medical certificates** (see Journey 10)
24. Generates **membership fees** and sends payment emails (see Journey 14)

### Phase 6 — Ongoing Operations
25. Publishes **news articles** as things happen (see Journey 15)
26. Runs the **annual election** at the AGM (see Journey 17)
27. Exports **medical data** for federation submission annually (see Journey 11)
28. Manages **equipment** loans and maintenance (see Journey 18)
29. Monitors the **dashboard** for statistics and trends
30. Generates the **annual report** for the AGM (see Journey 46)
31. Reviews **audit logs** periodically (see Journey 37)
32. Checks **GDPR** erasure requests if any come in
33. Manages **dive sites** with emergency data (see Journey 43)
34. Customizes the **homepage layout** — reorder widgets, set visibility (see Journey 44)
35. Exports club dive data to **DAN DL7** for research (see Journey 42)
36. Members scan their FFESSM cards to link **InfoLicencié** (see Journey 48)

### Phase 7 — Optional Integrations
37. Configures **OAuth** providers in `.env` (Google, Facebook, Microsoft client IDs/secrets)
38. Configures **Google Maps API key** for event location maps
39. Sets up **scheduled tasks** via cron: `* * * * * cd /path && php artisan schedule:run >> /dev/null 2>&1`
    - Medical reminders (daily 08:00)
    - Vote auto-open/close (every minute)
    - Weekly backup (Sunday 03:00)

---

## Summary Matrix

| Journey | Actor | Key Screens |
|---------|-------|-------------|
| 1 | Anonymous visitor | Welcome, About pages, Dues Calculator |
| 2 | New user | Register, Verify Email |
| 3 | New member | Profile (all tabs) |
| 4 | Active member | Home, Articles, Calendar, Events, Directory, Documents, Gallery, iCal, UDDF |
| 5 | Member (seller) | Classifieds |
| 6 | Member (cancelling) | Event Detail, Profile Registrations |
| 7 | Instructor | Profile → Diving → Instructor Bio |
| 8 | Voter (poll) | Vote page (multi-select, public results) |
| 9 | Voter (election) | Vote page (anonymous, irreversible) |
| 10 | Bureau | Members → Medical Cert → Verify |
| 11 | Bureau | Members → Medical Export (CSV + ZIP) |
| 12 | Bureau | Document Library |
| 13 | Bureau | Seasons → Patterns → Generate |
| 14 | Bureau | Payments → Reconciliation |
| 15 | Bureau | Articles → Create (with gallery, vote) |
| 16 | Bureau | Email → Compose → Send |
| 17 | Bureau | Votes → Election → Tokens → Results |
| 18 | Bureau | Equipment → Loan → Maintenance |
| 19 | Bureau Master | Full setup: deploy → configure → content → season → members |
| 20 | Anonymous visitor | Free Trial request |
| 21 | Instructor | Availability calendar — mark/unmark weekly slots |
| 22 | Member | Read article in preferred language via translation tabs |
| 23 | Bureau | Trigger article auto-translation to all languages |
| 24 | Bureau Master | Configure club identity (multi-club) |
| 25 | Bureau Master | Manage license key |
| 26 | Bureau Master | /install wizard → DB choice → admin setup |
| 27 | Bureau Master | Partnerships → Add Partner → key exchange |
| 28 | Bureau Master | Event → Federated checkbox → external slots |
| 29 | Member (external) | Partner admin → Browse Events → register via API |
| 30 | Bureau | Email → group "event" → select event → send |
| 31 | System (cron) | schedule:run → auto-translate oldest article |
| 32 | Dive Director | Dive Group Planner → auto-propose → validate → print fiche de sécurité |
| 33 | Small club admin | Wasmer deploy → /install → SQLite → operational |
| 34 | Bureau Master | Minors & Consent → link guardian → record consent |
| 35 | Member | Event photos → GDPR consent → auto-publish |
| 36 | Bureau Master | Settings → Social Media → Facebook auto-publish |
| 37 | Bureau Master | Audit Log → filter → detail diff → export CSV → retention |
| 38 | Member | Buddy Finder → post request → respond → close |
| 39 | Anonymous/Member | Dues Calculator → fee breakdown → EPC QR code |
| 40 | Member | Technical articles with SVG diagrams → multi-language tabs |
| 41 | Member | Dive log → Import UDDF → parse depth/duration/temp/deco |
| 42 | Bureau | Export DAN DL7 → upload to dan.org/PDE for research |
| 43 | Bureau | Dive Sites → emergency data → fiche de sécurité auto-fill |
| 44 | Bureau Master | Homepage layout → reorder widgets → per-widget visibility |
| 45 | Member | Calendar → Subscribe iCal → events sync to phone |
| 46 | Bureau | Annual Report → statistics → PDF export for AGM |
| 47 | System | HTTP cron endpoints for shared hosting without crontab |
| 48 | FFESSM member | Profile → Renewal → 📷 scan card QR → InfoLicencié link + QR |
| 49 | Member | Forgot Password → email reset link → new password |
| 50 | Developer | Staging mail viewer → preview intercepted emails |

---

## Journey 20 — Visitor Requests a Free Trial

**Actor:** Someone interested in trying diving.

1. Navigates to `/trial` (linked from welcome page)
2. Fills in: name, email, phone, preferred date, message
3. Submits — honeypot CAPTCHA validates silently
4. Sees confirmation message
5. Admin sees the request in Administration → Trial Requests
6. Admin contacts the visitor to schedule the trial

---

## Journey 21 — Instructor Manages Availability

**Actor:** Active instructor.

1. Clicks **Availability** in the nav bar
2. Sees weekly calendar grid (current month, Mon–Sun columns)
3. Clicks **+** on a date cell → picks activity type (e.g., Pool, Apnea, Theory)
4. Their initials appear as a colored badge on that date
5. Sees other instructors' initials already on the calendar
6. Clicks their own initial to remove availability
7. Events from the event calendar appear as grey badges for context

---

## Journey 22 — Member Reads Translated Article

**Actor:** Romanian member who prefers reading in Romanian.

1. Has set preferred language to Romanian via language selector
2. Opens an article → sees tabbed interface
3. Romanian tab is auto-selected (if translation exists)
4. Can click "Original" tab to read the French source
5. Can click any other language tab to compare
6. Auto-translated content shows a 🤖 indicator

---

## Journey 23 — Admin Translates an Article

**Actor:** Bureau member publishing a new article.

1. Creates article in French (the club's primary language)
2. Publishes it
3. Views the article → clicks "🌐 Generate translations"
4. System auto-translates to all 10 other languages via Google Translate
5. Translations stored in database — instant display for all members
6. Can manually edit any translation later if needed

---

## Journey 24 — Bureau Master Configures Club Identity

**Actor:** Admin setting up the system for a new club.

1. Goes to Administration → Settings → Club Identity
2. Fills in: club name, short code, email, address, phone, country
3. Sets warehouse address and GPS coordinates
4. Enters IBAN and BIC for payment references
5. All pages, emails, QR codes, and exports now use the new identity
6. No code changes needed

---

## Journey 25 — Bureau Master Manages License

**Actor:** Admin of a club that has grown past 100 members.

1. Tries to register member #101 → blocked by license check
2. Goes to Administration → Settings → License
3. Sees current status: "Free tier (100 members max)"
4. Obtains a license key from the project maintainer
5. Pastes the RSA-signed key into the license field
6. System verifies signature, club code, and expiry date
7. Registration unblocked — license valid for 13 months

---

## Journey 26 — Bureau Master Runs the Install Wizard

**Actor:** Technical person deploying a new instance.

1. Deploy code to server (or Wasmer). Visit the site URL.
2. Automatically redirected to `/install` (EnsureInstalled middleware).
3. Enter club name, choose database (SQLite for small clubs/Wasmer, MySQL for larger).
4. If MySQL: enter host, port, database, username, password.
5. Enter admin email and password.
6. Click "Install" → migrations run, reference data seeded (39 dive rules, 110+ cert levels, 11 federations), admin account created.
7. Redirected to homepage. Log in with admin credentials.
8. Follow Admin Guide → First Steps for post-install configuration.

---

## Journey 27 — Bureau Master Sets Up Inter-Club Partnership

**Actor:** Bureau master of Club A wanting to share events with Club B.

1. Admin → Partnerships → Add Partner.
2. Enter Club B's name and base URL. System generates Key ID + Secret.
3. Copy Key ID + Secret, send to Club B's admin (email, Signal, etc.).
4. Club B does the same, sends their credentials back.
5. Edit partnership, paste Club B's credentials into "Their credentials".
6. Both clubs now have bidirectional API access.
7. Browse Club B's federated events via "Browse Events" button.

---

## Journey 28 — Bureau Master Creates a Federated Event

**Actor:** Bureau master organizing a trip open to partner clubs.

1. Create event as usual (title, date, location, cost, cert requirements).
2. Check "Federated" checkbox, set "External slots" (e.g., 8).
3. Save. Event now appears in the federation API.
4. Partner clubs see it when browsing events.
5. Partner members register via their club's interface → API call.
6. Admin → Partnerships → External Registrations: see pending registrations.
7. Review cert level and medical validity. Approve or reject.
8. Approved external members appear on the event participant list.

---

## Journey 29 — Member Registers for a Partner Club's Event

**Actor:** Member of Club B wanting to join Club A's trip.

1. Club B admin browses Club A's federated events.
2. Sees "Gozo Trip Oct 2026" with 3/8 external slots taken.
3. Member requests to join (via their club admin or future self-service UI).
4. Club B sends registration to Club A's API with member's name, cert level, medical validity.
5. Member receives confirmation once Club A approves.

---

## Journey 30 — Admin Emails All Event Participants

**Actor:** Bureau member organizing a dive trip.

1. Admin → Email → select template (e.g., "Trip Update").
2. Choose group: "Event participants" → select the event from dropdown.
3. Preview email with variables resolved.
4. Send → emails queued for all confirmed participants.
5. Bilingual: if member's preferred locale differs from template locale, translated version appended.
6. Check email log for delivery status.

---

## Journey 31 — System Auto-Translates Articles

**Actor:** System (scheduled task).

1. Hourly cron runs `schedule:run`.
2. Task finds oldest published article without translations.
3. Calls Google Translate free API for each configured locale.
4. Stores translations in `article_translations` table with `auto_translated = true`.
5. Next visitor sees translation tabs on the article page.
6. Admin can manually trigger translations for any article via "Generate translations" button.

---

## Journey 32 — Dive Director Plans a Mixed-Level Dive

**Actor:** Instructor or dive director planning a dive at a quarry.

1. Open Dive Group Planner for the event.
2. Select participants from the event registration list.
3. System loads each diver's federation, cert level, and medical status.
4. Click **Auto-Propose** → system suggests palanquée/buddy groupings based on 39 federation rules:
   - LIFRAS: P1★ needs P3★+ leader, max 4 P1 per palanquée
   - FFESSM: PE-20 needs GP-N4+ guide, max 4 per guide
   - BSAC: Ocean Diver needs Sports Diver+ buddy
5. Review the proposal → click **Apply** to accept, or adjust manually via drag-and-drop.
6. Click **Validate** → system checks all groups against federation rules:
   - ✅ Valid — group composition meets all rules
   - ❌ Violation — specific rule broken, with explanation and regulation reference
   - ⚠️ Warning — medical cert expiring within 30 days, or cert level borderline for depth
7. Flags divers whose cert level doesn't allow the planned depth.
8. Director adjusts groups, confirms plan.
9. Click **Print Fiche de Sécurité** → generates FFESSM 2024-2025 format PDF:
   - 4 palanquées max (12-16 divers) with empty rows for hand-fill
   - Columns: Pal, Mode, Prof, Rôle, Nom, Brevet, Féd, N° Licence, Aptitude, Méd, H.Imm, H.Sort, DTR, Obs
   - Dive params sub-row per palanquée: actual depth, deco stops 3/6/9m, safety stop checkbox, GPS
   - Emergency info block: phone, VHF, hospital + distance, hyperbaric chamber + phone + distance
   - Required safety equipment from the dive site record

---

## Journey 33 — New Club Deploys on Wasmer (Free Tier)

**Actor:** Small club wanting a free hosted instance.

1. Fork the repo, push to Wasmer Edge.
2. Visit the Wasmer URL → install wizard appears.
3. Choose SQLite (only option on Wasmer — no MySQL available).
4. Set club name, admin credentials.
5. Install completes. DB is ~2 MB, well within 100 MB free tier.
6. Configure theme, upload logo, set federation preferences.
7. Invite members. Club operational.

---

## Journey 34 — Bureau Manages Minors & Parental Consent

**Actor:** Bureau master onboarding a 14-year-old member.

1. The minor's parent registers the child (or the bureau creates the account).
2. Opens **Administration → Minors & Consent**.
3. Sees the minor listed with age, no guardian linked, consent badges all ✗.
4. Expands the minor's row → **Link Guardian**: selects the parent's member account, relationship = "Parent".
5. **Record Consent**: selects "General", uploads the signed parental authorization form (PDF scan).
6. Records additional consents: "Events" (can participate), "Medical" (club can manage medical certs).
7. Leaves "Photos" unchecked — parent doesn't want the child's photos published.
8. Dashboard worklist no longer shows "Minors without guardian" for this child.
9. When the minor turns 18, they manage their own consents via the Privacy page.

---

## Journey 35 — Member Uploads Event Photos with GDPR Consent

**Actor:** A member who participated in a dive trip.

1. Opens the **event detail page** for the completed trip.
2. Scrolls to the **Photos** section.
3. Clicks **Choose Files** → selects 5 photos from the trip.
4. Adds a caption: "Vis Island wreck dive, 28m".
5. Checks the **GDPR consent checkbox**: "I consent to these photos being shared on the club's social media channels".
6. Clicks **Upload** → photos appear in the gallery, sorted by quality score.
7. If social media auto-publish is enabled and the FB group is confirmed closed, photos are automatically posted to the Facebook group.
8. The social publish log tracks each post's status (published/failed).

---

## Journey 36 — Bureau Configures Social Media Auto-Publish

**Actor:** Bureau master setting up Facebook integration.

1. Creates a Facebook App at developers.facebook.com, gets a Page Token with `publish_to_groups` permission.
2. Adds `FACEBOOK_PAGE_TOKEN=...` to `.env`.
3. Opens **Administration → Settings → Technical → Social Media Auto-Publish**.
4. Sets **Facebook Group ID** (from the group URL).
5. Confirms **"FB Group is Closed"** = Yes (privacy requirement).
6. Enables **Auto-Publish**.
7. Saves. From now on, event photos uploaded with GDPR consent are auto-posted.
8. If any condition fails (no consent, group not closed, publish disabled), photos stay local only.

---

## Journey 37 — Bureau Reviews the Audit Log

**Actor:** Bureau master investigating a data change.

1. Opens **Administration → Audit Log**.
2. Filters by model type "User", action "updated", date range last 7 days.
3. Sees a list of changes with summaries (which fields changed).
4. Clicks **View** on a suspicious entry → opens the **detail page**.
5. Sees a field-by-field diff: `status_id` changed from 3 to 1, `role_id` from 5 to 2.
6. Notes the IP address and user agent — confirms it was a legitimate admin action.
7. Clicks **📥 Export** to download filtered results as CSV for the bureau meeting.
8. Sets **retention policy** to 24 months → system auto-purges older entries monthly.

---

## Journey 38 — Member Uses the Buddy Finder

**Actor:** A certified diver looking for a buddy for a weekend dive.

1. Clicks **Buddies** in the nav bar.
2. Sees open buddy requests from other members.
3. Clicks **Post a Request**: "Looking for a buddy for Remerschen quarry, Saturday 10am, max 20m".
4. Other members see the request → one clicks **Respond** with a message.
5. Original poster sees the response, contacts the buddy directly.
6. After the dive, clicks **Close** to remove the request.

---

## Journey 39 — Member Uses the Dues Calculator

**Actor:** Prospective member checking costs before joining.

1. Navigates to `/dues` (linked from welcome page, no login required).
2. Selects a membership status (e.g., "Actif").
3. Optionally selects add-ons (insurance level, double affiliation).
4. Sees the calculated annual fee with breakdown.
5. Scans the **EPC QR code** with their banking app → pre-filled SEPA transfer with club IBAN, amount, and communication string.
6. Decides to register based on the transparent pricing.

---

## Journey 40 — Member Reads Technical Diving Articles

**Actor:** A member studying for their N2 certification.

1. Opens the **Articles** section → filters by type "Training".
2. Sees 20+ technical articles with original SVG diagrams covering physics, physiology, techniques, and gear.
3. Opens **Loi de Mariotte (Boyle)** → reads the explanation with an inline SVG showing volume compression at 1/2/3/4 bar.
4. Switches to the **English** tab to compare terminology (preparing for a PADI crossover).
5. Opens **Gradient Factors** → studies the ascent profile comparison chart and recommended GF settings table.
6. Opens **Dive Computer Export Guide** → follows brand-specific instructions for their Mares Genius to export UDDF.
7. Reads **Buddy Check (BWRAF)** → memorizes the mnemonic with the SVG diagram showing all check points.
8. All articles available in 11 languages with automatic translation (🤖 indicator on auto-translated content).

---

## Journey 41 — Member Imports Dive Logs from Their Computer

**Actor:** A member who wants to log their dives on the club platform.

1. Exports dive data from their dive computer's app:
   - Mares SSI → Menu → Share → UDDF
   - Shearwater Cloud → Select dives → Export → UDDF
   - Suunto DM5 → File → Export → UDDF
   - Garmin Connect → exports .fit → imports into Subsurface → exports UDDF
   - Scubapro LogTRAK → File → Export → UDDF
   - Aqualung DiverLog+ → native UDDF export
2. If their computer doesn't export UDDF natively, uses **Subsurface** (free, open source) as a universal converter.
3. Goes to their dive log → clicks **Import UDDF** → selects the `.uddf` file.
4. System parses UDDF 3.2.1: extracts depth profile, duration, temperature, deco stops, safety stop detection.
5. Dive appears in their personal log.
6. Can also **Export UDDF** to download all their logged dives in universal format.

---

## Journey 42 — Bureau Exports Dive Data for DAN Research

**Actor:** Bureau master contributing to decompression research.

1. Opens **Administration → Export DAN**.
2. System generates a DAN DL7 file (pipe-delimited format) containing all club dive logs.
3. File includes ZDH (header), ZDL (dive log), and ZDT (tissue) records.
4. Downloads the `.dl7` file.
5. Uploads it to the DAN Project Dive Exploration portal (dan.org/PDE).
6. Club contributes to global decompression sickness research.

---

## Journey 43 — Bureau Manages Dive Sites

**Actor:** Bureau master maintaining the dive site database.

1. Opens **Administration → Dive Sites**.
2. Sees the 13 pre-seeded sites (Luxembourg quarries, Belgian coast, etc.).
3. Clicks **Create** to add a new site: name, GPS coordinates, max depth, description.
4. Fills in **emergency data**:
   - Emergency phone number
   - VHF channel
   - Nearest hospital name and distance (km)
   - Nearest hyperbaric chamber, phone number, and distance (km)
   - Required safety equipment (O₂ kit, first aid, VHF radio, etc.)
5. When creating a dive event, selects this site → emergency data auto-populates the fiche de sécurité PDF.
6. Edits an existing site to update hospital distance after a new facility opens.

---

## Journey 44 — Bureau Customizes the Homepage Layout

**Actor:** Bureau master personalizing the club's home page.

1. Logs in → visits the home page → clicks **Edit Layout** (pencil icon).
2. Sees the widget toolbar on each section: Latest Articles, Upcoming Events, Quick Links, Hero Banner, Custom HTML.
3. Drags widgets to reorder them.
4. Sets **visibility** per widget:
   - 🌍 Public — visible to everyone including anonymous visitors
   - 🔒 Members — visible only to logged-in members
   - 🎓 Instructors — visible only to instructors and bureau
   - 👔 Bureau — visible only to bureau members
5. Configures widget options (e.g., number of articles to show, hero title text).
6. Clicks **Save Layout** → changes take effect immediately.
7. Anonymous visitors see only public widgets; members see member + public widgets.

---

## Journey 45 — Member Subscribes to the iCal Calendar Feed

**Actor:** A member who wants club events in their phone calendar.

1. Opens the **Calendar** page.
2. Clicks the **Subscribe (iCal)** link → copies the URL: `https://club-domain.lu/calendar.ics`.
3. Opens their phone's calendar app (Google Calendar, Apple Calendar, Outlook):
   - Google Calendar: Settings → Add calendar → From URL → paste
   - Apple Calendar: File → New Calendar Subscription → paste
   - Outlook: Add calendar → Subscribe from web → paste
4. Club events appear in their personal calendar, color-coded by type.
5. New events and changes sync automatically (calendar apps poll the feed periodically).
6. The iCal feed is public — no authentication required.

---

## Journey 46 — Bureau Generates the Annual Report

**Actor:** Bureau master preparing for the Annual General Meeting.

1. Opens **Administration → Annual Report**.
2. Selects the reporting year/season.
3. System generates a comprehensive report with:
   - Membership statistics: total members, new registrations, departures, by status/role/federation
   - Event statistics: total events, attendance rates, most popular events
   - Financial summary: total dues collected, outstanding payments
   - Medical compliance: percentage compliant, certificates verified
   - Equipment: inventory count, loans, maintenance completed
4. Reviews the report on screen.
5. Exports as PDF or prints for the AGM presentation.

---

## Journey 47 — System Runs Scheduled Tasks via HTTP Cron

**Actor:** System on shared hosting without cron access.

1. Admin configures `CRON_KEY=secret` in `.env`.
2. Sets up an external cron service (cron-job.org, UptimeRobot) to ping `{APP_URL}/cron/run?key=secret` every 15 minutes.
3. On each ping, the system runs all due scheduled tasks:
   - `/cron/medical-reminders` — sends expiry reminders (30/15/7/0 days)
   - `/cron/weekly-backup` — database backup (Sundays)
   - `/cron/run-schedule` — vote auto-open/close, article auto-translation
4. Each endpoint validates the `CRON_KEY` before executing.
5. Returns `OK` with timestamp on success, `403` on invalid key.
6. Alternative to the standard `* * * * * php artisan schedule:run` cron entry.

---

## Journey 48 — Member Links FFESSM InfoLicencié via QR Scan

**Actor:** FFESSM member who has their physical licence card.

1. Opens **My Profile → Renewal tab**
2. Sees their FFESSM licence number but no verification link yet
3. Taps the **📷** button next to the FFESSM Key field
4. Phone camera opens (rear-facing, using native BarcodeDetector API)
5. Points at the QR code on their FFESSM membership card
6. System reads the URL: `https://l.ffessm.fr/c.asp?id={number}_{KEY}`
7. Extracts the 6-character key (e.g. `UYDCFY`) and fills the input field
8. Clicks **Save** → key stored in `member_licences.federation_key`
9. Profile now shows:
   - A QR code linking to `https://infolicencie.ffessm.fr/Home/InfoLicence?number={num}&key={key}`
   - A clickable 🔗 link to view the full licence on FFESSM's portal (certifications, insurance, club)
10. Bureau can also scan/enter keys for any member from the admin member profile

**Browser support:** Chrome (Android/desktop), Safari 17+, Edge. Falls back to manual text entry on unsupported browsers.

---

## Journey 49 — Member Resets Their Password

**Actor:** A member who forgot their password.

1. Clicks **Forgot Password?** on the login page
2. Enters their email address → clicks **Send Reset Link**
3. Receives an email with a password reset link (valid for 60 minutes)
4. Clicks the link → enters a new password (twice)
5. Clicks **Reset Password** → redirected to login with success message
6. Logs in with the new password

---

## Journey 50 — Developer Reviews Intercepted Emails

**Actor:** Developer or admin in staging/dev environment.

1. Mail is configured with `MAIL_MAILER=log` (all emails written to `storage/logs/laravel.log`)
2. Opens `/staging-mail` in the browser
3. Sees a list of all intercepted emails: subject, recipient, date
4. Clicks an email → sees the rendered HTML preview
5. Clicks **Raw** to see the full email source
6. Uses this to verify email templates, variable substitution, and delivery logic without sending real emails
7. Clicks **Clear** to purge the staging mailbox

**Note:** Requires `STAGING_MODE=true` in `.env`. When `STAGING_USER` and `STAGING_PASS` are also set, the entire site is protected by HTTP Basic Auth (useful for real staging servers). Leave them empty for local dev.


## New Features (v1.1.0)

### Bureau (Admin)
54. Control federation visibility (Active/Recognized/Invisible) to declutter certification dropdowns for members
55. Search articles across all 15 language translations from the admin article list
56. Sort any admin table by clicking column headers (articles, equipment, payments, audit logs, members)
57. Compose newsletters with editable teaser text per slot and optional custom URLs
58. Scatter decorative marine SVG elements across newsletter layout with one click
59. Send test newsletter to own email before approval workflow
60. Send newsletter for comments via mailto: link to all bureau members
61. Monitor scheduled task health on the dashboard (heartbeat table with OK/Failed/Overdue status)
62. Monitor queue processing via Horizon dashboard (Admin → Queue Monitor)
63. Check for and apply GitHub updates from the admin dashboard (bureau_master only)
64. Manage granular permissions per role using spatie/laravel-permission (12 permissions)
65. Drag-and-drop file upload in the document library
66. Bulk download selected files as ZIP from the document library
67. Search documents by name or description across all folders
68. Preview images and PDFs inline (lightbox overlay) in the document library

### Members
69. Search the club document library by file name or description
70. View personal documents (medical certs, certifications) with verification status on the documents page
71. See "EN ›" link on newsletter article cards to read the English version

### System
72. Resend API integration for reliable email delivery (no SMTP port 25 dependency)
73. Avatars auto-resized to 400×400 JPEG on upload (Intervention Image)
74. Translation quality tracking: source hash, word count validation, retry logic, auto-flagging
75. All scheduled tasks extracted into dedicated Job classes (visible in Horizon)
76. Supervisor manages Horizon for auto-restart on crash/reboot


### Inbound Mail System
77. Bureau/instructors can email event participants by sending to `event-{id}@domain` — the system resolves the alias and forwards to all confirmed registrations
78. Dynamic mail aliases: `bureau`, `instructors`, `members`, `members.s{id}` (event participants), `members.pn1/pn2/pn3` (training levels), `year={YYYY}` (dues year)
79. Subject directive `(recipients: bureau, sortie=42, Michel B)` for targeting multiple groups in one email
80. Simulate mode: add `simulate` to directive to get a recipient report without actually sending
81. Sender confirmation email after each forwarded message
82. Two inbound modes: Maildir (local Postfix) or IMAP (remote mailbox) — configurable via .env
83. Resend API load-balancing across two API keys (clubcep.eu + ecb.pm) for 200 emails/day on free tier

---

## Journey 51 — Member Manages Email Preferences

**Actor:** A member with multiple email addresses (personal + work + OAuth).

1. Opens **My Profile → Info tab → Email Addresses**
2. Sees the table: each email has Label, Status (verified/unverified), and a **Receive mail** checkbox
3. Adds their Google email for OAuth login → it appears as unverified
4. Verifies it via the link sent to that address
5. **Unchecks "Receive mail"** on the Google email — it's now login-only
6. Tries to uncheck the last remaining email → gets error: "At least one email address must receive club communications"
7. Help text explains the options in their language

---

## Journey 52 — Instructor Marks Availability on the Planning Calendar

**Actor:** An instructor (e.g., Keran, E3/MF1).

1. Opens **Instructor Planning** from the nav menu
2. Sees the monthly calendar with color-coded activity types
3. On a Wednesday with two pool blocks (17:00–18:30 and 18:30–20:00), sees them **side by side** with activity-type colors (PN1 = navy, Kids = green)
4. Clicks **➕** on the first block → their initial (K, yellow) appears in that slot
5. They're auto-registered for the event
6. Clicks **✅** to remove availability → initial disappears, registration cancelled
7. Legend shows **Instructors** (with initials/colors) and **Bureau** (non-instructor) separately
8. Multi-day events (e.g., Gravière du Fort Sat–Sun) appear on both days

---

## Journey 53 — Bureau Loans Equipment at an Event

**Actor:** A bureau member managing equipment at a pool session.

1. Opens **Administration → Equipment**
2. Filters by **Type: BCD**, **Status: Available**, **Location: Entrepôt**
3. Clicks a row → sees equipment detail with serial number, last retest date, ❄️ cold water / 👶 child badges
4. Selects a member from the dropdown, picks the current event, clicks **Loan**
5. Or uses **Quick Loan** from a member's profile → selects multiple items at once
6. After 5 minutes (configurable), the member receives an email: "Equipment loaned to you: BCD #M9, Tank #15"
7. After the session, returns the items → member gets "All equipment returned — thank you!"
8. The **Last Seen** column updates automatically
9. Equipment with `status=on_loan` shows the borrower's name in the list

---

## Journey 54 — Bureau Reviews Equipment Inventory

**Actor:** A bureau member doing an inventory check.

1. Opens **Administration → Equipment**
2. Uses filters: **Type** (BCD/tank/regulator), **Status**, **Location** (Entrepôt/Piscine Merl), **Size** (free text search in name)
3. Clicks **Last Seen ↓** to sort — items not seen recently float to top
4. Clicks a row → sees full detail: serial number, brand, manufacturer, threading, pressures, retest dates, maintenance schedule, loan history
5. Updates **Location** via dropdown (Entrepôt / Piscine Merl / Hors Service)
6. Checks **👶 Child** or **❄️ Cold Water** flags
7. Items imported from the old SM system show full loan history back to 2023

---

## Journey 55 — Bureau Checks System Health

**Actor:** A bureau member or sysadmin.

1. Visits `/health` → gets JSON: status (healthy/degraded/unhealthy), response time, checks
2. Checks: database connectivity (ms), DB size, disk usage (%), storage writable, cache, queue (pending/failed jobs)
3. HTTP 200 = healthy, 299 = degraded (warnings), 503 = unhealthy
4. Uptime Kuma monitors this endpoint and alerts on non-200
5. **Umami analytics** at `analytics.ecb.pm` shows real-time visitors, pages, referrers, countries
6. **GoAccess report** at `/report.html` shows historical traffic (refreshed every 15 minutes)

---

## Journey 56 — Member Views Cotisation Timeline

**Actor:** A member checking their payment history.

1. Opens **My Profile → Info tab**
2. Sees the **cotisation timeline** — a row of colored blocks from adhesion year to current
3. Green = paid, grey = unpaid, with 2-digit year labels inside each block
4. Gaps are immediately visible (e.g., 2020–2021 grey = didn't pay during COVID)
5. Current year unpaid → red badge warning
6. Bureau members see the same timeline but can **click blocks to toggle** paid/unpaid

---

## Journey 57 — Member Registers Another Member for an Event

**Actor:** A member registering their spouse/buddy.

1. Opens an event page (e.g., "Sortie Gravière du Fort")
2. Scrolls to **Registration** section
3. Below their own registration, sees **"Register another person"** dropdown
4. Selects their spouse from the member list
5. Adds an optional comment
6. Clicks **Register** → spouse is confirmed (or added to waiting list)
7. To cancel: clicks **Unregister** → confirm dialog says "Unregister [Name]?" with Confirm/Cancel buttons

---

## Journey 58 — Bureau Configures Default Language

**Actor:** A bureau master setting up the club for a German-speaking club.

1. Opens **Administration → Settings → Languages**
2. Selects **Default Language: Deutsch** from the dropdown
3. Unchecks languages not needed (e.g., Finnish, Estonian)
4. Clicks **Save**
5. New visitors now see the site in German by default
6. Members can still override with their profile preference or the language switcher

---

## Journey 59 — Visitor Requests a Trial Dive (Baptême)

**Actor:** Someone interested in trying scuba diving.

1. Clicks **"Book a Trial"** in the navigation
2. Reads the page (translated to their language): what's included, how it works, health notice
3. Fills in: first name, last name, email, phone, preferred date, questions
4. Submits → sees "Your request has been submitted!"
5. Bureau receives the request in **Administration → Trial Requests**
6. Bureau contacts the person to confirm a date

---

## Journey 60 — Bureau Views Access Logs and Analytics

**Actor:** A bureau member reviewing site usage.

1. Checks **Umami** (analytics.ecb.pm) → sees real-time visitors, top pages, countries, devices
2. Checks **GoAccess** (/report.html) → sees historical traffic, status codes, referrers
3. Can identify: who logged in, what pages they visited, peak hours, 404 errors
4. Data persists permanently in Umami (PostgreSQL), GoAccess refreshes every 15 minutes from Caddy logs

---

## Journey 61 — Member Browses the Document Library

**Actor:** A member looking for club documents.

1. Opens **Resources → Documents**
2. Sees a **collapsible folder tree** on the left (same as admin library)
3. Clicks ▶ to expand subfolders, 📂 marks the current folder
4. Files show: name, size, access level (🌍 public / 👥 members / 🎓 instructors / 🔒 bureau), date
5. Clicks a file to download
6. Breadcrumb navigation shows the path
7. Bureau members can upload, change visibility, create folders, drag-and-drop files

---

## Journey 62 — Member Views Federation Quick Links

**Actor:** A member looking for federation information.

1. On the homepage sidebar, sees **Quick Links** widget
2. Each link shows: federation logo, name, and description
3. **FFESSM** — Fédération Française d'Études et de Sports Sous-Marins (primary federation)
4. **FLASSA** — Fédération Luxembourgeoise des Activités et Sports Sub-Aquatiques
5. **CMAS** — Confédération Mondiale des Activités Subaquatiques
6. Clicks any link → opens the federation website in a new tab

---

## Journey 63 — Bureau Edits an Article Inline

**Actor:** A bureau member reading an article that needs updating.

1. Views any article page (e.g., /article/member-figures)
2. Sees a **✏️ Edit** button next to the title (only visible to bureau)
3. Clicks it → goes directly to the admin article editor
4. Makes changes in the rich text editor
5. Saves → redirected back to the article with updated content
6. Auto-translation queues the changes for all 15 languages
