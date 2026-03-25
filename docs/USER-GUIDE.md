---
title: "DivingClub-Manager — User Guide"
author: "ClubCEP.eu"
lang: en
date: "March 2026"
---

# User Guide — DivingClub-Manager

Guide for members, instructors, and visitors of clubs running DivingClub-Manager.

---

## 1. Getting Started

### 1.1 Creating Your Account

1. Visit the club's website and click **Register**
2. Fill in: first name, last name, email, password
3. Or click a social login button (Google, Facebook, Microsoft, X, Amazon)
4. Check your inbox for a verification email → click the link
5. You're in! Your account starts with the "pending" role — the bureau will activate it

### 1.2 Switching Language

Click the language flag in the top navigation bar. 11 languages are available: English, French, German, Luxembourgish, Portuguese, Italian, Dutch, Spanish, Polish, Hungarian, Romanian. Your choice is saved for future visits. For logged-in users, the preference is stored in your profile. For guests, it's stored in the session.

### 1.3 Installing as a Phone App (PWA)

The site works as a Progressive Web App:

1. Open the site in Chrome (Android) or Safari (iOS)
2. Click "Add to Home Screen" or the install prompt
3. The app icon appears on your phone
4. Works offline with a fallback page

---

## 2. Your Profile

### 2.1 Personal Information

Go to **My Profile → Info tab**:

- Name, nationality, sex, date of birth, place of birth
- Phone numbers (home, mobile), club email
- Profile photo — upload or delete

### 2.2 Private Information

**My Profile → Private Info tab**:

- Full address (street, postal code, city, country)
- Emergency contact (name, phone, relationship)

This information is only visible to the bureau. It's used for emergency situations and federation submissions.

### 2.3 Diving Credentials

**My Profile → Diving tab**:

- Add your federation licence (e.g., FFESSM, LIFRAS, PADI) with licence number
- Add your certification level (e.g., FFESSM N2, PADI AOW, CMAS 2★) with the date obtained
- Set your primary certification — this is used for dive group planning and depth limits
- You can hold certifications from multiple federations simultaneously

The system includes 105 certification levels across 11 federations with cross-federation equivalence groups (e.g., FFESSM N1 ≈ PADI OWD ≈ CMAS 1★).

### 2.4 Medical Certificate

**My Profile → Medical Cert tab**:

1. Upload your medical certificate (PDF or photo)
2. Select the type: General Practitioner, ENT, Cardiologist, Ophthalmologist, or Other
3. Enter the exam date
4. Wait for bureau verification

The system tracks expiry automatically based on your federation's rules:

- FFESSM: GP every 12 months (all ages), ENT every 12 months (40+)
- LIFRAS: GP every 12 months (<40), GP every 6 months (40+), Cardio every 24 months (50+)

You'll receive email reminders at 30, 15, 7, and 0 days before expiration. You cannot register for pool, dive, or training events with an expired certificate. Social events are exempt.

### 2.5 Language Preference

**My Profile → Language tab** — set your preferred language for the interface and article translations.

### 2.6 Multiple Email Addresses

You can have up to 5 email addresses linked to your account. The primary email is used for login and notifications.

---

## 3. Browsing the Club

### 3.1 Home Page

After login, the home page shows:

- Latest articles as cards with type badges (News, Safety, Training, etc.) and colored borders
- Sidebar with upcoming events and quick links
- Trip proposals with embedded vote forms (🗳️ badge)
- Classifieds are in their own section, not on the home feed

### 3.2 About Pages (Public)

Seven pages accessible without login via the "About" dropdown:

- **Training Schedule** — pool times, locations, levels, holiday breaks
- **Our Values** — safety, inclusivity, environment
- **Club History** — founding story, milestones
- **The Bureau** — current elected board members
- **Our Instructors** — instructor cards with bios, specialties, motivation
- **Our Members** — membership breakdown figures
- **Contact & Social** — email, address, Facebook, Instagram, WhatsApp

### 3.3 Articles

Click any article to read the full content:

- Type-colored background header bar
- Featured image and image gallery (full-width, half-width, or third-width layouts with captions)
- Translation tabs — read in your preferred language, switch to the original or any other language
- Auto-translated content is marked with a 🤖 indicator
- Threaded comments (up to 3 levels deep) — reply to other members' comments
- Previous/Next navigation between articles (prioritizes same-type articles)
- Expired articles show an expiry badge

### 3.4 Calendar

Browse events in month, week, or day view. Events are color-coded by type:

- 🔵 Blue — Pool training
- 🔵 Navy — Open water dives
- 🟢 Green — Training
- 🟣 Purple — Theory sessions
- 🟡 Yellow — Social events

Click any event for details including location (Google Maps link), instructor, certification requirements, and registration.

### 3.5 Members Directory

**Info → Members Directory** — search for fellow members by name.

### 3.6 Trombinoscope

**Info → Trombinoscope** — photo grid of all members.

### 3.7 Documents

**Info → Documents** — download public club files (statutes, AGM minutes, insurance docs, etc.). Files are organized in folders by the bureau.

---

## 4. Events and Registration

### 4.1 Registering for an Event

