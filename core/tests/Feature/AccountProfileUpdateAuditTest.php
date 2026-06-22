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
 * F17: admin account edit (`ManageGuardController@update`, shared by users/agents/merchants) writes a
 * category-only semantic `account.profile.updated` audit row WITHOUT changing update behavior. The row
 * carries only changed field NAMES, the four verification-flag ints (ev/sv/ts/kv), and KYC-reset signals
 * (kv_reset / files_removed) — never PII values, KYC document data, or file paths/filenames. DB-backed on
 * marketcapx_test; DatabaseTransactions rolls back.
 */
class AccountProfileUpdateAuditTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesMoneyFixtures;

    /** A valid country.json key (Albania, dial_code 355). */
    private const COUNTRY = 'AL';
    private const DIAL = '355';

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

    /** Minimal valid payload for the update form. */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'firstname' => 'Edited',
            'lastname'  => 'Name',
            'email'     => $this->uniq('edited') . '@example.test',
            'country'   => self::COUNTRY,
            'mobile'    => '5550001',
            'address'   => 'Some Street',
            'city'      => 'Some City',
            'state'     => 'Some State',
            'zip'       => '12345',
            'ev'        => 1,
            'sv'        => 1,
            'ts'        => 1,
            'kv'        => 1,
        ], $overrides);
    }

    public function test_valid_update_persists_and_writes_audit(): void
    {
        $user = $this->makeUser();

        $this->actingAs($this->makeAdmin(), 'admin')
            ->post(route('admin.users.update', $user->id), $this->payload(['ts' => 1]))
            ->assertRedirect();

        $this->assertSame(Status::ENABLE, (int) $user->fresh()->ts); // behavior unchanged: flag persisted

        $rows = AuditRow::where('action', 'account.profile.updated')->get();
        $this->assertCount(1, $rows);

        $row = $rows->first();
        $this->assertSame('users', $row->entity_type);
        $this->assertSame($user->id, (int) $row->entity_id);
        $this->assertSame('succeeded', $row->result);
        $this->assertSame('users', $row->metadata['guard']);
    }

    public function test_audit_records_changed_field_names_and_flag_ints_only(): void
    {
        $user = $this->makeUser();
        $newEmail = $this->uniq('changed') . '@example.test';

        $this->actingAs($this->makeAdmin(), 'admin')
            ->post(route('admin.users.update', $user->id), $this->payload(['email' => $newEmail]))
            ->assertRedirect();

        $row = AuditRow::where('action', 'account.profile.updated')->latest('id')->first();

        $this->assertIsArray($row->metadata['changed_fields']);
        $this->assertContains('email', $row->metadata['changed_fields']); // a name, never the value

        // before/after carry ONLY the four verification-flag ints — no PII keys.
        $this->assertEqualsCanonicalizing(['ev', 'sv', 'ts', 'kv'], array_keys($row->before_values));
        $this->assertEqualsCanonicalizing(['ev', 'sv', 'ts', 'kv'], array_keys($row->after_values));
    }

    public function test_audit_excludes_pii_values(): void
    {
        $user = $this->makeUser();
        $sentinelName  = 'SENTINELFIRSTNAMEXYZ';
        $sentinelMail  = 'sentinelmailxyz';
        $sentinelAddr  = 'SENTINELADDRESSXYZ';

        $this->actingAs($this->makeAdmin(), 'admin')
            ->post(route('admin.users.update', $user->id), $this->payload([
                'firstname' => $sentinelName,
                'email'     => $sentinelMail . '@example.test',
                'address'   => $sentinelAddr,
            ]))
            ->assertRedirect();

        $row = AuditRow::where('action', 'account.profile.updated')->latest('id')->first();
        $serialized = json_encode($row->toArray());

        $this->assertStringNotContainsString($sentinelName, $serialized);
        $this->assertStringNotContainsString($sentinelMail, $serialized);
        $this->assertStringNotContainsString($sentinelAddr, $serialized);
    }

    public function test_kyc_reset_branch_audits_signals_without_paths_or_filenames(): void
    {
        $user = $this->makeUser();
        $user->kv = Status::KYC_VERIFIED;
        $user->kyc_data = [
            (object) ['type' => 'file', 'value' => 'SECRETKYCFILE.png'],
            (object) ['type' => 'text', 'value' => 'plain'],
        ];
        $user->save();

        $this->actingAs($this->makeAdmin(), 'admin')
            ->post(route('admin.users.update', $user->id), $this->payload(['kv' => null])) // unchecked → reset
            ->assertRedirect();

        $fresh = $user->fresh();
        $this->assertSame(0, (int) $fresh->kv);       // behavior unchanged: kv cleared
        $this->assertNull($fresh->kyc_data);          // behavior unchanged: kyc_data cleared

        $row = AuditRow::where('action', 'account.profile.updated')->latest('id')->first();
        $this->assertTrue($row->metadata['kv_reset']);
        $this->assertSame(1, (int) $row->metadata['files_removed']); // count only, one file entry

        $serialized = json_encode($row->toArray());
        $this->assertStringNotContainsString('SECRETKYCFILE', $serialized); // no filename
        $this->assertStringNotContainsString('verify/', $serialized);       // no storage path
    }

    public function test_validation_failure_writes_no_audit_row(): void
    {
        $user = $this->makeUser();

        $this->actingAs($this->makeAdmin(), 'admin')
            ->from(route('admin.users.detail', $user->id))
            ->post(route('admin.users.update', $user->id), $this->payload(['firstname' => ''])) // required
            ->assertRedirect();

        $this->assertSame(0, AuditRow::where('action', 'account.profile.updated')->count());
    }

    public function test_missing_account_is_404_and_writes_no_audit(): void
    {
        $this->actingAs($this->makeAdmin(), 'admin')
            ->post(route('admin.users.update', 999999), $this->payload())
            ->assertNotFound();

        $this->assertSame(0, AuditRow::where('action', 'account.profile.updated')->count());
    }

    public function test_mobile_duplicate_early_return_writes_no_audit_row(): void
    {
        $existing = $this->makeUser();
        $existing->mobile = '5559999';
        $existing->dial_code = self::DIAL;
        $existing->save();

        $target = $this->makeUser();

        $this->actingAs($this->makeAdmin(), 'admin')
            ->post(route('admin.users.update', $target->id), $this->payload([
                'mobile'  => '5559999',
                'country' => self::COUNTRY,
            ]))
            ->assertRedirect();

        $this->assertSame(0, AuditRow::where('action', 'account.profile.updated')->count());
    }

    public function test_agent_and_merchant_updates_audited_with_correct_entity_type(): void
    {
        $agent = $this->makeAgent();
        $this->actingAs($this->makeAdmin(), 'admin')
            ->post(route('admin.agents.update', $agent->id), $this->payload())
            ->assertRedirect();
        $this->assertSame('agents', AuditRow::where('action', 'account.profile.updated')->latest('id')->first()->entity_type);

        $merchant = $this->makeMerchant();
        $this->actingAs($this->makeAdmin(), 'admin')
            ->post(route('admin.merchants.update', $merchant->id), $this->payload())
            ->assertRedirect();
        $this->assertSame('merchants', AuditRow::where('action', 'account.profile.updated')->latest('id')->first()->entity_type);
    }
}
