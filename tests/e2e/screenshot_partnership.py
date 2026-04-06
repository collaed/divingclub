"""
Partnership flow screenshots — captures the full pairing workflow between two club instances.
Run: python3 tests/e2e/screenshot_partnership.py
"""
from playwright.sync_api import sync_playwright
import os

SCREENSHOTS = os.path.join(os.path.dirname(__file__), "screenshots")
CEP = "https://test.clubcep.eu"
ALSACE = "https://divingclub.ecb.pm"


def login(page, base_url, email="eddy.collart@gmail.com", password="password"):
    page.goto(f"{base_url}/login")
    page.fill('input[name="email"]', email)
    page.fill('input[name="password"]', password)
    page.click('button[type="submit"]')
    page.wait_for_load_state("networkidle")


def shot(page, name):
    path = os.path.join(SCREENSHOTS, f"{name}.png")
    page.screenshot(path=path, full_page=True)
    print(f"  📸 {name}.png")


with sync_playwright() as p:
    browser = p.chromium.launch(headless=True)

    # ── CEP Side (test.clubcep.eu) ──
    print("\n🔵 CEP (test.clubcep.eu)")
    ctx = browser.new_context(ignore_https_errors=True, viewport={"width": 1280, "height": 900})
    cep = ctx.new_page()
    login(cep, CEP)

    # 1. Admin → Partnerships list
    cep.goto(f"{CEP}/admin/partnerships")
    cep.wait_for_load_state("networkidle")
    shot(cep, "01_cep_partnerships_list")

    # 2. Create partnership form
    cep.goto(f"{CEP}/admin/partnerships/create")
    cep.wait_for_load_state("networkidle")
    shot(cep, "02_cep_create_partnership")

    # 3. Partnership with Alsace (already created)
    cep.goto(f"{CEP}/admin/partnerships")
    cep.wait_for_load_state("networkidle")
    shot(cep, "03_cep_partnership_with_alsace")

    # 4. External registrations
    cep.goto(f"{CEP}/admin/partnerships/registrations")
    cep.wait_for_load_state("networkidle")
    shot(cep, "04_cep_external_registrations")

    # 5. The federated event page
    cep.goto(f"{CEP}/events/704")
    cep.wait_for_load_state("networkidle")
    shot(cep, "05_cep_federated_event")

    # 6. Admin dashboard
    cep.goto(f"{CEP}/admin/dashboard")
    cep.wait_for_load_state("networkidle")
    shot(cep, "06_cep_admin_dashboard")

    ctx.close()

    # ── Alsace Side (divingclub.ecb.pm) ──
    print("\n🟢 Alsace (divingclub.ecb.pm)")
    ctx2 = browser.new_context(ignore_https_errors=True, viewport={"width": 1280, "height": 900})
    alsace = ctx2.new_page()
    login(alsace, ALSACE, "admin@divingclub.eu", "password")

    # 7. Alsace partnerships list
    alsace.goto(f"{ALSACE}/admin/partnerships")
    alsace.wait_for_load_state("networkidle")
    shot(alsace, "07_alsace_partnerships_list")

    # 8. Alsace browses CEP's remote events
    # Find the partnership ID and navigate to remote events
    alsace.goto(f"{ALSACE}/admin/partnerships/1/remote-events")
    alsace.wait_for_load_state("networkidle")
    shot(alsace, "08_alsace_remote_cep_events")

    ctx2.close()

    browser.close()
    print(f"\n✅ All screenshots saved to {SCREENSHOTS}/")