1. Open the event from the Calendar or home page
2. Review the details (location, depth, certification requirements)
3. Click **Register**
4. If the event is full, you're placed on the **waiting list** — you'll be auto-promoted if someone cancels
5. Medical compliance is checked at registration time — you need a valid certificate for pool/dive/training events

### 4.2 Cancelling a Registration

1. Go to **My Profile → Registrations tab**
2. Click the event → **Cancel Registration**
3. Any pending payment is automatically deleted
4. The next person on the waiting list is auto-promoted

### 4.3 WhatsApp Groups

Some events have a WhatsApp group link — look for the green "Join WhatsApp Group" button on the event page.

### 4.4 Event Deposits

Some events (especially trips) have deposit schedules. You'll see the instalment amounts and due dates on the event page.

### 4.5 Event Photos

After a completed event, you can upload photos:

1. Open the event detail page → Photos section
2. Click **Choose Files** → select photos
3. Add a caption
4. Check the GDPR consent checkbox if you agree to social media sharing
5. Upload — photos appear in the gallery, sorted by quality score

---

## 5. Dues and Payments

### 5.1 Dues Calculator

Visit `/dues` (no login required) to estimate your annual fee:

1. Select your membership status (Actif, Junior, Famille, etc.)
2. Optionally select add-ons (insurance level, double affiliation)
3. See the calculated amount with breakdown
4. Scan the **SEPA QR code** with your banking app → pre-filled transfer with club IBAN, amount, and communication string

### 5.2 Paying Your Dues

1. You'll receive an email with your amount and a structured communication string
2. Make a bank transfer using the exact communication string
3. The bureau reconciles payments — you'll see "Paid" status on your profile

---

## 6. Classifieds

### 6.1 Posting an Ad

1. Click **Classifieds** in the nav
2. Click **Post a Classified**
3. Fill in: title (e.g., "Selling Mares BCD, size M"), description (rich text), upload a photo
4. Your ad is active for 30 days

### 6.2 Managing Your Ads

- **Extend** — push the expiry 30 more days
- **Delete** — remove the ad immediately
- **Renew** — reactivate an expired ad

After 25 days, the status changes to "Expiring soon" (yellow badge).

---

## 7. Voting

### 7.1 Simple Polls (Trip Proposals)

You'll receive an email with a voting link (no login required — the token is your authentication):

1. See the options (e.g., trip destinations: Croatia, Egypt, Malta)
2. Select one or more options (if multi-select is enabled)
3. Submit — you can change your vote later
4. See live results as progress bars (if enabled)

Votes can also be embedded in trip proposal articles — read the article and vote inline.

### 7.2 Elections (Secret Ballot)

For formal elections (e.g., bureau election):

1. Click the voting link from your email
2. Read the warning: "Your vote is anonymous and irreversible"
3. Select one candidate
4. Cast your vote — you cannot change it, and the link between your token and your ballot is permanently severed
5. Results are hidden until the vote closes
6. Revisiting the link shows "You have already voted"

---

## 8. Instructor Features

### 8.1 Availability Calendar

If you have the Instructor role:

1. Click **Availability** in the nav
2. See the weekly calendar grid (current month, Mon–Sun columns)
3. Click **+** on a date to mark yourself available
4. Select the activity type (Pool, Kids, Apnea, Quarry, Theory, etc. — 10 color-coded types)
5. Your initials appear as a colored badge on that date
6. See other instructors' initials for coordination
7. Click your initial to remove availability
8. Events from the event calendar appear as grey badges for context

### 8.2 Instructor Bio

**My Profile → Diving tab → Instructor Profile** (only visible to instructors):

- Experience & Background (e.g., "Diving since 2005, 500+ logged dives")
- Specialties & Interests (e.g., "Wreck diving, underwater photography, Nitrox")
- What motivates you? (e.g., "Sharing the passion, helping beginners gain confidence")

This appears on the public **Our Instructors** page — visible to everyone, including non-registered visitors.

---

## 9. Dive Data

### 9.1 Importing from Your Dive Computer

You can upload dive logs from your computer in UDDF format. The system extracts: depth, duration, temperature, deco stops, safety stop detection.

**Brand-specific instructions:**

| Brand | App | Export |
|-------|-----|--------|
| **Mares** (Genius, Puck, Quad, Smart) | Mares SSI app | Menu → Share → UDDF |
| **Shearwater** (Perdix, Teric, Peregrine) | Shearwater Cloud | Select dives → Export → UDDF |
| **Suunto** (Zoop, D5, EON) | Suunto DM5 (desktop) | File → Export → UDDF |
| **Garmin** (Descent Mk2/3, G1) | Garmin Connect | ⚠️ .fit only → use Subsurface |
| **Scubapro** (G2, G3, Luna) | LogTRAK | File → Export → UDDF |
| **Aqualung/Apeks** (i330R, i770R) | DiverLog+ | Native UDDF export |

**Subsurface** (free, open source, by Linus Torvalds) reads all formats and can convert to UDDF. Download from subsurface-divelog.org.

### 9.2 Exporting

