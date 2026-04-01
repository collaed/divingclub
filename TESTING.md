# DivingClub-Manager — Testing Guide v2

## Running Tests

```bash
php artisan test --compact                        # All 138 tests
php artisan test --filter=CriticalPath            # Critical path tests (21)
php artisan test --filter=FederationVisibility     # Federation tests (4)
php artisan test --filter=testName                 # Single test by name
```

## Test Structure

- **138 tests**, 321 assertions
- **Feature tests** (21): registration, login, event registration, medical gate, GDPR, admin access
- **Unit tests** (117): models, services, helpers

## Writing Tests with Spatie Permissions

Tests using `RefreshDatabase` must create Spatie roles in `setUp()`:

```php
use Spatie\Permission\Models\Role as SpatieRole;

protected function setUp(): void
{
    parent::setUp();
    foreach (['public', 'member', 'instructor', 'bureau_finance', 'bureau_technical', 'bureau_master'] as $r) {
        SpatieRole::findOrCreate($r, 'web');
    }
}
```

When creating test users, assign both the legacy `role_id` and the Spatie role:

```php
$user = User::factory()->create(['role_id' => $legacyRole->id, 'status_id' => $status->id]);
$user->assignRole('bureau_master');
```

## UserFactory

The factory uses `username` and `primary_email` (not the Laravel defaults):

```php
'username' => fake()->userName(),
'primary_email' => fake()->unique()->safeEmail(),
```

## Staging Safety

`STAGING_MODE=true` activates:
- `Mail::alwaysTo('admin@gmail.com')` — all outbound redirected to one address
- `STAGING_USE_SMTP=true` — enables Resend delivery (otherwise defaults to `log` driver)
- Basic auth on all pages (`STAGING_USER` / `STAGING_PASS`)

**Real member emails in the DB are never contacted in staging.**

## Testing Inbound Mail

```bash
# Local delivery test
echo "Test body" | mail -s "Test (recipients: bureau)" clubcep+bureau@test.clubcep.eu

# Wait for PollInboundMail job (~1 minute) or run manually:
php artisan tinker --execute="(new App\Jobs\PollInboundMail)->handle();"

# Check result
php artisan tinker --execute="echo App\Models\EmailLog::where('direction','inbound')->latest()->first()?->status;"

# Direct artisan command test (bypasses Maildir)
echo "Test" | php artisan mail:inbound --to=bureau@clubcep.eu --from=admin@test.com --subject="Test"
```

## Key Test Files

| File | Tests | What it covers |
|------|-------|---------------|
| `CriticalPathsTest.php` | 17 | Registration, login, events, medical gate, GDPR, admin access |
| `FederationVisibilityTest.php` | 4 | Federation active/recognized/invisible scopes |
| `UserModelTest.php` | 15 | Name, email, dive profile, minor detection, photo ban |
| `EventModelTest.php` | 13 | Type colors, registration open/closed, maps URL |
| `ArticleModelTest.php` | 19 | Types, expiry, rendered body, edit permissions |
| `HomogeneityAssessmentServiceTest.php` | 17 | Dive group homogeneity scoring |
| `BackupServiceTest.php` | 7 | Human size formatting, DB dump filename |
| `UddfServiceTest.php` | 8 | UDDF dive log parsing |
| `BankReconciliationServiceTest.php` | 8 | Date parsing, IBAN normalization |
| `LicenseServiceTest.php` | 7 | License verification, free tier |
| `DiveGroupRuleTest.php` | 9 | Rule matching, leader qualification |
| `CalendarFeedHelperTest.php` | 9 | iCal date formatting, escaping |
| `FeeCalculationServiceTest.php` | 3 | Communication string building |
| `ThemeServiceTest.php` | 5 | Presets, color validation |
| `DocumentModelTest.php` | 6 | Expiry, days until expiry |
