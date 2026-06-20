<?php

namespace Tests\Feature;

use App\Models\AdminAuditLog;
use App\Services\AdminAuditLogger;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Tests\Concerns\CreatesMoneyFixtures;
use Tests\TestCase;

/**
 * F10b coverage: the AdminAuditLog model + AdminAuditLogger service.
 *
 * DB-backed against `marketcapx_test` (admin_audit_logs applied by the F10a migration).
 * DatabaseTransactions rolls back every row — nothing persists, no real DB touched.
 * No admin behavior is exercised (the logger is called directly).
 */
class AdminAuditLoggerTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesMoneyFixtures;

    private AdminAuditLogger $logger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->logger = new AdminAuditLogger();
    }

    public function test_logs_a_permission_decision_row(): void
    {
        $admin = $this->makeAdmin();
        $before = AdminAuditLog::count();

        $this->logger->logPermissionDecision(
            $admin,
            'admin.deposits.approve',
            'would_deny',
            Request::create('/admin/deposit/approve/5', 'POST'),
            200
        );

        $this->assertSame($before + 1, AdminAuditLog::count());
        $row = AdminAuditLog::latest('id')->first();
        $this->assertSame('rbac.permission.would_deny', $row->action);
        $this->assertSame('would_deny', $row->result);
        $this->assertSame('admin.deposits.approve', $row->permission_slug);
        $this->assertSame($admin->id, $row->admin_id);
        $this->assertSame('POST', $row->method);
    }

    public function test_log_admin_request_derives_result_from_status(): void
    {
        $admin = $this->makeAdmin();

        $this->logger->logAdminRequest(
            Request::create('/admin/users/status/1', 'POST')->setUserResolver(fn () => $admin),
            new Response('', 302)
        );
        $ok = AdminAuditLog::latest('id')->first();
        $this->assertSame('admin.request.mutation', $ok->action);
        $this->assertSame('succeeded', $ok->result);
        $this->assertSame(302, $ok->http_status);

        $this->logger->logAdminRequest(
            Request::create('/admin/users/status/1', 'POST')->setUserResolver(fn () => $admin),
            new Response('', 500)
        );
        $fail = AdminAuditLog::latest('id')->first();
        $this->assertSame('admin.request.failed', $fail->action);
        $this->assertSame('failed', $fail->result);
    }

    public function test_sanitize_masks_sensitive_keys(): void
    {
        $clean = $this->logger->sanitizeMetadata([
            'password'    => 'hunter2',
            'api_key'     => 'sk_live_123',
            'note'        => 'all good',
            'nested'      => ['secret' => 'shh', 'fine' => 'ok', 'authorization' => 'Bearer x'],
        ]);

        $this->assertSame('[REDACTED]', $clean['password']);
        $this->assertSame('[REDACTED]', $clean['api_key']);
        $this->assertSame('all good', $clean['note']);
        $this->assertSame('[REDACTED]', $clean['nested']['secret']);
        $this->assertSame('[REDACTED]', $clean['nested']['authorization']);
        $this->assertSame('ok', $clean['nested']['fine']);
    }

    public function test_metadata_size_is_capped(): void
    {
        // Many short keys whose combined JSON exceeds the cap (each value stays under
        // the per-string truncation limit, so the overall-size guard is what fires).
        $huge = [];
        for ($i = 0; $i < 200; $i++) {
            $huge["key_{$i}"] = str_repeat('x', 50);
        }
        $clean = $this->logger->sanitizeMetadata($huge);

        $this->assertArrayHasKey('_truncated', $clean);
        $this->assertTrue($clean['_truncated']);
    }

    public function test_model_casts_json_columns_to_array(): void
    {
        $row = AdminAuditLog::create([
            'action'   => 'rbac.permission.allowed',
            'method'   => 'POST',
            'result'   => 'allowed',
            'metadata' => ['a' => 1, 'b' => ['c' => 2]],
        ]);

        $fresh = AdminAuditLog::find($row->id);
        $this->assertIsArray($fresh->metadata);
        $this->assertSame(2, $fresh->metadata['b']['c']);
        $this->assertNull($fresh->before_values);
    }

    public function test_logger_never_throws_and_writes_sanitized_metadata(): void
    {
        $admin = $this->makeAdmin();
        $this->logger->logMutation(
            'user.status.updated',
            $admin,
            'users',
            7,
            ['status' => 'active', 'password' => 'leak'],
            ['status' => 'banned'],
            ['token' => 'should-not-store', 'reason' => 'fraud']
        );

        $row = AdminAuditLog::latest('id')->first();
        $this->assertSame('user.status.updated', $row->action);
        $this->assertSame('[REDACTED]', $row->before_values['password']);
        $this->assertSame('active', $row->before_values['status']);
        $this->assertSame('[REDACTED]', $row->metadata['token']);
        $this->assertSame('fraud', $row->metadata['reason']);
    }
}
