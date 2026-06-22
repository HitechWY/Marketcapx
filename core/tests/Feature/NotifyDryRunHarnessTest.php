<?php

namespace Tests\Feature;

use App\Constants\Status;
use App\Models\AdminNotification;
use App\Models\GeneralSetting;
use App\Models\NotificationLog;
use App\Models\NotificationTemplate;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Tests\Concerns\CreatesMoneyFixtures;
use Tests\TestCase;

/**
 * F18a: the notification dry-run seam (`config('notify.dry_run')`, default false). When enabled, the
 * App\Notify\Email|Sms|Push transports build the message and write the NotificationLog row but SKIP the
 * actual outbound send (no real email/SMS/push, no network). Production behaviour is unchanged when the
 * flag is false. These tests run entirely with dry-run ON (no real outbound is ever attempted); the
 * false/default is asserted by config only — the real-send path is never exercised. DB-backed on
 * marketcapx_test; DatabaseTransactions rolls back. NOTE: gs() caches the GeneralSetting row in the cache
 * (not transactional), so the cache is forgotten in setUp/tearDown to keep other tests isolated.
 */
class NotifyDryRunHarnessTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesMoneyFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::forget('GeneralSetting');
    }

    protected function tearDown(): void
    {
        Cache::forget('GeneralSetting'); // undo the in-test gs() cache so the rolled-back row is reloaded
        parent::tearDown();
    }

    /** Enable channel flags + the DEFAULT template (rolled back); point mail_config at an unroutable
     *  host so a regressed seam would throw on send instead of delivering. */
    private function enableNotifications(bool $en, bool $sn, bool $pn): void
    {
        $gs = GeneralSetting::first();
        $gs->en = $en ? Status::ENABLE : Status::DISABLE;
        $gs->sn = $sn ? Status::ENABLE : Status::DISABLE;
        $gs->pn = $pn ? Status::ENABLE : Status::DISABLE;
        // Tripwire: if dry-run failed to short-circuit, SMTP to an unroutable host throws (caught) and
        // the success NotificationLog row would NOT be written — failing the test without real delivery.
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

        Cache::forget('GeneralSetting'); // force gs() to reload the freshly-enabled flags
    }

    public function test_dry_run_defaults_to_false(): void
    {
        // Proves production default; the real-send path is never run in this suite.
        $this->assertFalse((bool) config('notify.dry_run', false));
    }

    public function test_email_dry_run_writes_log_without_outbound(): void
    {
        $user = $this->makeUser();
        $this->enableNotifications(en: true, sn: false, pn: false);
        config(['notify.dry_run' => true]);

        $errorsBefore = AdminNotification::count();
        notify($user, 'DEFAULT', ['subject' => 'S', 'message' => 'Hello'], ['email']);

        $this->assertSame(1, NotificationLog::where('user_id', $user->id)
            ->where('notification_type', 'email')->count()); // success log written
        $this->assertSame($errorsBefore, AdminNotification::count()); // no error path → no send attempted
    }

    public function test_sms_dry_run_writes_log_without_outbound(): void
    {
        $user = $this->makeUser();
        $this->enableNotifications(en: false, sn: true, pn: false);
        config(['notify.dry_run' => true]);

        notify($user, 'DEFAULT', ['subject' => 'S', 'message' => 'Hello'], ['sms']);

        $this->assertSame(1, NotificationLog::where('user_id', $user->id)
            ->where('notification_type', 'sms')->count());
    }

    public function test_push_dry_run_writes_log_without_outbound(): void
    {
        $user = $this->makeUser();
        $this->enableNotifications(en: false, sn: false, pn: true);
        config(['notify.dry_run' => true]);

        notify($user, 'DEFAULT', ['subject' => 'S', 'message' => 'Hello'], ['push']);

        $this->assertSame(1, NotificationLog::where('user_id', $user->id)
            ->where('notification_type', 'push')->count());
    }

    public function test_disabled_channels_write_no_log_even_in_dry_run(): void
    {
        $user = $this->makeUser();
        $this->enableNotifications(en: false, sn: false, pn: false); // gs('en'/'sn'/'pn') all off
        config(['notify.dry_run' => true]);

        notify($user, 'DEFAULT', ['subject' => 'S', 'message' => 'Hello'], ['email']);

        // The existing channel guard returns before the dry-run branch → no log.
        $this->assertSame(0, NotificationLog::where('user_id', $user->id)->count());
    }
}
