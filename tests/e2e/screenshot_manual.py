"""
User Manual Screenshots — structured chapter-by-chapter captures.
Run: python3 tests/e2e/screenshot_manual.py
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

    # Login as bureau master
    page.goto(f"{BASE}/login")
    page.fill('input[name="email"]', "eddy.collart@gmail.com")
    page.fill('input[name="password"]', "password")
    page.click('button[type="submit"]')
    page.wait_for_load_state("networkidle")

    # ── Ch1: Season Setup ──
    print("\n📖 Chapter 1: Season Setup")
    page.goto(f"{BASE}/admin/settings")
    page.wait_for_load_state("networkidle")
    shot(page, "ch01_01_admin_settings", "Admin settings overview")

    page.goto(f"{BASE}/admin/seasons")
    page.wait_for_load_state("networkidle")
    shot(page, "ch01_02_seasons_list", "Season list with 2025-2026 active")

    # ── Ch2: Calendar ──
    print("\n📖 Chapter 2: Calendar")
    page.goto(f"{BASE}/events?month=2026-04")
    page.wait_for_load_state("networkidle")
    shot(page, "ch02_01_calendar_april", "April 2026 calendar with generated events")

    page.goto(f"{BASE}/events?month=2026-05")
    page.wait_for_load_state("networkidle")
    shot(page, "ch02_02_calendar_may", "May 2026 — Pentecost holidays visible")

    page.goto(f"{BASE}/events?month=2026-06")
    page.wait_for_load_state("networkidle")
    shot(page, "ch02_03_calendar_june", "June 2026 — Fête nationale")

    # ── Ch3: Instructor Calendar ──
    print("\n📖 Chapter 3: Instructor Availability")
    page.goto(f"{BASE}/availability?month=2026-04")
    page.wait_for_load_state("networkidle")
    shot(page, "ch03_01_instructor_april", "Instructor planning April — teal theme, availability markers")

    page.goto(f"{BASE}/availability?month=2026-05")
    page.wait_for_load_state("networkidle")
    shot(page, "ch03_02_instructor_may", "May — some events without instructors (cancellation risk)")

    # ── Ch4: Event Detail & Registration ──
    print("\n📖 Chapter 4: Event Registration")
    # Find a pool event with registrations
    page.goto(f"{BASE}/events?month=2026-04")
    page.wait_for_load_state("networkidle")
    # Click first event link
    links = page.locator("a[href*='/events/']")
    if links.count() > 2:
        links.nth(2).click()
        page.wait_for_load_state("networkidle")
        shot(page, "ch04_01_event_detail", "Event detail with registrations, dive site, weather")

    # ── Ch5: Payments ──
    print("\n📖 Chapter 5: Payments & Reconciliation")
    page.goto(f"{BASE}/admin/payments")
    page.wait_for_load_state("networkidle")
    shot(page, "ch05_01_payments_list", "Payment records — Paid/Pending status, communication codes")

    page.goto(f"{BASE}/admin/payments?status=pending")
    page.wait_for_load_state("networkidle")
    shot(page, "ch05_02_payments_pending", "Pending payments only — outstanding amounts")

    # ── Ch6: Partnerships ──
    print("\n📖 Chapter 6: Inter-Club Partnerships")
    page.goto(f"{BASE}/admin/partnerships")
    page.wait_for_load_state("networkidle")
    shot(page, "ch06_01_partnerships", "Partnership with Plongée Alsace")

    page.goto(f"{BASE}/admin/partnerships/registrations")
    page.wait_for_load_state("networkidle")
    shot(page, "ch06_02_external_regs", "External registrations — approve/reject")

    # ── Ch7: Communications ──
    print("\n📖 Chapter 7: Event Communications")
    page.goto(f"{BASE}/admin/email")
    page.wait_for_load_state("networkidle")
    shot(page, "ch07_01_email_system", "Email templates, send to groups, log")

    # ── Ch8: Dive Groups ──
    print("\n📖 Chapter 8: Dive Group Planning")
    # Find a dive event with groups
    page.goto(f"{BASE}/events/704")
    page.wait_for_load_state("networkidle")
    shot(page, "ch08_01_event_with_groups", "Dive event with group planner")

    # ── Ch9: Dashboard ──
    print("\n📖 Chapter 9: Admin Dashboard")
    page.goto(f"{BASE}/admin/dashboard")
    page.wait_for_load_state("networkidle")
    shot(page, "ch09_01_dashboard", "Statistics, worklist, mail quota, scheduled tasks")

    # ── Ch10: Newsletter ──
    print("\n📖 Chapter 10: Newsletter")
    page.goto(f"{BASE}/admin/newsletters")
    page.wait_for_load_state("networkidle")
    shot(page, "ch10_01_newsletters_list", "Newsletter list")

    page.goto(f"{BASE}/admin/newsletters/create")
    page.wait_for_load_state("networkidle")
    shot(page, "ch10_02_newsletter_compose", "Newsletter composer — slots, decorations, teaser")

    # ── Ch11: Email Stats ──
    print("\n📖 Chapter 11: Email Delivery Stats")
    page.goto(f"{BASE}/admin/email-stats")
    page.wait_for_load_state("networkidle")
    shot(page, "ch11_01_email_stats", "Delivery tracking — opened/clicked/sent/failed per recipient")

    # ── Ch12: Modern Dashboard ──
    print("\n📖 Chapter 12: Member Dashboard")
    page.goto(f"{BASE}/home4")
    page.wait_for_load_state("networkidle")
    shot(page, "ch12_01_tile_dashboard", "Modern tile dashboard — quick actions, upcoming dives, articles")

    ctx.close()
    browser.close()
    print(f"\n✅ All manual screenshots saved to {SCREENSHOTS}/")