- **UDDF export** — download your dive logs in universal format
- **DAN DL7** — the bureau can export all club dives for DAN decompression research (dan.org/PDE)

---

## 10. Privacy and GDPR

### 10.1 Your Privacy Settings

Go to **Privacy** to:

- **Consent management** — grant or revoke consent for data processing, marketing emails, and photo publication
- **Data export** — download all your personal data as JSON (profile, emails, licences, documents metadata, consent history)
- **Account erasure** — request deletion. Your data is anonymized (name → "ERASED"), documents deleted, social links removed. The anonymized record is kept for audit integrity.

### 10.2 Cookie Consent

First-time visitors see a cookie consent banner. No tracking occurs before you accept.

### 10.3 Minors

For members under 18, a guardian must be linked to the account. The guardian manages consents (general, events, photos, medical) until the member turns 18, at which point they manage their own consents via the Privacy page.

---

## 11. Buddy Finder

1. Click **Buddies** in the nav
2. See open buddy requests from other members
3. **Post a Request** — "Looking for a buddy for Remerschen quarry, Saturday 10am, max 20m"
4. Other members can respond with a message
5. Contact the buddy directly to coordinate
6. **Close** the request after the dive

---

## 12. Technical Articles

The club maintains a library of diving theory articles with original SVG diagrams, all available in 11 languages:

### Physics
- **Archimedes' Principle** — buoyancy forces, positive/negative/neutral
- **Mariotte's Law (Boyle)** — pressure and volume relationship
- **Henry's Law** — gas dissolution, nitrogen saturation
- **Dalton's Law** — partial pressures, toxicity thresholds

### Physiology & Safety
- **Decompression models** — Haldanien vs VPM/RGBM, gradient factors
- **Nitrogen narcosis** — depth-graded severity chart
- **Hyperoxia** — oxygen toxicity, PpO₂ limits, VENTID mnemonic
- **Barotraumas** — squeeze zones, equalization

### Practical Techniques
- **Ear equalization** — 5 techniques: Valsalva, Frenzel, BTV, Toynbee, Lowry
- **SMB inflation** — 3 methods: octopus, mouth, inflator
- **Buddy check** — BWRAF + 4 alternative mnemonics
- **Successive dives** — consecutive vs successive, breached deco protocol
- **Gradient factors** — GF Low/High explained, recommended settings

### Gear & Planning
- **Dive computer export guide** — brand-by-brand instructions
- **MN90 tables** — planning with French navy tables
- **Nitrox** — what it is and why use it
- **Pre-dive checklist** — nothing forgotten

### Safety & First Aid
- **Dive signs** — underwater communication
- **First aid** — life-saving gestures for diving accidents

---

## 13. Free Trial

If you're interested in trying diving before committing:

1. Visit `/trial` (linked from the welcome page)
2. Fill in: name, email, phone, preferred date, message
3. Submit — the club admin will contact you to schedule the trial

## 14. Equipment and Loans

### 14.1 Borrowing Equipment (Quick Loan)

From your **Profile → Equipment** tab, you can borrow club gear:

1. Select equipment from the dropdowns grouped by type (Tanks, BCDs, Regulators, etc.)
2. BCDs are sorted by proximity to your preferred size (set in your profile)
3. Click **Borrow** — the items are immediately marked as on loan to you
4. Return equipment by visiting the same tab or asking an admin

### 14.2 Equipment Inventory (Admin)

Admins can manage the full inventory at **Admin → Equipment**:

- Each item has a **short number** (physical marking, e.g. "5", "M01") for easy identification
- Filter by type, sort by any column, search by name or number
- **Location tracking**: items can be at "Entrepôt", "Piscine Merl", etc.
- **Loanable flag**: only items marked as loanable appear in member quick-loan dropdowns
- **Return button**: one-click return directly from the equipment list for on-loan items
- **Maintenance scheduling**: set rules (e.g. "every 12 months") with auto-calculated next dates

## 15. Documents and Library

The **Documents** section (Resources → Documents) provides access to club files:

- Bureau members can upload files organized in folders
- Files can be marked public (visible to all members) or private (bureau only)
- Common uses: meeting minutes, regulations, insurance documents, training materials

## 16. Newsletters

Bureau members can create rich HTML newsletters at **Admin → Newsletters**:

1. Compose the newsletter with the rich text editor
2. Submit for approval — other bureau members review and approve
3. Once approved, send to all members or a targeted group
4. Full send log tracks delivery

## 17. Push Notifications

If you install the app as a PWA (Add to Home Screen), you can receive push notifications for:

- Event updates and new registrations
- Newsletter publications
- Important club announcements

Enable notifications when prompted, or manage them in your profile settings.

## 18. Instructor Availability Calendar

Members can view the **Instructor Availability** calendar (Calendar → Instructor Availability):

- See which instructors are available for each training session
- Color-coded initials identify each instructor at a glance
- A legend at the bottom shows full names with their assigned colors

### For Instructors

- Click the ➕ button next to any event to mark yourself as available
- This also auto-registers you for the event if registration is open
- Click ✅ to remove your availability (and cancel registration)
- Fill in your **instructor bio** in Profile → Diving tab to appear on the public instructors page
