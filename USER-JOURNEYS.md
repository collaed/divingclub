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

**Accessible without login:** Welcome, About pages (7), Dues Calculator, public articles, Contact, language switch, Offline page (PWA).

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
4. Opens **Medical Cert tab**
   - Uploads their medical certificate (PDF or photo)
   - Selects the **type** (GP, ENT, Sports Medicine)
   - Sets the **exam date**
   - Sees status: "Not yet verified" — waits for bureau confirmation
5. Opens **Language tab** — sets preferred language to French

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
8. Opens **Privacy** — toggles photo publication consent, downloads their data as JSON

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
7. Runs `php artisan db:seed --class=PinnedArticleSeeder` → 7 public stub articles
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
22. Verifies uploaded **medical certificates** (see Journey 10)
23. Generates **membership fees** and sends payment emails (see Journey 14)

### Phase 6 — Ongoing Operations
24. Publishes **news articles** as things happen (see Journey 15)
25. Runs the **annual election** at the AGM (see Journey 17)
26. Exports **medical data** for federation submission annually (see Journey 11)
27. Manages **equipment** loans and maintenance (see Journey 18)
28. Monitors the **dashboard** for statistics and trends
29. Generates the **annual report** for the AGM
30. Reviews **audit logs** periodically
31. Checks **GDPR** erasure requests if any come in

### Phase 7 — Optional Integrations
32. Configures **OAuth** providers in `.env` (Google, Facebook, Microsoft client IDs/secrets)
33. Configures **Google Maps API key** for event location maps
34. Sets up **scheduled tasks** via cron: `* * * * * cd /path && php artisan schedule:run >> /dev/null 2>&1`
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
| 4 | Active member | Home, Articles, Calendar, Events, Directory, Documents |
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
| 32 | Dive Director | Dive Group Planner → mixed-level palanquée |
| 33 | Small club admin | Wasmer deploy → /install → SQLite → operational |

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
4. System suggests palanquée/buddy groupings based on federation rules:
   - LIFRAS: P1★ needs P3★+ leader, max 4 P1 per palanquée
   - FFESSM: PE-20 needs GP-N4+ guide, max 4 per guide
   - BSAC: Ocean Diver needs Sports Diver+ buddy
5. Flags medical certificates expiring within 30 days.
6. Flags divers whose cert level doesn't allow the planned depth.
7. Director adjusts groups, confirms plan.

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
