#!/usr/bin/env python3
"""Blackbox testing of DivingClub staging instance."""
import requests
import json
import time
import re
from bs4 import BeautifulSoup
from urllib.parse import urljoin

BASE = "http://204.168.168.60"
AUTH = ("cep", "cep2026")
USER_EMAIL = "eddy.collart@gmail.com"
USER_PASS = "cep2026!"
results = []

def test(name, passed, detail=""):
    status = "PASS" if passed else "FAIL"
    results.append({"test": name, "status": status, "detail": detail})
    print(f"  [{status}] {name}" + (f" — {detail}" if detail else ""))

def get(path, session=None, **kwargs):
    s = session or requests
    return s.get(urljoin(BASE, path), auth=AUTH if not session else None, timeout=15, **kwargs)

def extract_csrf(html):
    soup = BeautifulSoup(html, "html.parser")
    token = soup.find("input", {"name": "_token"})
    return token["value"] if token else None

print("=" * 60)
print("BLACKBOX TESTING — DivingClub Staging")
print(f"Target: {BASE}")
print(f"Time: {time.strftime('%Y-%m-%d %H:%M:%S')}")
print("=" * 60)

# ── 1. CONNECTIVITY & BASIC AUTH ──
print("\n── 1. Connectivity & Basic Auth ──")
r = requests.get(BASE, timeout=15)
test("Unauthenticated returns 401", r.status_code == 401, f"got {r.status_code}")

r = get("/")
test("Basic auth returns 200", r.status_code == 200, f"got {r.status_code}")

test("Response contains HTML", "<!DOCTYPE html" in r.text.lower() or "<html" in r.text.lower())
test("Generator meta tag present", "DivingClub-Manager" in r.text)

# ── 2. PUBLIC PAGES ──
print("\n── 2. Public Pages ──")
public_pages = ["/", "/login", "/register", "/locale/en", "/locale/fr"]
for page in public_pages:
    r = get(page, allow_redirects=True)
    ok = r.status_code == 200
    test(f"GET {page}", ok, f"status={r.status_code}")

# ── 3. LOGIN FLOW ──
print("\n── 3. Login Flow ──")
s = requests.Session()
s.auth = AUTH

r = s.get(urljoin(BASE, "/login"), timeout=15)
csrf = extract_csrf(r.text)
test("Login page has CSRF token", csrf is not None)

soup = BeautifulSoup(r.text, "html.parser")
oauth_buttons = soup.find_all("a", href=re.compile(r"/auth/\w+/redirect"))
providers = [a.get_text(strip=True) for a in oauth_buttons]
test("OAuth buttons visible", len(oauth_buttons) > 0, f"providers: {providers}")

r = s.post(urljoin(BASE, "/login"), data={
    "_token": csrf, "email": USER_EMAIL, "password": USER_PASS
}, allow_redirects=True, timeout=15)
test("Login succeeds (200 after redirect)", r.status_code == 200)
test("Redirected to authenticated page", "/login" not in r.url, f"url={r.url}")

# ── 4. AUTHENTICATED PAGES ──
print("\n── 4. Authenticated Pages ──")
auth_pages = [
    ("/profile", "Profile"),
    ("/events", "Events"),
    ("/members", "Members"),
    ("/equipment", "Equipment"),
    ("/articles", "Articles"),
    ("/dive-sites", "Dive Sites"),
]
for path, label in auth_pages:
    r = s.get(urljoin(BASE, path), timeout=15, allow_redirects=True)
    ok = r.status_code == 200
    test(f"GET {path} ({label})", ok, f"status={r.status_code}")

# ── 5. ADMIN PAGES ──
print("\n── 5. Admin Pages ──")
admin_pages = [
    ("/admin/dashboard", "Dashboard"),
    ("/admin/members", "Member Management"),
    ("/admin/settings", "Settings"),
    ("/admin/email-log", "Email Log"),
    ("/admin/equipment", "Equipment Admin"),
]
for path, label in admin_pages:
    r = s.get(urljoin(BASE, path), timeout=15, allow_redirects=True)
    ok = r.status_code == 200
    test(f"GET {path} ({label})", ok, f"status={r.status_code}")

# ── 6. STAGING MAIL VIEWER ──
print("\n── 6. Staging Mail Viewer ──")
r = s.get(urljoin(BASE, "/staging-mail"), timeout=15, allow_redirects=True)
test("Staging mail viewer accessible", r.status_code == 200, f"status={r.status_code}")
if r.status_code == 200:
    test("Shows captured emails", "staging_captured" in r.text or "bureau" in r.text.lower() or "email" in r.text.lower())

# ── 7. API / JSON ENDPOINTS ──
print("\n── 7. API / AJAX Endpoints ──")
r = s.get(urljoin(BASE, "/events"), timeout=15, headers={"Accept": "text/html"})
test("Events page loads", r.status_code == 200)

# ── 8. LOCALE SWITCHING ──
print("\n── 8. Locale Switching ──")
for locale in ["fr", "de", "en"]:
    r = s.get(urljoin(BASE, f"/locale/{locale}"), timeout=15, allow_redirects=True)
    test(f"Switch to {locale}", r.status_code == 200, f"status={r.status_code}")

# ── 9. SECURITY CHECKS ──
print("\n── 9. Security Checks ──")
r = get("/admin/dashboard")  # unauthenticated session
test("Admin requires auth (redirects to login)", r.status_code == 200 and "login" in r.url.lower() or r.status_code in [302, 401, 403])

r = get("/.env")
test(".env not exposed", r.status_code != 200 or "APP_KEY" not in r.text, f"status={r.status_code}")

r = get("/storage/logs/laravel.log")
test("Logs not exposed", r.status_code != 200, f"status={r.status_code}")

r = get("/telescope")
test("Telescope not exposed", r.status_code != 200, f"status={r.status_code}")

# ── 10. RESPONSE HEADERS ──
print("\n── 10. Response Headers ──")
r = get("/")
headers = r.headers
test("X-Frame-Options or CSP present", "x-frame-options" in {k.lower() for k in headers} or "content-security-policy" in {k.lower() for k in headers})
test("Content-Type is text/html", "text/html" in headers.get("Content-Type", ""))

# ── 11. ERROR HANDLING ──
print("\n── 11. Error Handling ──")
r = get("/nonexistent-page-xyz-12345")
test("404 for unknown route", r.status_code == 404, f"got {r.status_code}")
test("404 page doesn't leak stack trace", "vendor/" not in r.text and "Stack trace" not in r.text)

# ── 12. LOGOUT ──
print("\n── 12. Logout ──")
r = s.get(urljoin(BASE, "/login"), timeout=15)
csrf = extract_csrf(r.text)
if csrf:
    r = s.post(urljoin(BASE, "/logout"), data={"_token": csrf}, allow_redirects=True, timeout=15)
    test("Logout works", r.status_code == 200)
else:
    test("Logout works", False, "could not get CSRF for logout")

# ── SUMMARY ──
print("\n" + "=" * 60)
passed = sum(1 for r in results if r["status"] == "PASS")
failed = sum(1 for r in results if r["status"] == "FAIL")
print(f"TOTAL: {len(results)} tests — {passed} passed, {failed} failed")
print("=" * 60)

# Save JSON for report
with open("/tmp/blackbox-results.json", "w") as f:
    json.dump({"target": BASE, "timestamp": time.strftime('%Y-%m-%d %H:%M:%S'), "results": results, "summary": {"total": len(results), "passed": passed, "failed": failed}}, f, indent=2)
