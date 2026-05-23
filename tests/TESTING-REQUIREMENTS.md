# Testing Strategy — DivingClub-Manager

## Philosophy

We test to **prevent regressions**, not to achieve 100% coverage. Every bug fix gets a test. Every new feature gets a test. We prioritize testing workflows (feature tests) over isolated units, because most bugs happen at integration boundaries.

---

## Test Stack

| Layer | Framework | Scope | Files | Tests |
|-------|-----------|-------|-------|-------|
| **Unit** | PHPUnit | Models, services, helpers, pure logic | 15 | ~80 |
| **Feature** | PHPUnit | HTTP requests, auth, workflows, validation | 21 | ~153 |
| **E2E** | Pytest + requests | Full HTTP journeys, multi-step flows | 7 | ~86 |

**Total: 233 PHPUnit tests, 532 assertions** (as of May 2026)

---

## Test Pyramid

```
        ╱ E2E (7 files) ╲          — Slow, brittle, but catches real bugs
       ╱                   ╲
      ╱  Feature (21 files) ╲       — Our primary layer: fast, realistic
     ╱                        ╲
    ╱    Unit (15 files)        ╲    — Pure logic, no DB, no HTTP
   ╱──────────────────────────────╲
```

**Feature tests are our bread and butter.** They hit real routes, use real middleware, touch the database, and verify the full request/response cycle. A feature test catches 10× more bugs than a unit test for the same effort.

---

## What We Test

### Unit Tests (`tests/Unit/`)

Pure logic with no database or HTTP:

| File | What it tests |
|------|---------------|
| `FeeCalculationServiceTest` | Fee formula: base × status × age discount + optionals |
| `MedicalComplianceServiceTest` | Expiry rules per federation, age brackets |
| `BankReconciliationServiceTest` | Fuzzy matching algorithm for payments |
| `DiveGroupRuleTest` | 14 buddy pairing rules (depth, cert level) |
| `HomogeneityAssessmentServiceTest` | Group homogeneity scoring |
| `LicenseServiceTest` | RSA signature verification |
| `BackupServiceTest` | Backup creation, manifest, retention |
| `ThemeServiceTest` | Theme preset application |
| `CalendarFeedHelperTest` | iCal feed generation |
| `UddfServiceTest` | UDDF dive log export |
| `ArticleModelTest` | Article type logic, translation |
| `EventModelTest` | Event date logic, capacity |
| `UserModelTest` | Role checks, status helpers |
| `DocumentModelTest` | File path, category logic |

### Feature Tests (`tests/Feature/`)

Full HTTP request/response with database:

| File | What it tests |
|------|---------------|
| `CriticalPathsTest` | All pages load without 500 (smoke test) |
| `EventRegistrationWorkflowTest` | Register, waiting list, auto-promote, cancel |
| `EquipmentLoanTest` | Borrow, return, overdue |
| `VoteWorkflowTest` | Create vote, cast ballot, results |
| `DataIntegrityTest` | GDPR export/erasure, cascade deletes, fee determinism |
| `MigrationSafetyTest` | Schema has required columns |
| `FederationVisibilityTest` | Multi-federation cert display |
| `ImpersonationTest` | Bureau can impersonate, member cannot |
| `ClassifiedControllerTest` | CRUD, auto-expiry, authorization |
| `ClassifiedsTest` | Listing, filtering |
| `TrialRequestTest` | Public trial form, honeypot, admin management |
| `HomeControllerTest` | Public pages, landing pages |
| `InstructorAvailabilityTest` | Toggle availability, auto-register |
| `AdminMemberTest` | Member CRUD, role checks |
| `DiveSiteTest` | Dive site CRUD |
| `DocumentUploadTest` | Upload, download, authorization |
| `EmailSendTest` | Email composition, target groups |
| `NewsletterWorkflowTest` | Draft, approve, send workflow |
| `PhotoGalleryTest` | Upload photos, gallery display |
| `ProfileEditTest` | Profile update, language preference |

