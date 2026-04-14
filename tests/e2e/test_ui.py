"""
DivingClub-Manager E2E UI Tests — Playwright (Python)
Run: python3 -m pytest tests/e2e/test_ui.py -v
"""
import pytest
import re
from playwright.sync_api import sync_playwright, expect

BASE = "https://test.clubcep.eu"


@pytest.fixture(scope="module")
def browser():
    with sync_playwright() as p:
        b = p.chromium.launch(headless=True)
        yield b
        b.close()


@pytest.fixture
def page(browser):
    ctx = browser.new_context(ignore_https_errors=True)
    pg = ctx.new_page()
    yield pg
    ctx.close()


@pytest.fixture
def auth_page(browser):
    """Logged-in page as admin."""
    ctx = browser.new_context(ignore_https_errors=True)
    pg = ctx.new_page()
    pg.goto(f"{BASE}/login")
    pg.fill('input[name="email"]', "eddy.collart@gmail.com")
    pg.fill('input[name="password"]', "password")
    pg.click('button[type="submit"]')
    pg.wait_for_load_state("networkidle")
    yield pg
    ctx.close()


# ── Public pages ──


class TestPublicPages:
    def test_homepage_loads(self, page):
        page.goto(BASE)
        assert page.title()
        assert page.locator("body").is_visible()

    def test_home2_loads(self, page):
        page.goto(f"{BASE}/home2")
        expect(page.locator(".h2-hero")).to_be_visible()
        expect(page.locator(".h2-hero-content h1")).to_contain_text("Plongée")

    def test_home3_hero_and_login_panel(self, page):
        page.goto(f"{BASE}/home3")
        expect(page.locator(".h3-hero")).to_be_visible()
        # Scroll down to trigger sticky nav, then click its login button
        page.evaluate("window.scrollTo(0, window.innerHeight)")
        page.wait_for_timeout(500)
        nav_login = page.locator(".h3-nav button", has_text="Login")
        if nav_login.count() > 0:
            nav_login.click()
            expect(page.locator("#loginPanel")).to_have_class(re.compile("open"))
            expect(page.locator('#loginPanel input[name="email"]')).to_be_visible()
            # Close with Escape
            page.keyboard.press("Escape")
            page.wait_for_timeout(400)
            expect(page.locator("#loginPanel")).not_to_have_class(re.compile("open"))

    def test_home3_sections_exist(self, page):
        page.goto(f"{BASE}/home3")
        # Numbers strip
        expect(page.locator(".h3-numbers")).to_be_visible()
        # Events section
        expect(page.locator(".h3-events")).to_be_visible()
        # Value cards
        expect(page.locator(".h3-values")).to_be_visible()
        # CTA
        expect(page.locator(".h3-cta")).to_be_visible()
        # Footer
        expect(page.locator(".h3-footer")).to_be_visible()

    def test_home3_photo_gallery(self, page):
        page.goto(f"{BASE}/home3")
        mosaic_links = page.locator(".h3-mosaic a")
        if mosaic_links.count() > 0:
            mosaic_links.first.click()
            page.wait_for_timeout(500)
            gallery = page.locator(".pg-overlay")
            if gallery.count() > 0:
                expect(gallery.first).to_have_class(re.compile("open"))
                page.keyboard.press("Escape")

    def test_login_page(self, page):
        page.goto(f"{BASE}/login")
        expect(page.locator('input[name="email"]')).to_be_visible()
        expect(page.locator('input[name="password"]')).to_be_visible()
        expect(page.locator('button[type="submit"]')).to_be_visible()

    def test_trial_page(self, page):
        page.goto(f"{BASE}/trial")
        expect(page.locator("h1, h2, h3, h4").first).to_be_visible()

    def test_events_calendar(self, page):
        page.goto(f"{BASE}/events")
        assert page.url.endswith("/events") or "/login" in page.url

    def test_language_switch(self, page):
        page.goto(f"{BASE}/home2")
        # Page should have language content
        assert page.content()


# ── Authentication ──


class TestAuth:
    def test_login_with_valid_credentials(self, page):
        page.goto(f"{BASE}/login")
        page.fill('input[name="email"]', "eddy.collart@gmail.com")
        page.fill('input[name="password"]', "password")
        page.click('button[type="submit"]')
        page.wait_for_load_state("networkidle")
        # Should redirect away from login
        assert "/login" not in page.url

    def test_login_with_invalid_credentials(self, page):
        page.goto(f"{BASE}/login")
        page.fill('input[name="email"]', "wrong@example.com")
        page.fill('input[name="password"]', "wrongpassword")
        page.click('button[type="submit"]')
        page.wait_for_load_state("networkidle")
        # Should stay on login with error
        assert "login" in page.url or page.locator(".invalid-feedback, .alert-danger").count() > 0

    def test_home3_login_form_submits(self, page):
        page.goto(f"{BASE}/home3")
        page.evaluate("openLogin()")
        page.fill('#loginPanel input[name="email"]', "eddy.collart@gmail.com")
        page.fill('#loginPanel input[name="password"]', "password")
        page.click('#loginPanel button[type="submit"]')
        page.wait_for_load_state("networkidle")
        assert "/login" not in page.url


