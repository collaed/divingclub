"""
Demo scenario screenshots — captures the full event lifecycle.
Run: python3 tests/e2e/screenshot_demo.py
"""
from playwright.sync_api import sync_playwright
import os

SCREENSHOTS = os.path.join(os.path.dirname(__file__), "screenshots", "demo")
BASE = "https://test.clubcep.eu"
os.makedirs(SCREENSHOTS, exist_ok=True)


def shot(page, name):
    path = os.path.join(SCREENSHOTS, f"{name}.png")
    page.screenshot(path=path, full_page=True)
    print(f"  📸 {name}")


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

    # 1. Modern tile dashboard
    print("\n🏠 Tile Dashboard")
    page.goto(f"{BASE}/home4")
    page.wait_for_load_state("networkidle")
    shot(page, "01_tile_dashboard")

    # 2. Events calendar
    print("\n📅 Calendar")
    page.goto(f"{BASE}/events")
    page.wait_for_load_state("networkidle")
    shot(page, "02_events_calendar")

    # 3. A dive event with registrations, groups, photos
    print("\n🤿 Dive Event")
    # Find the Gravière event
    page.goto(f"{BASE}/events")
    page.wait_for_load_state("networkidle")
    grav_link = page.locator("a", has_text="Gravière du Fort")
    if grav_link.count() > 0:
        grav_link.first.click()
        page.wait_for_load_state("networkidle")
        shot(page, "03_event_graviere")

    # 4. A trip event with deposit schedule
    print("\n✈️ Trip Event")
    page.goto(f"{BASE}/events")
    page.wait_for_load_state("networkidle")
    trip_link = page.locator("a", has_text="Juan-les-Pins")
    if trip_link.count() > 0:
        trip_link.first.click()
        page.wait_for_load_state("networkidle")
        shot(page, "04_event_trip_deposits")

    # 5. Admin payments list
    print("\n💰 Payments")
    page.goto(f"{BASE}/admin/payments")
    page.wait_for_load_state("networkidle")
    shot(page, "05_admin_payments")

    # 6. External registrations
    print("\n🤝 External Registrations")
    page.goto(f"{BASE}/admin/partnerships/registrations")
    page.wait_for_load_state("networkidle")
    shot(page, "06_external_registrations")

    # 7. Email log / communications
    print("\n📧 Communications")
    page.goto(f"{BASE}/admin/email")
    page.wait_for_load_state("networkidle")
    shot(page, "07_email_log")

    # 8. Instructor calendar (teal)
    print("\n🏊 Instructor Calendar")
    page.goto(f"{BASE}/availability")
    page.wait_for_load_state("networkidle")
    shot(page, "08_instructor_calendar")

    # 9. Admin dashboard with worklist
    print("\n📋 Admin Dashboard")
    page.goto(f"{BASE}/admin/dashboard")
    page.wait_for_load_state("networkidle")
    shot(page, "09_admin_dashboard")

    # 10. Email stats
    print("\n📊 Email Stats")
    page.goto(f"{BASE}/admin/email-stats")
    page.wait_for_load_state("networkidle")
    shot(page, "10_email_stats")

    # 11. Home3 public page (logout first)
    print("\n🌐 Public Landing")
    page.goto(f"{BASE}/home3")
    page.wait_for_load_state("networkidle")
    shot(page, "11_home3_with_photos")

    ctx.close()
    browser.close()
    print(f"\n✅ All screenshots saved to {SCREENSHOTS}/")
