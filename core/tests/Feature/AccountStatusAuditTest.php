<?php

namespace Tests\Feature;

use App\Constants\Status;
use App\Http\Middleware\AdminAuditLog;
use App\Http\Middleware\CheckStatus;
use App\Http\Middleware\Module;
use App\Http\Middleware\RegistrationStep;
use App\Models\AdminAuditLog as AuditRow;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\CreatesMoneyFixtures;
use Tests\TestCase;

/**
 * F15: admin account ban/unban (`ManageGuardController@status`) writes a semantic
 * `account.status.changed` audit row WITHOUT changing status behavior. Status-only — never the
 * ban_reason text or any PII. Covers users/agents/merchants (one shared method). DB-backed on
 * `marketcapx_test`; DatabaseTransactions rolls back.
 */
class AccountStatusAuditTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesMoneyFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([
            \Laramin\Utility\Utility::class,
            \Laramin\Utility\GoToCore::class,
            CheckStatus::class,
            RegistrationStep::class,
            AdminAuditLog::class,
            Module::class,
        ]);
    }

    public function test_ban_user_changes_status_and_writes_audit(): void
    {
        $user = $this->makeUser(); // status = USER_ACTIVE

        $this->actingAs($this->makeAdmin(), 'admin')
            ->post(route('admin.users.status', $user->id), ['reason' => 'spamming'])
            ->assertRedirect();

        $user->refresh();
        $this->assertSame(Status::USER_BAN, (int) $user->status);
        $this->assertSame('spamming', $user->ban_reason);

        $rows = AuditRow::where('action', 'account.status.changed')->get();
        $this->assertCount(1, $rows);

        $row = $rows->first();
        $this->assertSame('users', $row->entity_type);
        $this->assertSame($user->id, (int) $row->entity_id);
        $this->assertSame('succeeded', $row->result);
        $this->assertSame(Status::USER_ACTIVE, (int) $row->before_values['status']);
        $this->assertSame(Status::USER_BAN, (int) $row->after_values['status']);
        $this->assertSame('users', $row->metadata['guard']);
        $this->assertTrue((bool) $row->metadata['banned']);
    }

    public function test_unban_user_changes_status_and_writes_audit(): void
    {
        $user = $this->makeUser();
        $user->status     = Status::USER_BAN;
        $user->ban_reason = 'old reason';
        $user->save();

        $this->actingAs($this->makeAdmin(), 'admin')
            ->post(route('admin.users.status', $user->id)) // no reason needed to unban
            ->assertRedirect();

        $user->refresh();
        $this->assertSame(Status::USER_ACTIVE, (int) $user->status);
        $this->assertNull($user->ban_reason);

        $row = AuditRow::where('action', 'account.status.changed')->latest('id')->first();
        $this->assertSame(Status::USER_BAN, (int) $row->before_values['status']);
        $this->assertSame(Status::USER_ACTIVE, (int) $row->after_values['status']);
        $this->assertFalse((bool) $row->metadata['banned']);
    }

    public function test_audit_excludes_ban_reason_text(): void
    {
        $user   = $this->makeUser();
        $secret = 'SECRET-REASON-PII-DO-NOT-LEAK';

        $this->actingAs($this->makeAdmin(), 'admin')
            ->post(route('admin.users.status', $user->id), ['reason' => $secret])
            ->assertRedirect();

        $row = AuditRow::where('action', 'account.status.changed')->latest('id')->first();
        $serialized = json_encode($row->toArray());

        $this->assertStringNotContainsString($secret, $serialized);
        $this->assertArrayNotHasKey('ban_reason', (array) $row->metadata);
        $this->assertArrayNotHasKey('reason', (array) $row->metadata);
    }

    public function test_ban_without_reason_fails_validation_and_writes_no_audit(): void
    {
        $user = $this->makeUser();

        $this->actingAs($this->makeAdmin(), 'admin')
            ->from(route('admin.users.detail', $user->id))
            ->post(route('admin.users.status', $user->id)) // missing reason
            ->assertRedirect();

        $this->assertSame(Status::USER_ACTIVE, (int) $user->fresh()->status); // unchanged
        $this->assertSame(0, AuditRow::where('action', 'account.status.changed')->count());
    }

    public function test_agent_and_merchant_status_audited_with_correct_entity_type(): void
    {
        $agent = $this->makeAgent();
        $this->actingAs($this->makeAdmin(), 'admin')
            ->post(route('admin.agents.status', $agent->id), ['reason' => 'x'])
            ->assertRedirect();
        $this->assertSame('agents', AuditRow::where('action', 'account.status.changed')->latest('id')->first()->entity_type);

        $merchant = $this->makeMerchant();
        $this->actingAs($this->makeAdmin(), 'admin')
            ->post(route('admin.merchants.status', $merchant->id), ['reason' => 'x'])
            ->assertRedirect();
        $this->assertSame('merchants', AuditRow::where('action', 'account.status.changed')->latest('id')->first()->entity_type);
    }

    public function test_missing_account_is_404_and_writes_no_audit(): void
    {
        $this->actingAs($this->makeAdmin(), 'admin')
            ->post(route('admin.users.status', 999999), ['reason' => 'x'])
            ->assertNotFound();

        $this->assertSame(0, AuditRow::where('action', 'account.status.changed')->count());
    }
}
