# Testing Requirements — DivingClub-Manager

## Test Stack

| Layer | Framework | Scope | Count |
|-------|-----------|-------|-------|
| Unit | PHPUnit | Models, services, helpers | 12 tests |
| Feature | PHPUnit | HTTP routes, auth, workflows | 2 test files |
| E2E | Playwright (pytest) | Full browser flows | 86 tests |
| Data Integrity | PHPUnit | Import/export/backup safety | NEW |
| Migration | PHPUnit | Schema change data survival | NEW |

## Priority Classification

| Priority | Rule | Tests |
|----------|------|-------|
| **P0** | Must pass before every deploy | Auth, registration, medical gate, payment, GDPR, data integrity |
| **P1** | Must pass before release | All admin pages load, equipment, newsletters, votes, calendar |
| **P2** | Run weekly / before major release | E2E adversarial, stability, rate limiting |

## User Story → Test Traceability

### Members (US 6-22)

| US | Story | PHPUnit | E2E | Gap |
|----|-------|---------|-----|-----|
| 6 | Login & manage profile | CriticalPathsTest | test_login_* | ✅ |
| 7 | Register for events | test_user_can_register_for_event | — | ✅ |
| 8 | View dive sites with maps | — | test_no_500[/admin/dive-sites] | ✅ |
| 9 | Read translated articles | ArticleModelTest | — | ✅ |
| 10 | Switch languages | — | test_language_switch | ✅ |
| 11 | Classifieds | — | — | ❌ needs test |
| 12 | Comments | — | — | ❌ needs test |
| 13 | Votes | — | test_no_500[/admin/votes] | partial |
| 14 | Instructor calendar | — | test_calendar_* | ✅ |
| 15 | GDPR export/erasure | test_gdpr_* | — | ✅ |
| 16 | PWA install | — | — | manual only |
| 17 | SEPA QR codes | — | — | ❌ needs test |
| 18 | Document library | — | test_documents_page | ✅ |
| 19 | Dive group planner | DiveGroupRuleTest | — | ✅ |
| 20 | Equipment loan | — | test_no_500[/admin/equipment] | partial |
| 21 | Buddy finder | — | — | ❌ needs test |
| 22 | Push notifications | — | — | manual only |

### Bureau (US 26-46)

| US | Story | PHPUnit | E2E | Gap |
|----|-------|---------|-----|-----|
| 26 | Manage members | — | test_admin_members | ✅ |
| 27 | Create events | test_user_can_register_for_event | — | partial |
| 28 | Send emails | — | test_email_stats_loads | ✅ |
| 29 | Bank reconciliation | BankReconciliationServiceTest | — | ✅ |
| 30 | Equipment inventory | — | test_no_500[/admin/equipment] | ✅ |
| 31 | Votes | — | test_no_500[/admin/votes] | ✅ |
| 32 | Articles | ArticleModelTest | test_no_500[/admin/articles] | ✅ |
| 33 | Document library | DocumentModelTest | test_no_500[/admin/library] | ✅ |
| 34 | Theme customization | ThemeServiceTest | — | ✅ |
| 35 | Club identity | — | test_no_500[/admin/settings] | ✅ |
| 36 | Trial requests | — | — | ❌ needs test |
| 37 | Impersonation | — | — | ❌ needs test |
| 38 | Dashboard stats | — | test_admin_dashboard | ✅ |
| 39 | License key | LicenseServiceTest | — | ✅ |
| 40 | Admin guide | — | test_admin_guide | ✅ |
| 41 | Backups | BackupServiceTest | test_admin_backups | ✅ |
| 42 | Worklist | — | test_admin_dashboard | ✅ |
| 43 | Homepage widgets | — | test_homepage_loads | ✅ |
| 44 | Dive sites | — | test_no_500[/admin/dive-sites] | ✅ |
| 45 | Newsletters | — | test_admin_newsletters | ✅ |
| 46 | Partnerships | — | test_partnerships_page | ✅ |

### System (US 47-53)

| US | Story | PHPUnit | E2E | Gap |
|----|-------|---------|-----|-----|
| 47 | Medical reminders | MedicalComplianceService | — | ✅ |
| 48 | Medical gate | test_medical_gate_* | — | ✅ |
| 49 | Vote auto-open/close | — | — | ❌ needs test |
| 50 | Weekly backup | BackupServiceTest | — | ✅ |
| 51 | License verification | LicenseServiceTest | — | ✅ |
| 52 | Article translations | ArticleModelTest | — | ✅ |
| 53 | Multi-DB support | — | — | tested by CI |

### Security

| What | Test | Priority |
|------|------|----------|
| XSS in search | test_xss_in_search | P0 |
| SQL injection | test_sql_injection_in_search | P0 |
| Auth redirect | test_unauthenticated_admin_redirect | P0 |
| CSRF tokens | test_csrf_token_present_on_forms | P0 |
| Document auth | test_direct_document_url_requires_auth | P0 |
| API auth | test_api_federation_requires_auth | P0 |
| Rate limiting | test_login_rate_limit | P1 |
| Invalid params | test_invalid_month/date_param_no_500 | P1 |

### Data Integrity (NEW)

| What | Test | Priority |
|------|------|----------|
| GDPR export completeness | DataIntegrityTest | P0 |
| GDPR erasure anonymizes | DataIntegrityTest | P0 |
| Backup contains all tables | DataIntegrityTest | P0 |
| Cotisation import preserves medical | DataIntegrityTest | P0 |
| Member delete cascades correctly | DataIntegrityTest | P0 |
| Fee calculation deterministic | DataIntegrityTest | P1 |

## Gaps Summary

8 user stories have no automated test coverage:
- US 11 (classifieds), US 12 (comments), US 17 (SEPA QR), US 21 (buddy finder)
- US 36 (trial requests), US 37 (impersonation), US 49 (vote auto-open/close)
- US 16/22 (PWA/push — manual only, acceptable)
