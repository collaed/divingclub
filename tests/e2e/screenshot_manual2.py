"""
User Manual Screenshots — Part 2: Missing chapters.
Run: python3 tests/e2e/screenshot_manual2.py
"""
from playwright.sync_api import sync_playwright
import os

SCREENSHOTS = os.path.join(os.path.dirname(__file__), "screenshots", "manual")
BASE = "https://test.clubcep.eu"
os.makedirs(SCREENSHOTS, exist_ok=True)


def shot(page, name, desc=""):
    path = os.path.join(SCREENSHOTS, f"{name}.png")
    page.screenshot(path=path, full_page=True)
    print(f"  📸 {name} — {desc}")


with sync_playwright() as p:
    browser = p.chromium.launch(headless=True)
    ctx = browser.new_context(ignore_https_errors=True, viewport={"width": 1280, "height": 900})
    page = ctx.new_page()

    # Login
    page.goto(f"{BASE}/login")
    page.fill('input[name="email"]', "eddy.collart@gmail.com")
    page.fill('input[name="password"]', "password")
    page.click('button[type="submit"]')
    page.wait_for_load_state("networkidle")

    # ── Members ──
    print("\n📖 Members")
    page.goto(f"{BASE}/admin/members")
    page.wait_for_load_state("networkidle")
    shot(page, "ch13_01_members_list", "Member list with search and roles")

    # Find a member profile — navigate directly
    page.goto(f"{BASE}/admin/members/1/profile")
    page.wait_for_load_state("networkidle")
    shot(page, "ch13_02_member_profile", "Member profile — details, certs, medical, documents")

    # Own profile
    page.goto(f"{BASE}/profile")
    page.wait_for_load_state("networkidle")
    shot(page, "ch13_03_my_profile", "My Profile — edit personal info")

    # ── Medical ──
    print("\n📖 Medical Compliance")
    page.goto(f"{BASE}/admin/settings")
    page.wait_for_load_state("networkidle")
    # Scroll to medical rules section
    page.evaluate("document.querySelector('[id*=medical], h3:has(+ table)')?.scrollIntoView()")
    page.wait_for_timeout(300)
    shot(page, "ch14_01_medical_rules", "Medical compliance rules per federation")

    # ── Equipment ──
    print("\n📖 Equipment")
    page.goto(f"{BASE}/admin/equipment")
    page.wait_for_load_state("networkidle")
    shot(page, "ch15_01_equipment_list", "Equipment inventory with status and loans")

    page.goto(f"{BASE}/admin/equipment/create")
    page.wait_for_load_state("networkidle")
    shot(page, "ch15_02_equipment_create", "Add new equipment")

    # ── Articles / CMS ──
    print("\n📖 Articles & CMS")
    page.goto(f"{BASE}/admin/articles")
    page.wait_for_load_state("networkidle")
    shot(page, "ch16_01_articles_list", "Article list with types and search")

    page.goto(f"{BASE}/admin/articles/create")
    page.wait_for_load_state("networkidle")
    shot(page, "ch16_02_article_create", "Create article with TinyMCE editor")

    # Classifieds
    page.goto(f"{BASE}/classifieds")
    page.wait_for_load_state("networkidle")
    shot(page, "ch16_03_classifieds", "Classifieds — gear for sale, buddy requests")

    # ── Voting ──
    print("\n📖 Voting")
    page.goto(f"{BASE}/admin/votes")
    page.wait_for_load_state("networkidle")
    shot(page, "ch17_01_votes_list", "Votes and elections list")

    page.goto(f"{BASE}/admin/votes/create")
    page.wait_for_load_state("networkidle")
    shot(page, "ch17_02_vote_create", "Create vote — simple or election mode")

    # ── Auth ──
    print("\n📖 Authentication")
    # Impersonation
    page.goto(f"{BASE}/admin/members")
    page.wait_for_load_state("networkidle")
    shot(page, "ch18_01_impersonation", "Member list with impersonate button")

    # ── Theme ──
    print("\n📖 Theme & Homepage")
    page.goto(f"{BASE}/admin/settings")
    page.wait_for_load_state("networkidle")
    shot(page, "ch19_01_theme_settings", "Theme presets and custom colors")

    # Homepage widget editor
    page.goto(BASE)
    page.wait_for_load_state("networkidle")
    shot(page, "ch19_02_homepage_widgets", "Homepage with configurable widgets")

    # ── Backup ──
    print("\n📖 Backup")
    try:
        page.goto(f"{BASE}/admin/backups", timeout=15000)
        page.wait_for_load_state("networkidle", timeout=10000)
        shot(page, "ch20_01_backups", "Backup management — create, download, delete")
    except Exception:
        print("  ⚠️ Backup page timed out, skipping")

    # ── Documents ──
    print("\n📖 Documents")
    page.goto(f"{BASE}/admin/library")
    page.wait_for_load_state("networkidle")
    shot(page, "ch21_01_library", "Document library — folders, upload, search")

    page.goto(f"{BASE}/documents")
    page.wait_for_load_state("networkidle")
    shot(page, "ch21_02_member_documents", "Member document browser — My Documents")

    # ── Trial ──
    print("\n📖 Free Trial")
    page.goto(f"{BASE}/trial")
    page.wait_for_load_state("networkidle")
    shot(page, "ch22_01_trial_page", "Public trial dive request form")

    page.goto(f"{BASE}/admin/trials")
    page.wait_for_load_state("networkidle")
    shot(page, "ch22_02_trial_admin", "Admin — manage trial requests")

    # ── GDPR ──
    print("\n📖 GDPR")
    page.goto(f"{BASE}/admin/gdpr")
    page.wait_for_load_state("networkidle")
    shot(page, "ch23_01_gdpr", "GDPR consent management")

    # ── i18n ──
    print("\n📖 Language")
    page.goto(f"{BASE}/home2")
    page.wait_for_load_state("networkidle")
    shot(page, "ch24_01_language", "Language selector — 15 locales")

    # ── Dive Sites ──
    print("\n📖 Dive Sites")
    page.goto(f"{BASE}/admin/dive-sites")
    page.wait_for_load_state("networkidle")
    shot(page, "ch25_01_dive_sites", "Dive site database with conditions and safety")

    # ── Admin Guide ──
    print("\n📖 Admin Guide")
    page.goto(f"{BASE}/admin/guide")
    page.wait_for_load_state("networkidle")
    shot(page, "ch26_01_admin_guide", "In-app admin guide — 24 sections")

    # ── Audit Log ──
    print("\n📖 Audit Log")
    page.goto(f"{BASE}/admin/audit-logs")
    page.wait_for_load_state("networkidle")
    shot(page, "ch27_01_audit_log", "Audit log — all actions with old/new values")

    ctx.close()
    browser.close()
    print(f"\n✅ All screenshots saved to {SCREENSHOTS}/")
