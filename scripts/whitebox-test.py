#!/usr/bin/env python3
"""Whitebox testing of DivingClub — local server-side checks."""
import subprocess, json, time

results = []
APP = "/home/collaed/laravel/divingclub"

def run(cmd):
    r = subprocess.run(cmd, shell=True, capture_output=True, text=True, timeout=30, cwd=APP)
    return r.stdout.strip()

def tinker(php):
    return run(f'php artisan tinker --execute "{php}"')

def test(name, passed, detail=""):
    status = "PASS" if passed else "FAIL"
    results.append({"test": name, "status": status, "detail": detail})
    print(f"  [{status}] {name}" + (f" — {detail}" if detail else ""))

print("=" * 60)
print("WHITEBOX TESTING — DivingClub (local)")
print(f"Time: {time.strftime('%Y-%m-%d %H:%M:%S')}")
print("=" * 60)

# ── 1. DATABASE ──
print("\n── 1. Database Health ──")
for model, label, minimum in [
    ("User", "users", 1), ("MemberDetail", "member details", 1),
    ("Event", "events", 1), ("Equipment", "equipment", 1),
    ("CertificationLevel", "cert levels", 100), ("DiveSite", "dive sites", 1),
    ("Role", "roles", 6), ("Article", "articles", 1),
]:
    out = tinker(f"echo App\\\\Models\\\\{model}::count();")
    ok = out.isdigit() and int(out) >= minimum
    test(f"{model} has data (>={minimum})", ok, f"{out}")

# ── 2. USERS & ROLES ──
print("\n── 2. Users & Roles ──")
out = tinker("\\$u = App\\\\Models\\\\User::where('primary_email','eddy.collart@gmail.com')->first(); echo \\$u ? \\$u->role->slug : 'NOT_FOUND';")
test("Eddy has bureau_master role", "bureau_master" in out, out)

out = tinker("echo App\\\\Models\\\\MemberDetail::where('bureau_member', true)->count();")
test("Bureau members flagged (>=5)", out.isdigit() and int(out) >= 5, f"{out}")

# ── 3. LICENSE ──
print("\n── 3. License System ──")
out = tinker("echo App\\\\Models\\\\ThemeSetting::get('license_key') ? 'SET' : 'MISSING';")
test("License key in DB", "SET" in out, out)

out = tinker("\\$s = new App\\\\Services\\\\LicenseService(); \\$v = \\$s->validate(); echo json_encode(\\$v);")
try:
    data = json.loads(out)
    test("License validates", data.get("valid") is True, f"max={data.get('max_members')}, exp={data.get('expires')}")
except:
    test("License validates", False, out[:200])

# ── 4. MAIL ALIAS ──
print("\n── 4. Mail Alias Resolution ──")
out = tinker("\\$r = App\\\\Services\\\\MailAliasService::resolve('bureau@clubcep.eu'); echo count(\\$r['emails']);")
test("bureau@ resolves", out.isdigit() and int(out) > 0, f"{out} recipients")

out = tinker("\\$r = App\\\\Services\\\\MailAliasService::resolve('all@clubcep.eu'); echo count(\\$r['emails']);")
test("all@ resolves", out.isdigit() and int(out) > 0, f"{out} recipients")

out = tinker("\\$r = App\\\\Services\\\\MailAliasService::resolve('instructors@clubcep.eu'); echo count(\\$r['emails']);")
test("instructors@ resolves", out.isdigit() and int(out) >= 0, f"{out} recipients")

out = tinker("\\$r = App\\\\Services\\\\MailAliasService::resolve('nonexistent@clubcep.eu'); echo \\$r === null ? 'null' : 'resolved';")
test("Unknown alias returns null", "null" in out, out)

# ── 5. MIGRATIONS ──
print("\n── 5. Migrations ──")
out = run("php artisan migrate:status 2>&1 | grep -c Ran")
test("All migrations ran (>=60)", out.isdigit() and int(out) >= 60, f"{out} migrations")

out = run("php artisan migrate:status 2>&1 | grep Pending | head -3")
test("No pending migrations", out == "", out[:100] if out else "none")

# ── 6. PGSQL COMPAT ──
print("\n── 6. PostgreSQL Compatibility ──")
out = run("grep -rn 'RAND()' app/ --include='*.php' | grep -v RANDOM | grep -v '//' | head -3")
test("No raw RAND()", out == "", out[:100] if out else "clean")

out = run("grep -rn 'DATE_SUB\\|DAYOFYEAR' app/ --include='*.php' | grep -v '//' | grep -v pgsql | head -3")
test("No raw MySQL date functions", out == "", out[:100] if out else "clean")

# ── 7. CODE QUALITY ──
print("\n── 7. Code Quality ──")
out = run("vendor/bin/pint --test --format agent 2>&1")
try:
    d = json.loads(out)
    test("Pint formatting passes", d.get("result") == "pass")
except:
    test("Pint formatting passes", False, out[:100])

out = run("php artisan test --compact 2>&1 | tail -3")
test("All tests pass", "passed" in out and "failed" not in out.lower(), out.strip())

# ── SUMMARY ──
print("\n" + "=" * 60)
passed = sum(1 for r in results if r["status"] == "PASS")
failed = sum(1 for r in results if r["status"] == "FAIL")
print(f"TOTAL: {len(results)} tests — {passed} passed, {failed} failed")
print("=" * 60)

with open("/tmp/whitebox-results.json", "w") as f:
    json.dump({"timestamp": time.strftime('%Y-%m-%d %H:%M:%S'),
               "results": results, "summary": {"total": len(results), "passed": passed, "failed": failed}}, f, indent=2)
