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
 * F16: admin KYC approve/reject (`ManageGuardController@kycApprove|kycReject`) writes a semantic
 * `account.kyc.approved` / `account.kyc.rejected` audit row WITHOUT changing KYC behavior. Status-only
 * — never the rejection reason text or any KYC document/PII. Covers users/agents/merchants (one shared
 * controller). DB-backed on `marketcapx_test`; DatabaseTransactions rolls back.
 */
class KycDecisionAuditTest extends TestCase
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

    public function test_kyc_approve_sets_verified_and_writes_audit(): void
    {
        $user = $this->makeUser();
        $user->kv = Status::KYC_PENDING;
        $user->save();

        $this->actingAs($this->makeAdmin(), 'admin')
            ->post(route('admin.users.kyc.approve', $user->id))
            ->assertRedirect();

        $this->assertSame(Status::KYC_VERIFIED, (int) $user->fresh()->kv);

        $rows = AuditRow::where('action', 'account.kyc.approved')->get();
        $this->assertCount(1, $rows);

        $row = $rows->first();
        $this->assertSame('users', $row->entity_type);
        $this->assertSame($user->id, (int) $row->entity_id);
        $this->assertSame('succeeded', $row->result);
        $this->assertSame(Status::KYC_PENDING, (int) $row->before_values['kv']);
        $this->assertSame(Status::KYC_VERIFIED, (int) $row->after_values['kv']);
        $this->assertSame('users', $row->metadata['guard']);
        $this->assertSame('approved', $row->metadata['decision']);
    }

    public function test_kyc_reject_sets_unverified_and_writes_audit(): void
    {
        $user = $this->makeUser();
        $user->kv = Status::KYC_PENDING;
        $user->save();

        $this->actingAs($this->makeAdmin(), 'admin')
            ->post(route('admin.users.kyc.reject', $user->id), ['reason' => 'blurry document'])
            ->assertRedirect();

        $fresh = $user->fresh();
        $this->assertSame(Status::KYC_UNVERIFIED, (int) $fresh->kv);
        $this->assertSame('blurry document', $fresh->kyc_rejection_reason); // reason kept on the user row

        $row = AuditRow::where('action', 'account.kyc.rejected')->latest('id')->first();
        $this->assertSame(Status::KYC_PENDING, (int) $row->before_values['kv']);
        $this->assertSame(Status::KYC_UNVERIFIED, (int) $row->after_values['kv']);
        $this->assertSame('rejected', $row->metadata['decision']);
    }

    public function test_kyc_reject_audit_excludes_reason_text(): void
    {
        $user = $this->makeUser();
        $user->kv = Status::KYC_PENDING;
        $user->save();
        $secret = 'SECRET-KYC-REASON-DO-NOT-LEAK';

        $this->actingAs($this->makeAdmin(), 'admin')
            ->post(route('admin.users.kyc.reject', $user->id), ['reason' => $secret])
            ->assertRedirect();

        $row = AuditRow::where('action', 'account.kyc.rejected')->latest('id')->first();
        $serialized = json_encode($row->toArray());

        $this->assertStringNotContainsString($secret, $serialized);
        $this->assertArrayNotHasKey('reason', (array) $row->metadata);
        $this->assertArrayNotHasKey('kyc_rejection_reason', (array) $row->metadata);
    }

    public function test_kyc_reject_without_reason_fails_validation_and_writes_no_audit(): void
    {
        $user = $this->makeUser();
        $user->kv = Status::KYC_PENDING;
        $user->save();

        $this->actingAs($this->makeAdmin(), 'admin')
            ->from(route('admin.users.kyc.details', $user->id))
            ->post(route('admin.users.kyc.reject', $user->id)) // missing reason
            ->assertRedirect();

        $this->assertSame(Status::KYC_PENDING, (int) $user->fresh()->kv); // unchanged
        $this->assertSame(0, AuditRow::where('action', 'account.kyc.rejected')->count());
    }

    public function test_agent_and_merchant_kyc_audited_with_correct_entity_type(): void
    {
        $agent = $this->makeAgent();
        $agent->kv = Status::KYC_PENDING;
        $agent->save();
        $this->actingAs($this->makeAdmin(), 'admin')
            ->post(route('admin.agents.kyc.approve', $agent->id))
            ->assertRedirect();
        $this->assertSame('agents', AuditRow::where('action', 'account.kyc.approved')->latest('id')->first()->entity_type);

        $merchant = $this->makeMerchant();
        $merchant->kv = Status::KYC_PENDING;
        $merchant->save();
        $this->actingAs($this->makeAdmin(), 'admin')
            ->post(route('admin.merchants.kyc.approve', $merchant->id))
            ->assertRedirect();
        $this->assertSame('merchants', AuditRow::where('action', 'account.kyc.approved')->latest('id')->first()->entity_type);
    }

    public function test_missing_account_is_404_and_writes_no_audit(): void
    {
        $this->actingAs($this->makeAdmin(), 'admin')
            ->post(route('admin.users.kyc.approve', 999999))
            ->assertNotFound();

        $this->assertSame(0, AuditRow::where('action', 'account.kyc.approved')->count());
    }
}