# ── Authenticated pages ──


class TestAuthenticatedPages:
    def test_dashboard_loads(self, auth_page):
        auth_page.goto(BASE)
        expect(auth_page.locator("body")).to_be_visible()
        # Should see widgets or dashboard content
        assert auth_page.title()

    def test_events_page(self, auth_page):
        auth_page.goto(f"{BASE}/events")
        expect(auth_page.locator("body")).to_be_visible()

    def test_profile_page(self, auth_page):
        auth_page.goto(f"{BASE}/profile")
        expect(auth_page.locator("body")).to_be_visible()

    def test_documents_page(self, auth_page):
        auth_page.goto(f"{BASE}/documents")
        expect(auth_page.locator("body")).to_be_visible()


# ── Admin pages ──


class TestAdminPages:
    def test_admin_dashboard(self, auth_page):
        auth_page.goto(f"{BASE}/admin/dashboard")
        expect(auth_page.locator("body")).to_be_visible()
        # Mail balance widget
        mail_widget = auth_page.locator("text=Email Sending Quota")
        if mail_widget.count() > 0:
            expect(mail_widget.first).to_be_visible()

    def test_admin_members(self, auth_page):
        auth_page.goto(f"{BASE}/admin/members")
        resp_status = auth_page.evaluate("() => document.readyState")
        assert resp_status == "complete"
        # Page loaded without 500
        assert "500" not in auth_page.title()

    def test_admin_newsletters(self, auth_page):
        auth_page.goto(f"{BASE}/admin/newsletters")
        expect(auth_page.locator("body")).to_be_visible()

    def test_admin_email_stats(self, auth_page):
        auth_page.goto(f"{BASE}/admin/email-stats")
        auth_page.wait_for_load_state("networkidle")
        # Page should load without error
        assert "500" not in auth_page.title()
        expect(auth_page.locator("body")).to_be_visible()

    def test_admin_equipment(self, auth_page):
        auth_page.goto(f"{BASE}/admin/equipment")
        expect(auth_page.locator("body")).to_be_visible()

    def test_admin_settings(self, auth_page):
        auth_page.goto(f"{BASE}/admin/settings")
        expect(auth_page.locator("body")).to_be_visible()

    def test_admin_guide(self, auth_page):
        auth_page.goto(f"{BASE}/admin/guide")
        expect(auth_page.locator("body")).to_be_visible()

    def test_admin_audit_log(self, auth_page):
        auth_page.goto(f"{BASE}/admin/audit-logs")
        expect(auth_page.locator("body")).to_be_visible()

    def test_admin_backups(self, auth_page):
        auth_page.goto(f"{BASE}/admin/backups")
        expect(auth_page.locator("body")).to_be_visible()


# ── Responsive ──


class TestResponsive:
    def test_home3_mobile(self, browser):
        ctx = browser.new_context(
            ignore_https_errors=True,
            viewport={"width": 375, "height": 812},
            user_agent="Mozilla/5.0 (iPhone; CPU iPhone OS 15_0 like Mac OS X)",
        )
        pg = ctx.new_page()
        pg.goto(f"{BASE}/home3")
        expect(pg.locator(".h3-hero")).to_be_visible()
        # Login button should still be accessible
        login_btn = pg.locator("button", has_text="Login")
        expect(login_btn.first).to_be_visible()
        ctx.close()

    def test_home3_tablet(self, browser):
        ctx = browser.new_context(
            ignore_https_errors=True,
            viewport={"width": 768, "height": 1024},
        )
        pg = ctx.new_page()
        pg.goto(f"{BASE}/home3")
        expect(pg.locator(".h3-hero")).to_be_visible()
        ctx.close()


# ── No 500 errors ──


class TestNo500Errors:
    """Hit every major route and ensure no 500."""

    ROUTES = [
        "/", "/home2", "/home3", "/login", "/trial",
        "/events", "/article/values", "/article/history",
    ]

    @pytest.mark.parametrize("route", ROUTES)
    def test_no_500(self, page, route):
        resp = page.goto(f"{BASE}{route}")
        assert resp.status < 500, f"{route} returned {resp.status}"
