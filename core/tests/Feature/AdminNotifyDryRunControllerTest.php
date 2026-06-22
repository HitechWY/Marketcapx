<?php

namespace Tests\Feature;

use App\Constants\Status;
use App\Http\Middleware\AdminAuditLog;
use App\Http\Middleware\CheckStatus;
use App\Http\Middleware\Module;
use App\Http\Middleware\RegistrationStep;
use App\Models\AdminAuditLog as AuditRow;
use App\Models\GeneralSetting;
use App\Models\NotificationLog;
use App\Models\NotificationTemplate;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Tests\Concerns\CreatesMoneyFixtures;
use Tests\TestCase;

/**
 * F18b/F18c: coverage for the admin "send notification" controller methods
 * (`ManageGuardController@sendNotificationSingle|sendNotificationAll|countBySegment`, shared by
 * users/agents/merchants). Drives the REAL admin routes with the F18a `notify.dry_run=true` seam so the
 * success path runs end-to-end (validation → channel guard → UserNotificationSender → notify() →
 * transport) but the outbound transport is skipped — no real email/SMS/push, no network. DB-backed on
 * marketcapx_test; DatabaseTransactions rolls back. gs() caches GeneralSetting (non-transactional), so the
 * cache is forgotten in setUp/tearDown and after each settings mutation.
 *
 * F18c adds the semantic `account.notification.sent` audit on each successful send (in
 * UserNotificationSender, not the controller). These tests assert the row exists on success with a strict
 * metadata allow-list and NO content/PII (subject, message, recipient email/mobile/username, image
 * name/path are never persisted); guard/validation/segment-count paths still write no audit row.
 */
