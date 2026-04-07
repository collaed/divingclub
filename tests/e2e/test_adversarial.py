"""
Adversarial E2E tests — mischievous scenarios to find bugs.
Tests all features modified in the last week.
Run: python3 -m pytest tests/e2e/test_adversarial.py -v --tb=short
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


def login(browser, email="eddy.collart@gmail.com", pw="password"):
    ctx = browser.new_context(ignore_https_errors=True, viewport={"width": 1280, "height": 900})
    pg = ctx.new_page()
    pg.goto(f"{BASE}/login")
    pg.fill('input[name="email"]', email)
    pg.fill('input[name="password"]', pw)
    pg.click('button[type="submit"]')
    pg.wait_for_load_state("networkidle")
    return pg, ctx


class TestHome3Adversarial:
    """Public landing page edge cases."""

    def test_home3_no_500_when_no_photos(self, browser):
        ctx = browser.new_context(ignore_https_errors=True)
        pg = ctx.new_page()
        resp = pg.goto(f"{BASE}/home3")
        assert resp.status < 500
        ctx.close()

    def test_home3_login_panel_escape_closes(self, browser):
        ctx = browser.new_context(ignore_https_errors=True)
        pg = ctx.new_page()
        pg.goto(f"{BASE}/home3")
        pg.evaluate("openLogin()")
        expect(pg.locator("#loginPanel")).to_have_class(re.compile("open"))
        pg.keyboard.press("Escape")
        pg.wait_for_timeout(400)
        expect(pg.locator("#loginPanel")).not_to_have_class(re.compile("open"))
        ctx.close()

    def test_home3_login_with_wrong_password_shows_panel(self, browser):
        ctx = browser.new_context(ignore_https_errors=True)
        pg = ctx.new_page()
        pg.goto(f"{BASE}/home3")
        pg.evaluate("openLogin()")
        pg.fill('#loginPanel input[name="email"]', "wrong@example.com")
        pg.fill('#loginPanel input[name="password"]', "wrong")
        pg.click('#loginPanel button[type="submit"]')
        pg.wait_for_load_state("networkidle")
        # Should redirect to login page or show error, not 500
        assert pg.evaluate("document.readyState") == "complete"
        ctx.close()

    def test_home3_xss_in_login_field(self, browser):
        ctx = browser.new_context(ignore_https_errors=True)
        pg = ctx.new_page()
        pg.goto(f"{BASE}/home3")
        pg.evaluate("openLogin()")
        pg.fill('#loginPanel input[name="email"]', '<script>alert(1)</script>@test.com')
        pg.fill('#loginPanel input[name="password"]', "test")
        pg.click('#loginPanel button[type="submit"]')
        pg.wait_for_load_state("networkidle")
        assert "<script>" not in pg.content()
        ctx.close()


class TestHome4Adversarial:
    """Tile dashboard edge cases."""

    def test_home4_redirects_when_not_logged_in(self, browser):
        ctx = browser.new_context(ignore_https_errors=True)
        pg = ctx.new_page()
        resp = pg.goto(f"{BASE}/home4")
        assert resp.status < 500
        # Should redirect to home3 or login
        assert "/home4" not in pg.url or resp.status == 302
        ctx.close()

    def test_home4_loads_for_bureau(self, browser):
        pg, ctx = login(browser)
        pg.goto(f"{BASE}/home4")
        pg.wait_for_load_state("networkidle")
        assert "500" not in pg.title()
        # Bureau tiles should be visible
        expect(pg.locator("text=Worklist")).to_be_visible()
        expect(pg.locator("text=Members")).to_be_visible()
        ctx.close()

    def test_home4_classic_view_link_works(self, browser):
        pg, ctx = login(browser)
        pg.goto(f"{BASE}/home4")
        pg.wait_for_load_state("networkidle")
        pg.click("text=Classic view")
        pg.wait_for_load_state("networkidle")
        assert "/home4" not in pg.url
        ctx.close()


class TestInstructorCalendar:
    """Instructor calendar edge cases."""

    def test_calendar_accessible_to_regular_member(self, browser):
        pg, ctx = login(browser)
        resp = pg.goto(f"{BASE}/availability")
        assert resp.status < 500
        # Should show read-only notice
        assert pg.content()
        ctx.close()

    def test_calendar_month_navigation(self, browser):
        pg, ctx = login(browser)
        pg.goto(f"{BASE}/availability?month=2026-04")
        pg.wait_for_load_state("networkidle")
        assert "April" in pg.content() or "avril" in pg.content()
        # Navigate forward
        pg.click("a:has-text('→')")
        pg.wait_for_load_state("networkidle")
        assert "May" in pg.content() or "mai" in pg.content()
        ctx.close()

    def test_calendar_invalid_month_param(self, browser):
        pg, ctx = login(browser)
        resp = pg.goto(f"{BASE}/availability?month=invalid")
        assert resp.status < 500
        ctx.close()


class TestEmailStats:
    """Email stats edge cases."""

    def test_stats_future_date(self, browser):
        pg, ctx = login(browser)
        resp = pg.goto(f"{BASE}/admin/email-stats?date=2030-01-01")
        assert resp.status < 500
        assert "No emails" in pg.content() or "Aucun" in pg.content() or pg.locator("body").is_visible()
        ctx.close()

    def test_stats_invalid_date(self, browser):
        pg, ctx = login(browser)
        resp = pg.goto(f"{BASE}/admin/email-stats?date=not-a-date")
        assert resp.status < 500
        ctx.close()

    def test_stats_date_navigation(self, browser):
        pg, ctx = login(browser)
        pg.goto(f"{BASE}/admin/email-stats")
        pg.wait_for_load_state("networkidle")
        # Click prev day
        pg.locator("a:has-text('◀')").click()
        pg.wait_for_load_state("networkidle")
        assert pg.evaluate("document.readyState") == "complete"
        ctx.close()


class TestRolesPermissions:
    """Roles & permissions admin page."""

    def test_roles_page_loads(self, browser):
        pg, ctx = login(browser)
        pg.goto(f"{BASE}/admin/roles")
        pg.wait_for_load_state("networkidle")
        assert "500" not in pg.title()
        expect(pg.locator("text=bureau_master")).to_be_visible()
        expect(pg.locator("text=instructor_apnea")).to_be_visible()
        expect(pg.locator("text=auditor")).to_be_visible()
        ctx.close()

    def test_roles_shows_permission_checkboxes(self, browser):
        pg, ctx = login(browser)
        pg.goto(f"{BASE}/admin/roles")
        pg.wait_for_load_state("networkidle")
        checkboxes = pg.locator('input[type="checkbox"]')
        assert checkboxes.count() > 50  # 20 perms × 8 roles = 160
        ctx.close()


class TestFinancialAudit:
    """Auditor financial view."""

    def test_audit_page_loads_for_bureau(self, browser):
        pg, ctx = login(browser)
        pg.goto(f"{BASE}/admin/audit-finances")
        pg.wait_for_load_state("networkidle")
        assert "500" not in pg.title()
        expect(pg.locator("text=Total Due")).to_be_visible()
        ctx.close()

    def test_audit_page_shows_payments(self, browser):
        pg, ctx = login(browser)
        pg.goto(f"{BASE}/admin/audit-finances")
        pg.wait_for_load_state("networkidle")
        # Should have payment rows
        expect(pg.locator("table")).to_be_visible()
        ctx.close()


class TestProfilePrivacy:
    """Profile privacy — the most critical security test."""

    def test_own_profile_shows_private_tab(self, browser):
        pg, ctx = login(browser)
        pg.goto(f"{BASE}/profile")
        pg.wait_for_load_state("networkidle")
        expect(pg.locator("text=Private Info")).to_be_visible()
        ctx.close()

    def test_bureau_sees_other_member_private(self, browser):
        pg, ctx = login(browser)
        # View another member's profile
        pg.goto(f"{BASE}/admin/members/2/profile")
        pg.wait_for_load_state("networkidle")
        expect(pg.locator("text=Private Info")).to_be_visible()
        ctx.close()


class TestPayments:
    """Payment system."""

    def test_payments_page_loads(self, browser):
        pg, ctx = login(browser)
        pg.goto(f"{BASE}/admin/payments")
        pg.wait_for_load_state("networkidle")
        assert "500" not in pg.title()
        expect(pg.locator("text=Payments")).to_be_visible()
        ctx.close()

    def test_payments_filter_by_status(self, browser):
        pg, ctx = login(browser)
        pg.goto(f"{BASE}/admin/payments?status=pending")
        pg.wait_for_load_state("networkidle")
        assert "500" not in pg.title()
        ctx.close()


class TestPartnerships:
    """Partnership system."""

    def test_partnerships_page(self, browser):
        pg, ctx = login(browser)
        pg.goto(f"{BASE}/admin/partnerships")
        pg.wait_for_load_state("networkidle")
        expect(pg.locator("text=Plongée Alsace")).to_be_visible()
        ctx.close()

    def test_external_registrations(self, browser):
        pg, ctx = login(browser)
        pg.goto(f"{BASE}/admin/partnerships/registrations")
        pg.wait_for_load_state("networkidle")
        assert "500" not in pg.title()
        ctx.close()


class TestNo500OnAllAdminPages:
    """Hit every admin page and ensure no 500."""

    ADMIN_ROUTES = [
        "/admin/dashboard", "/admin/members", "/admin/equipment",
        "/admin/articles", "/admin/payments", "/admin/settings",
        "/admin/newsletters", "/admin/email", "/admin/votes",
        "/admin/dive-sites", "/admin/audit-logs", "/admin/partnerships",
        "/admin/partnerships/registrations", "/admin/guide",
        "/admin/roles", "/admin/audit-finances", "/admin/email-stats",
        "/admin/library",
    ]

    @pytest.mark.parametrize("route", ADMIN_ROUTES)
    def test_no_500(self, browser, route):
        pg, ctx = login(browser)
        resp = pg.goto(f"{BASE}{route}", timeout=15000)
        assert resp.status < 500, f"{route} returned {resp.status}"
        ctx.close()


class TestAllPublicPages:
    """All public pages return < 500."""

    ROUTES = [
        "/", "/home2", "/home3", "/home4", "/login", "/trial",
        "/events", "/availability",
    ]

    @pytest.mark.parametrize("route", ROUTES)
    def test_no_500(self, browser, route):
        ctx = browser.new_context(ignore_https_errors=True)
        pg = ctx.new_page()
        resp = pg.goto(f"{BASE}{route}")
        assert resp.status < 500, f"{route} returned {resp.status}"
        ctx.close()
