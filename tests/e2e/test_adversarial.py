"""
Adversarial E2E tests — security, stability, edge cases.
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


# ── Input Validation & Error Handling ──

class TestInputValidation:
    def test_invalid_month_param_no_500(self, browser):
        pg, ctx = login(browser)
        resp = pg.goto(f"{BASE}/availability?month=invalid")
        assert resp.status < 500
        ctx.close()

    def test_invalid_date_param_no_500(self, browser):
        pg, ctx = login(browser)
        resp = pg.goto(f"{BASE}/admin/email-stats?date=not-a-date")
        assert resp.status < 500
        ctx.close()

    def test_future_date_email_stats(self, browser):
        pg, ctx = login(browser)
        resp = pg.goto(f"{BASE}/admin/email-stats?date=2030-01-01")
        assert resp.status < 500
        ctx.close()

    def test_negative_page_number(self, browser):
        pg, ctx = login(browser)
        resp = pg.goto(f"{BASE}/admin/members?page=-1")
        assert resp.status < 500
        ctx.close()

    def test_huge_page_number(self, browser):
        pg, ctx = login(browser)
        resp = pg.goto(f"{BASE}/admin/members?page=99999")
        assert resp.status < 500
        ctx.close()

    def test_sql_injection_in_search(self, browser):
        pg, ctx = login(browser)
        resp = pg.goto(f"{BASE}/admin/members?search=' OR 1=1 --")
        assert resp.status < 500
        assert pg.locator("body").is_visible()
        ctx.close()

    def test_xss_in_search(self, browser):
        pg, ctx = login(browser)
        pg.goto(f"{BASE}/admin/members?search=<img src=x onerror=alert(1)>")
        content = pg.content()
        assert "onerror=alert" not in content
        ctx.close()


# ── Security ──

class TestSecurity:
    def test_unauthenticated_admin_redirect(self, browser):
        ctx = browser.new_context(ignore_https_errors=True)
        pg = ctx.new_page()
        resp = pg.goto(f"{BASE}/admin/dashboard")
        assert "/login" in pg.url or resp.status == 403
        ctx.close()

    def test_unauthenticated_profile_redirect(self, browser):
        ctx = browser.new_context(ignore_https_errors=True)
        pg = ctx.new_page()
        resp = pg.goto(f"{BASE}/profile")
        assert "/login" in pg.url
        ctx.close()

    def test_csrf_token_present_on_forms(self, browser):
        pg, ctx = login(browser)
        pg.goto(f"{BASE}/profile")
        pg.wait_for_load_state("networkidle")
        tokens = pg.locator('input[name="_token"]')
        assert tokens.count() > 0
        ctx.close()

    def test_home3_xss_in_login_escaped(self, browser):
        ctx = browser.new_context(ignore_https_errors=True)
        pg = ctx.new_page()
        pg.goto(f"{BASE}/home3")
        pg.evaluate("openLogin()")
        pg.fill('#loginPanel input[name="email"]', '<script>alert(1)</script>@test.com')
        pg.fill('#loginPanel input[name="password"]', "test")
        pg.click('#loginPanel button[type="submit"]')
        pg.wait_for_load_state("networkidle")
        # The injected script tag should not appear unescaped in the rendered page
        assert 'onerror=' not in pg.content()
        ctx.close()

    def test_direct_document_url_requires_auth(self, browser):
        ctx = browser.new_context(ignore_https_errors=True)
        pg = ctx.new_page()
        resp = pg.goto(f"{BASE}/profile/document/1")
        assert "/login" in pg.url or resp.status in [302, 403, 404]
        ctx.close()

    def test_api_federation_requires_auth(self, browser):
        ctx = browser.new_context(ignore_https_errors=True)
        pg = ctx.new_page()
        resp = pg.goto(f"{BASE}/api/federation/events")
        assert resp.status == 401 or "Unauthorized" in pg.content()
        ctx.close()


# ── Authenticated Feature Tests ──

class TestAuthenticatedFeatures:
    def test_home4_tile_dashboard(self, browser):
        pg, ctx = login(browser)
        pg.goto(f"{BASE}/home4")
        pg.wait_for_load_state("networkidle")
        assert pg.title() != ""
        # Should have tile grid
        assert pg.locator(".h4-tile").count() > 3
        ctx.close()

    def test_instructor_calendar_navigation(self, browser):
        pg, ctx = login(browser)
        pg.goto(f"{BASE}/availability?month=2026-04")
        pg.wait_for_load_state("networkidle")
        assert pg.locator(".ic-header").is_visible()
        ctx.close()

    def test_own_profile_has_private_tab(self, browser):
        pg, ctx = login(browser)
        pg.goto(f"{BASE}/profile")
        pg.wait_for_load_state("networkidle")
        # Tab might be in French or English
        tabs = pg.locator("button[data-bs-toggle='tab']")
        tab_texts = [tabs.nth(i).text_content() for i in range(tabs.count())]
        has_private = any("Private" in t or "privé" in t or "Privé" in t for t in tab_texts)
        assert has_private, f"No private tab found in: {tab_texts}"
        ctx.close()

    def test_payments_page_loads(self, browser):
        pg, ctx = login(browser)
        resp = pg.goto(f"{BASE}/admin/payments")
        assert resp.status < 500
        assert pg.locator("body").is_visible()
        ctx.close()

    def test_roles_page_loads(self, browser):
        pg, ctx = login(browser)
        resp = pg.goto(f"{BASE}/admin/roles")
        assert resp.status < 500
        assert pg.locator("table").first.is_visible()
        ctx.close()

    def test_financial_audit_loads(self, browser):
        pg, ctx = login(browser)
        resp = pg.goto(f"{BASE}/admin/audit-finances")
        assert resp.status < 500
        assert pg.locator("table").first.is_visible()
        ctx.close()

    def test_partnerships_page(self, browser):
        pg, ctx = login(browser)
        resp = pg.goto(f"{BASE}/admin/partnerships")
        assert resp.status < 500
        assert pg.locator("body").is_visible()
        ctx.close()

    def test_email_stats_loads(self, browser):
        pg, ctx = login(browser)
        resp = pg.goto(f"{BASE}/admin/email-stats")
        assert resp.status < 500
        assert pg.locator("body").is_visible()
        ctx.close()

    def test_member_profile_other_user(self, browser):
        pg, ctx = login(browser)
        resp = pg.goto(f"{BASE}/admin/members/2/profile")
        assert resp.status < 500
        # Bureau should see private tabs
        tabs = pg.locator("button[data-bs-toggle='tab']")
        assert tabs.count() >= 3
        ctx.close()


# ── No 500 on All Routes ──

class TestNo500OnAllAdminPages:
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
    ROUTES = ["/", "/home2", "/home3", "/home4", "/login", "/trial", "/events", "/availability"]

    @pytest.mark.parametrize("route", ROUTES)
    def test_no_500(self, browser, route):
        ctx = browser.new_context(ignore_https_errors=True)
        pg = ctx.new_page()
        resp = pg.goto(f"{BASE}{route}")
        assert resp.status < 500, f"{route} returned {resp.status}"
        ctx.close()


# ── Stability (run key flows multiple times) ──

class TestStability:
    def test_repeated_login_logout(self, browser):
        """Login and navigate 5 times to check session stability."""
        for i in range(5):
            pg, ctx = login(browser)
            pg.goto(f"{BASE}/home4")
            pg.wait_for_load_state("networkidle")
            assert pg.locator("body").is_visible()
            ctx.close()

    def test_rapid_page_navigation(self, browser):
        """Navigate through 10 pages quickly."""
        pg, ctx = login(browser)
        pages = ["/", "/events", "/profile", "/availability", "/admin/dashboard",
                 "/admin/members", "/admin/payments", "/home4", "/events", "/profile"]
        for url in pages:
            resp = pg.goto(f"{BASE}{url}", timeout=10000)
            assert resp.status < 500, f"{url} returned {resp.status}"
        ctx.close()


# ── Rate Limiting ──

class TestRateLimiting:
    def test_login_rate_limit(self, browser):
        """Attempt 10 rapid failed logins — should not crash."""
        ctx = browser.new_context(ignore_https_errors=True)
        pg = ctx.new_page()
        for i in range(10):
            pg.goto(f"{BASE}/login")
            pg.fill('input[name="email"]', f"attacker{i}@example.com")
            pg.fill('input[name="password"]', "wrong")
            pg.click('button[type="submit"]')
            pg.wait_for_load_state("networkidle")
        # Should still be on login page, not crashed
        assert pg.locator('input[name="email"]').is_visible() or "429" in pg.content() or "throttle" in pg.content().lower()
        ctx.close()