### E2E Tests (`tests/e2e/`)

Full HTTP journeys testing multi-step flows across pages. Run against a live server instance.

---

## Running Tests

```bash
# All tests (recommended before push)
php artisan test --compact

# Single file
php artisan test --compact tests/Feature/EventRegistrationWorkflowTest.php

# Single method
php artisan test --compact --filter=test_member_can_upload_document

# Unit only
php artisan test --compact tests/Unit/

# Feature only
php artisan test --compact tests/Feature/
```

### Test Database

Tests use a separate database (`divingclub_test`) configured in `phpunit.xml`. The `RefreshDatabase` trait migrates fresh for each test class.

```bash
# Create test database (one-time)
mysql -e "CREATE DATABASE divingclub_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

---

## Writing Tests

### Rules

1. **Every change gets a test** — no exceptions
2. **Feature tests over unit tests** — unless testing pure math/logic
3. **Use factories** — never `Model::create()` with hardcoded data in tests
4. **Test names describe behavior** — `test_guest_cannot_upload_document`
5. **Test happy path + failure path** — at minimum
6. **Tests must work on MySQL AND PostgreSQL** — no DB-specific syntax

### Template

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MyFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_can_do_the_thing(): void
    {
        $user = $this->createUser();

        $response = $this->actingAs($user)->get('/the-route');

        $response->assertOk();
        $response->assertSee('Expected content');
    }

    public function test_guest_cannot_do_the_thing(): void
    {
        $this->get('/the-route')->assertRedirect('/login');
    }

    private function createUser(): User
    {
        // Use factories or minimal setup
        // Check existing tests for the pattern
    }
}
```

### Creating a test

```bash
php artisan make:test MyFeatureTest --phpunit --no-interaction
```

---

## Priority Classification

| Priority | When to run | What |
|----------|-------------|------|
| **P0** | Every push, CI blocks on failure | Auth, data integrity, GDPR, payments, medical gate |
| **P1** | Every push, CI blocks on failure | All feature tests, admin pages, workflows |
| **P2** | Weekly / before major release | E2E browser tests, performance, edge cases |

---

## CI Pipeline

GitHub Actions (`.github/workflows/ci.yml`) runs on every push:

1. **Lint** — `vendor/bin/pint --test` (code style)
2. **Test** — `php artisan test --compact` (all 233 PHPUnit tests)
3. **Build** — `npm run build` (frontend assets compile)

All 3 must pass before deploying to staging.

---

## Cross-Database Compatibility

Tests must pass on both MySQL (local dev) and PostgreSQL (staging/CI). Rules:

- No `SHOW INDEX`, `SHOW TABLES` — use `Schema` facade
- No MySQL-specific functions — use Eloquent/query builder
- Use `Schema::hasTable()` for conditional logic
- Test with `DB_CONNECTION=pgsql` periodically

---

## Coverage Gaps (Known)

Features with manual-only testing (acceptable):

- PWA install/offline (requires device)
- Push notifications (requires service worker)
- SEPA QR code scanning (requires camera)
- OAuth login flows (requires external providers)

---

## Test Data

### Factories

Every model has a factory in `database/factories/`. Use them:

```php
$user = User::factory()->create();
$event = Event::factory()->create(['max_participants' => 5]);
```

### Sample Data

For manual testing: `php artisan db:seed --class=SampleDataSeeder` creates 20 realistic members with various roles, statuses, and certifications.

### Test Accounts

| Email | Password | Role |
|-------|----------|------|
| admin@divingclub.eu | password | Bureau Master |
| diver@example.com | password | Member |

---

## Adding Tests for Bug Fixes

When fixing a bug:

1. Write a test that **reproduces the bug** (it should fail)
2. Fix the bug
3. Verify the test passes
4. Commit both together

This ensures the bug never returns.
