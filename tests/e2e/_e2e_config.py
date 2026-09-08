"""Shared configuration and helpers for the Playwright E2E suite.

Connection details come from the environment so the suite can run against
any deployment without editing source:

    E2E_BASE   base URL            (default https://test.clubcep.eu)
    E2E_USER   admin login id      (default eddy.collart@gmail.com)
    E2E_PASS   admin password      (no default — export before running)

Example:
    E2E_PASS='…' python3 -m pytest tests/e2e/ -v
"""
import os
import time

BASE = os.environ.get("E2E_BASE", "https://test.clubcep.eu").rstrip("/")
USER = os.environ.get("E2E_USER", "eddy.collart@gmail.com")
PASS = os.environ.get("E2E_PASS", "")

# Backoff schedule (seconds) for Laravel's login throttle. Its default
# limiter releases after ~60s, so the last step simply waits it out.
_LOGIN_BACKOFF = (2, 5, 15, 30, 60)


def _looks_throttled(pg) -> bool:
    try:
        body = pg.inner_text("body").lower()
    except Exception:
        return False
    return any(s in body for s in ("too many", "throttle", "secondes", "seconds"))


def submit_login(pg, email=None, pw=None) -> bool:
    """Fill and submit the /login form, retrying past the rate limiter.

    Returns True once the session leaves /login. Returns False on a genuine
    credential rejection (no point waiting out the limiter) or if every
    backoff step is exhausted while still throttled.
    """
    email = email or USER
    pw = pw if pw is not None else PASS
    for wait in (0, *_LOGIN_BACKOFF):
        if wait:
            time.sleep(wait)
        if "/login" not in pg.url:
            pg.goto(f"{BASE}/login")
        pg.wait_for_load_state("networkidle")
        pg.fill('input[name="email"]', email)
        pg.fill('input[name="password"]', pw)
        pg.click('button[type="submit"]')
        pg.wait_for_load_state("networkidle")
        if "/login" not in pg.url:
            return True
        if not _looks_throttled(pg):
            return False
    return "/login" not in pg.url
