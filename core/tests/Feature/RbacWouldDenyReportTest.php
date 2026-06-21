<?php

namespace Tests\Feature;

use App\Models\AdminAuditLog;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Tests\Concerns\CreatesMoneyFixtures;
use Tests\TestCase;

/**
 * F12 coverage: rbac:would-deny-report is a READ-ONLY summary of RBAC decision rows.
 * DB-backed on `marketcapx_test`; DatabaseTransactions rolls everything back. The command
 * must never write rows and never emit sensitive columns (ip/user_agent/metadata).
 */
class RbacWouldDenyReportTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesMoneyFixtures;

    /** Insert a single RBAC decision audit row directly. */
    private function row(array $overrides = []): AdminAuditLog
    {
        return AdminAuditLog::create(array_merge([
            'admin_id'        => 1,
            'admin_username'  => 'tester',
            'action'          => 'rbac.permission.would_deny',
            'permission_slug' => 'admin.deposits.approve',
            'route_name'      => 'admin.deposits.approve',
            'method'          => 'POST',
            'path'            => 'admin/deposit/approve/9',
            'result'          => 'would_deny',
        ], $overrides));
    }

    private function runJson(array $options = []): array
    {
        $code = Artisan::call('rbac:would-deny-report', array_merge(['--format' => 'json', '--days' => 30], $options));
        $this->assertSame(0, $code);
        $decoded = json_decode(Artisan::output(), true);
        $this->assertIsArray($decoded, 'JSON output must decode to an array.');

        return $decoded;
    }

    public function test_runs_and_groups_would_deny_by_permission(): void
    {
        $this->row(['permission_slug' => 'admin.deposits.approve']);
        $this->row(['permission_slug' => 'admin.deposits.approve']);
        $this->row(['permission_slug' => 'admin.withdrawals.approve']);
        $this->row(['result' => 'allowed', 'action' => 'rbac.permission.allowed']); // excluded by default

        $code = Artisan::call('rbac:would-deny-report', ['--days' => 30, '--group-by' => 'permission']);
        $out = Artisan::output();

        $this->assertSame(0, $code);
        $this->assertStringContainsString('admin.deposits.approve', $out);
        $this->assertStringContainsString('admin.withdrawals.approve', $out);
    }

    public function test_default_counts_only_would_deny(): void
    {
        $this->row(); // would_deny
        $this->row(); // would_deny
        $this->row(['result' => 'allowed', 'action' => 'rbac.permission.allowed']);
        $this->row(['result' => 'denied', 'action' => 'rbac.permission.denied']);

        $data = $this->runJson();

        $this->assertSame('would_deny', $data['result']);
        $this->assertSame(2, $data['totals']['matching']);
    }

    public function test_result_override_reports_allowed(): void
    {
        $this->row(); // would_deny
        $this->row(['result' => 'allowed', 'action' => 'rbac.permission.allowed']);
        $this->row(['result' => 'allowed', 'action' => 'rbac.permission.allowed']);

        $data = $this->runJson(['--result' => 'allowed']);

        $this->assertSame(2, $data['totals']['matching']);
    }

    public function test_days_filter_excludes_old_rows(): void
    {
        $this->row(); // created_at = now
        $this->row(['created_at' => '2000-01-01 00:00:00']); // far outside the window

        $data = $this->runJson(['--days' => 7]);

        $this->assertSame(1, $data['totals']['matching']);
    }

    public function test_admin_and_permission_filters_narrow(): void
    {
        $this->row(['admin_id' => 11, 'permission_slug' => 'admin.deposits.approve']);
        $this->row(['admin_id' => 22, 'permission_slug' => 'admin.withdrawals.approve']);

        $this->assertSame(1, $this->runJson(['--admin' => 11])['totals']['matching']);
        $this->assertSame(1, $this->runJson(['--permission' => 'admin.withdrawals.approve'])['totals']['matching']);
    }

    public function test_json_output_excludes_sensitive_columns(): void
    {
        $this->row([
            'ip'         => '203.0.113.77',
            'user_agent' => 'SENSITIVE-UA-STRING',
            'metadata'   => ['secret_key' => 'sk_live_LEAK'],
        ]);

        $code = Artisan::call('rbac:would-deny-report', ['--format' => 'json', '--days' => 30]);
        $out = Artisan::output();

        $this->assertSame(0, $code);
        $this->assertIsArray(json_decode($out, true));
        $this->assertStringNotContainsString('203.0.113.77', $out);
        $this->assertStringNotContainsString('SENSITIVE-UA-STRING', $out);
        $this->assertStringNotContainsString('sk_live_LEAK', $out);
    }

    public function test_legacy_null_role_admin_warning_is_surfaced(): void
    {
        $this->makeAdmin(); // created with null role_id => legacy full access
        $this->row();

        $data = $this->runJson();

        $this->assertGreaterThanOrEqual(1, $data['warnings']['legacy_null_role_admins']);
    }

    public function test_unmapped_permission_slug_is_flagged(): void
    {
        $this->row(['permission_slug' => 'admin.bogus.unmapped', 'route_name' => 'admin.bogus.unmapped']);

        $data = $this->runJson();

        $this->assertContains('admin.bogus.unmapped', $data['warnings']['unmapped_permission_slugs']);
    }

    public function test_command_is_read_only(): void
    {
        $this->row();
        $before = AdminAuditLog::count();

        Artisan::call('rbac:would-deny-report', ['--days' => 30]);

        $this->assertSame($before, AdminAuditLog::count(), 'Report command must not create or delete rows.');
    }

    public function test_invalid_group_by_fails(): void
    {
        $code = Artisan::call('rbac:would-deny-report', ['--group-by' => 'bogus']);

        $this->assertSame(1, $code);
    }

    public function test_invalid_result_fails(): void
    {
        $code = Artisan::call('rbac:would-deny-report', ['--result' => 'succeeded']);

        $this->assertSame(1, $code);
    }
}
