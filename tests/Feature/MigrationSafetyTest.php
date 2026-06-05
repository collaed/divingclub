<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('p0')]
class MigrationSafetyTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function all_migrations_run_without_error(): void
    {
        // RefreshDatabase already ran them — verify key tables exist
        $critical = [
            'users', 'member_details', 'member_licences', 'documents',
            'events', 'event_registrations', 'payment_expected',
            'equipment', 'equipment_loans', 'federations',
            'medical_compliance_rules', 'membership_fees',
            'membership_fee_components', 'gdpr_consents',
            'audit_logs', 'votes', 'vote_tokens', 'vote_ballots',
            'articles', 'article_translations', 'newsletters',
        ];

        foreach ($critical as $table) {
            $this->assertTrue(Schema::hasTable($table), "Table {$table} missing after migration");
        }
    }

    #[Test]
    public function users_table_has_required_columns(): void
    {
        $required = ['id', 'username', 'primary_email', 'password', 'role_id', 'status_id', 'email_verified_at'];

        foreach ($required as $col) {
            $this->assertTrue(Schema::hasColumn('users', $col), "Column users.{$col} missing");
        }
    }

    #[Test]
    public function member_details_has_privacy_fields(): void
    {
        $fields = ['first_name', 'last_name', 'date_of_birth', 'phone_mobile', 'emergency_contact_name', 'emergency_contact_phone', 'avatar_path'];

        foreach ($fields as $col) {
            $this->assertTrue(Schema::hasColumn('member_details', $col), "Column member_details.{$col} missing");
        }
    }

    #[Test]
    public function member_licences_has_federation_fields(): void
    {
        $fields = ['user_id', 'federation_id', 'licence_number', 'licence_request_date', 'licence_request_pending', 'medical_cert_expiry', 'season'];

        foreach ($fields as $col) {
            $this->assertTrue(Schema::hasColumn('member_licences', $col), "Column member_licences.{$col} missing");
        }
    }

    #[Test]
    public function documents_table_supports_soft_deletes(): void
    {
        $this->assertTrue(Schema::hasColumn('documents', 'deleted_at'), 'Documents must support soft deletes for GDPR');
    }

    #[Test]
    public function payment_expected_has_financial_columns(): void
    {
        $fields = ['user_id', 'event_id', 'amount_due', 'amount_paid', 'status', 'communication'];

        foreach ($fields as $col) {
            $this->assertTrue(Schema::hasColumn('payment_expected', $col), "Column payment_expected.{$col} missing");
        }
    }

    #[Test]
    public function medical_compliance_rules_exist(): void
    {
        $this->assertTrue(Schema::hasTable('medical_compliance_rules'));
        $fields = ['federation_id', 'age_bracket_low', 'age_bracket_high', 'validity_months', 'cert_type'];

        foreach ($fields as $col) {
            $this->assertTrue(Schema::hasColumn('medical_compliance_rules', $col), "Column medical_compliance_rules.{$col} missing");
        }
    }

    #[Test]
    public function season_patterns_has_template_fields(): void
    {
        $fields = ['season_id', 'day_of_week', 'start_time', 'title', 'event_type', 'description', 'estimated_cost'];

        foreach ($fields as $col) {
            $this->assertTrue(Schema::hasColumn('season_patterns', $col), "Column season_patterns.{$col} missing");
        }
    }
}
