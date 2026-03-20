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
3. Or click a social login button (Google, Facebook, Microsoft)
4. Check your inbox for a verification email → click the link
5. You're in! Your account has the "Member" role by default

### 1.2 Switching Language

Click the language flag in the top navigation bar. 11 languages are available: English, French, German, Luxembourgish, Portuguese, Italian, Dutch, Spanish, Polish, Hungarian, Romanian. Your choice is saved for future visits.

---

## 2. Your Profile

### 2.1 Personal Information

Go to **My Profile → Info tab**:

- Name, nationality, sex, date of birth
- Phone numbers, club email
- Profile photo (upload or delete)

### 2.2 Private Information

**My Profile → Private Info tab**:

- Full address (street, postal code, city, country)
- Emergency contact (name, phone, relationship)

This information is only visible to the bureau.

### 2.3 Diving Credentials

**My Profile → Diving tab**:

- Add your federation licence (e.g., FFESSM, LIFRAS, PADI)
- Add your certification level (e.g., FFESSM N2, PADI AOW) with the date obtained
- Set your primary certification — this is used for dive group planning

### 2.4 Medical Certificate

**My Profile → Medical Cert tab**:

1. Upload your medical certificate (PDF or photo)
2. Select the type: General Practitioner, ENT, or Sports Medicine
3. Enter the exam date
4. Wait for bureau verification

The system tracks expiry automatically based on your federation's rules and sends reminders at 30, 15, 7, and 0 days before expiration. You cannot register for dive events with an expired certificate.

### 2.5 Language Preference

**My Profile → Language tab** — set your preferred language for the interface and article translations.

---

## 3. Browsing the Club

### 3.1 Home Page

After login, the home page shows:

- Latest articles as cards with type badges and colored borders
- Sidebar with upcoming events and quick links
- Trip proposals with embedded vote forms

### 3.2 Articles

Click any article to read the full content. Features:

- Image gallery with captions
- Translation tabs — read in your preferred language, switch to the original or any other language
- Auto-translated content is marked with a 🤖 indicator
- Threaded comments (up to 3 levels deep)
- Previous/Next navigation between articles

### 3.3 Calendar

Browse events in month, week, or day view. Click any event for details including:

- Date, time, location (with Google Maps link)
- Instructor, certification requirements
- Registration button (or waiting list if full)

### 3.4 Members Directory

**Info → Members Directory** — search for fellow members by name.

### 3.5 Trombinoscope

**Info → Trombinoscope** — photo grid of all members.

### 3.6 Documents

**Info → Documents** — download public club files (statutes, minutes, etc.).

---

## 4. Events and Registration

### 4.1 Registering for an Event

1. Open the event from the Calendar or home page
2. Review the details (location, depth, requirements)
3. Click **Register**
4. If the event is full, you're placed on the **waiting list** — you'll be auto-promoted if someone cancels

### 4.2 Cancelling a Registration

1. Go to **My Profile → Registrations tab**
2. Click the event → **Cancel Registration**
3. Any pending payment is automatically deleted
4. The next person on the waiting list is auto-promoted

---

## 5. Dues and Payments

### 5.1 Dues Calculator

Visit `/dues` (no login required) to estimate your annual fee:

1. Select your membership status
2. See the calculated amount with breakdown
3. Scan the **SEPA QR code** with your banking app → pre-filled transfer

### 5.2 Paying Your Dues

1. You'll receive an email with your amount and a structured communication string
2. Make a bank transfer using the exact communication string
3. The bureau reconciles payments — you'll see "Paid" status on your profile

---

## 6. Classifieds

### 6.1 Posting an Ad

1. Click **Classifieds** in the nav
2. Click **Post a Classified**
3. Fill in: title, description, photo
4. Your ad is active for 30 days

### 6.2 Managing Your Ads

- **Extend** — push the expiry 30 more days
- **Delete** — remove the ad immediately
- **Renew** — reactivate an expired ad

---

## 7. Voting

### 7.1 Simple Polls

You'll receive an email with a voting link. Click it to:

1. See the options (e.g., trip destinations)
2. Select one or more options
3. Submit — you can change your vote later
4. See live results (if enabled)

### 7.2 Elections

For formal elections (e.g., bureau election):

1. Click the voting link from your email
2. Read the warning: "Your vote is anonymous and irreversible"
3. Select one candidate
4. Cast your vote — you cannot change it
5. Results are hidden until the vote closes

---

## 8. Instructor Features

### 8.1 Availability Calendar

If you have the Instructor role:

1. Click **Availability** in the nav
2. Click **+** on a date to mark yourself available
3. Select the activity type (Pool, Apnea, Theory, etc.)
4. Your initials appear as a colored badge
5. Click your initial to remove availability

### 8.2 Instructor Bio

**My Profile → Diving tab → Instructor Profile**:

- Experience & Background
- Specialties & Interests
- What motivates you?

This appears on the public **Our Instructors** page.

---

## 9. Dive Data

### 9.1 Importing from Your Dive Computer

You can upload dive logs from your computer in UDDF format:

1. Export from your dive computer's app (Mares SSI, Shearwater Cloud, Suunto DM5, etc.)
2. If your computer doesn't export UDDF, use **Subsurface** (free, open source) as a converter
3. Go to your dive log → **Import UDDF** → select the file

See the article "Exporter vos plongées — Guide par marque d'ordinateur" for brand-specific instructions.

### 9.2 Exporting

- **UDDF export** — download your dive logs in universal format
- **DAN DL7** — the bureau can export all club dives for DAN research

---

## 10. Privacy and GDPR

### 10.1 Your Privacy Settings

Go to **Privacy** to:

- Toggle photo publication consent
- Download all your personal data as JSON
- Request account erasure (anonymization)

### 10.2 Minors

For members under 18, a guardian must be linked to the account. The guardian manages consents (events, medical, photos) until the member turns 18.

---

## 11. Buddy Finder

1. Click **Buddies** in the nav
2. See open buddy requests from other members
3. **Post a Request** — describe when, where, and what depth
4. Other members can respond
5. **Close** the request after the dive

---

## 12. PWA — Install on Your Phone

The site works as a Progressive Web App:

1. Open the site in Chrome/Safari
2. Click "Add to Home Screen" (or the install prompt)
3. The app icon appears on your phone
4. Works offline with a fallback page

---

## 13. Technical Articles

The club maintains a library of diving theory articles with original SVG diagrams:

- **Physics**: Archimedes, Mariotte/Boyle, Henry, Dalton
- **Physiology**: Decompression models, narcosis, hyperoxia, barotraumas
- **Techniques**: Ear equalization, SMB inflation, buddy check, successive dives
- **Gear**: Dive computer export guide
- **Theory**: Gradient factors, MN90 tables, Nitrox

All articles are available in 11 languages with automatic translation.