class AdminNotifyDryRunControllerTest extends TestCase
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
        Cache::forget('GeneralSetting');
        config(['notify.dry_run' => true]); // every send path in this file is dry-run
    }

    protected function tearDown(): void
    {
        Cache::forget('GeneralSetting'); // undo in-test gs() cache so the rolled-back row is reloaded
        parent::tearDown();
    }

    /** Enable the given channels + the DEFAULT template (rolled back); mail_config → unroutable host so a
     *  regressed dry-run seam would error on send instead of delivering. */
    private function setNotificationSettings(bool $en, bool $sn, bool $pn): void
    {
        $gs = GeneralSetting::first();
        $gs->en = $en ? Status::ENABLE : Status::DISABLE;
        $gs->sn = $sn ? Status::ENABLE : Status::DISABLE;
        $gs->pn = $pn ? Status::ENABLE : Status::DISABLE;
        $gs->mail_config = ['name' => 'smtp', 'host' => '240.0.0.0', 'port' => 2, 'enc' => 'tls', 'username' => 'x', 'password' => 'x'];
        $gs->save();

        $tpl = NotificationTemplate::where('act', 'DEFAULT')->first() ?? new NotificationTemplate();
        $tpl->act          = 'DEFAULT';
        $tpl->name         = $tpl->name ?? 'Default';
        $tpl->subject      = 'Test Subject';
        $tpl->email_body   = 'Email body {{message}}';
        $tpl->sms_body     = 'SMS body {{message}}';
        $tpl->push_body    = 'Push body {{message}}';
        $tpl->email_status = $en ? Status::ENABLE : Status::DISABLE;
        $tpl->sms_status   = $sn ? Status::ENABLE : Status::DISABLE;
        $tpl->push_status  = $pn ? Status::ENABLE : Status::DISABLE;
        $tpl->save();

        Cache::forget('GeneralSetting');
    }

    private function makeActiveUser()
    {
        $u = $this->makeUser();
        $u->status = Status::USER_ACTIVE;
        $u->ev = Status::VERIFIED;
        $u->sv = Status::VERIFIED;
        $u->save();
        return $u;
    }

    private function noAudit(): void
    {
        $this->assertSame(0, AuditRow::where('action', 'account.notification.sent')->count());
    }

    /** Fetch the single `account.notification.sent` row for the given entity (asserts exactly one). */
    private function sentRowFor(string $entityType, ?int $entityId): AuditRow
    {
        $query = AuditRow::where('action', 'account.notification.sent')->where('entity_type', $entityType);
        $entityId === null ? $query->whereNull('entity_id') : $query->where('entity_id', $entityId);

        $rows = $query->get();
        $this->assertCount(1, $rows, "exactly one account.notification.sent row expected for {$entityType}");

        return $rows->first();
    }

    /** before/after are empty + NO content/PII anywhere in the persisted row (columns + metadata). */
    private function assertCleanAuditRow(AuditRow $row, array $sentinels): void
    {
        $this->assertSame([], $row->before_values, 'before_values must be empty');
        $this->assertSame([], $row->after_values, 'after_values must be empty');

        $haystack = strtolower((string) json_encode($row->getAttributes()));
        foreach ($sentinels as $needle) {
            if ($needle === null || $needle === '') {
                continue;
            }
            $this->assertStringNotContainsString(strtolower((string) $needle), $haystack,
                "audit row must not contain content/PII sentinel: {$needle}");
        }
    }

    public function test_single_user_notification_success_writes_one_log_and_audit(): void
    {
        $user = $this->makeUser();
        $this->setNotificationSettings(en: true, sn: false, pn: false);

        $this->actingAs($this->makeAdmin(), 'admin')
            ->post(route('admin.users.notification.single', $user->id), [
                'message' => 'MESSAGE_SENTINEL_BODY',
                'via'     => 'email',
                'subject' => 'SUBJECT_SENTINEL',
            ])
            ->assertRedirect();

        $this->assertSame(1, NotificationLog::where('user_id', $user->id)
            ->where('notification_type', 'email')->count());

        $row = $this->sentRowFor('users', $user->id);
        $this->assertEquals([
            'guard'              => 'users',
            'mode'               => 'single',
            'channels_requested' => ['email'],
            'recipient_count'    => 1,
            'recipient_scope'    => 'single',
            'image_attached'     => false,
        ], $row->metadata);
        $this->assertCleanAuditRow($row, ['SUBJECT_SENTINEL', 'MESSAGE_SENTINEL_BODY', $user->email, $user->username]);
    }

    public function test_single_agent_and_merchant_notification_success(): void
    {
        $this->setNotificationSettings(en: true, sn: false, pn: false);
        $admin = $this->makeAdmin();

        $agent = $this->makeAgent();
        $this->actingAs($admin, 'admin')
            ->post(route('admin.agents.notification.single', $agent->id), ['message' => 'AGENT_MSG_SENTINEL', 'via' => 'email', 'subject' => 'AGENT_SUBJ_SENTINEL'])
            ->assertRedirect();
        $this->assertSame(1, NotificationLog::where('agent_id', $agent->id)->where('notification_type', 'email')->count());

        $merchant = $this->makeMerchant();
        $this->actingAs($admin, 'admin')
            ->post(route('admin.merchants.notification.single', $merchant->id), ['message' => 'MERCHANT_MSG_SENTINEL', 'via' => 'email', 'subject' => 'MERCHANT_SUBJ_SENTINEL'])
            ->assertRedirect();
        $this->assertSame(1, NotificationLog::where('merchant_id', $merchant->id)->where('notification_type', 'email')->count());

        // exactly one row per guard, with the correct plural entity_type + no content/PII.
        $this->assertSame(2, AuditRow::where('action', 'account.notification.sent')->count());

        $agentRow = $this->sentRowFor('agents', $agent->id);
        $this->assertSame('single', $agentRow->metadata['mode']);
        $this->assertSame('agents', $agentRow->metadata['guard']);
        $this->assertCleanAuditRow($agentRow, ['AGENT_SUBJ_SENTINEL', 'AGENT_MSG_SENTINEL', $agent->email, $agent->username]);

        $merchantRow = $this->sentRowFor('merchants', $merchant->id);
        $this->assertSame('single', $merchantRow->metadata['mode']);
        $this->assertSame('merchants', $merchantRow->metadata['guard']);
        $this->assertCleanAuditRow($merchantRow, ['MERCHANT_SUBJ_SENTINEL', 'MERCHANT_MSG_SENTINEL', $merchant->email, $merchant->username]);
    }

    public function test_validation_failures_write_no_log(): void
    {
        $user  = $this->makeUser();
        $this->setNotificationSettings(en: true, sn: false, pn: false);
        $admin = $this->makeAdmin();
        $url   = route('admin.users.notification.single', $user->id);
        $from  = route('admin.users.detail', $user->id);

        // (a) missing message
        $this->actingAs($admin, 'admin')->from($from)->post($url, ['via' => 'email', 'subject' => 'S'])->assertRedirect();
        // (b) invalid via
        $this->actingAs($admin, 'admin')->from($from)->post($url, ['message' => 'M', 'via' => 'carrier', 'subject' => 'S'])->assertRedirect();
        // (c) missing subject when via=email
        $this->actingAs($admin, 'admin')->from($from)->post($url, ['message' => 'M', 'via' => 'email'])->assertRedirect();

        $this->assertSame(0, NotificationLog::where('user_id', $user->id)->count());
        $this->noAudit();
    }

    public function test_all_channels_disabled_guard_writes_no_log(): void
    {
        $user = $this->makeUser();
        $this->setNotificationSettings(en: false, sn: false, pn: false); // en/sn/pn all off

        $this->actingAs($this->makeAdmin(), 'admin')
            ->post(route('admin.users.notification.single', $user->id), ['message' => 'M', 'via' => 'email', 'subject' => 'S'])
            ->assertRedirect();

        $this->assertSame(0, NotificationLog::where('user_id', $user->id)->count());
        $this->noAudit();
    }

    public function test_bulk_selected_users_writes_one_log_per_recipient_and_one_audit(): void
    {
        $u1 = $this->makeActiveUser();
        $u2 = $this->makeActiveUser();
        $this->setNotificationSettings(en: true, sn: false, pn: false);

        $this->actingAs($this->makeAdmin(), 'admin')
            ->post(route('admin.users.notification.all.send'), [
                'via'           => 'email',
                'message'       => 'BULK_MESSAGE_SENTINEL',
                'subject'       => 'BULK_SUBJECT_SENTINEL',
                'start'         => 1,
                'batch'         => 10,
                'cooling_time'  => 1,
                'being_sent_to' => 'selectedUsers',
                'user'          => [$u1->id, $u2->id],
            ])
            ->assertRedirect();

        $this->assertSame(2, NotificationLog::whereIn('user_id', [$u1->id, $u2->id])
            ->where('notification_type', 'email')->count());
        $this->assertFalse(session()->has('SEND_NOTIFICATION')); // batch (10) >= total (2) → completed, session cleared

        // exactly one bulk audit row for the batch; entity_id null; recipient_count is the batch count
        // (not the IDs); full metadata equality proves NO recipient IDs leak into metadata.
        $this->assertSame(1, AuditRow::where('action', 'account.notification.sent')->count());
        $row = $this->sentRowFor('users', null);
        $this->assertNull($row->entity_id);
        $this->assertEquals([
            'guard'              => 'users',
            'mode'               => 'bulk',
            'channels_requested' => ['email'],
            'recipient_count'    => 2,
            'recipient_scope'    => 'selectedUsers',
            'image_attached'     => false,
            'batch_start'        => 1,
            'batch_size'         => 10,
            'is_final_batch'     => true,
        ], $row->metadata);
        $this->assertCleanAuditRow($row, [
            'BULK_SUBJECT_SENTINEL', 'BULK_MESSAGE_SENTINEL',
            $u1->email, $u1->username, $u2->email, $u2->username,
        ]);
    }

    public function test_segment_count_is_read_only_numeric(): void
    {
        $user = $this->makeActiveUser();
        $this->setNotificationSettings(en: true, sn: false, pn: false);

        $response = $this->actingAs($this->makeAdmin(), 'admin')
            ->get(route('admin.users.segment.count', ['methodName' => 'selectedUsers', 'user' => [$user->id]]));

        $response->assertOk();
        $this->assertSame('1', trim($response->getContent())); // exactly the one selected active user
        $this->assertSame(0, NotificationLog::count());
        $this->noAudit();
    }
}
