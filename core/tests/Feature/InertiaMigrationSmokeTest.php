<?php

namespace Tests\Feature;

use App\Http\Middleware\AdminAuditLog;
use App\Http\Middleware\CheckStatus;
use App\Http\Middleware\Module;
use App\Http\Middleware\RegistrationStep;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesMoneyFixtures;
use Tests\TestCase;

/**
 * F7b: broad regression smoke for the F1–F6 Inertia migration. Public pages mount their
 * components; each panel's migrated pages redirect guests and mount the right component when
 * authenticated; F4 auth recovery pages render. Read-only; DatabaseTransactions = rollback.
 *
 * Note: guest assertions run BEFORE any actingAs() — actingAs persists for the rest of a test.
 */
class InertiaMigrationSmokeTest extends TestCase
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

    public function test_public_pages_mount_inertia(): void
    {
        $this->get('/')->assertOk()->assertInertia(fn (Assert $p) => $p->component('Public/Home'));
        $this->get('/contact')->assertOk()->assertInertia(fn (Assert $p) => $p->component('Public/Contact'));
    }

    public function test_user_pages_guarded_and_mount_when_authed(): void
    {
        $map = ['/user/dashboard' => 'User/Dashboard', '/user/transactions' => 'User/Transactions', '/user/wallets' => 'User/Wallets'];
        foreach (array_keys($map) as $uri) {
            $this->get($uri)->assertStatus(302);
        }
        $user = $this->makeUser();
        foreach ($map as $uri => $component) {
            $this->actingAs($user)->get($uri)
                ->assertOk()->assertInertia(fn (Assert $p) => $p->component($component));
        }
    }

    public function test_merchant_pages_guarded_and_mount_when_authed(): void
    {
        $map = ['/merchant/dashboard' => 'Merchant/Dashboard', '/merchant/transaction/history' => 'Merchant/Transactions', '/merchant/wallets' => 'Merchant/Wallets'];
        foreach (array_keys($map) as $uri) {
            $this->get($uri)->assertStatus(302);
        }
        $merchant = $this->makeMerchant();
        foreach ($map as $uri => $component) {
            $this->actingAs($merchant, 'merchant')->get($uri)
                ->assertOk()->assertInertia(fn (Assert $p) => $p->component($component));
        }
    }

    public function test_agent_pages_guarded_and_mount_when_authed(): void
    {
        $map = ['/agent/dashboard' => 'Agent/Dashboard', '/agent/transaction/history' => 'Agent/Transactions', '/agent/commission-log' => 'Agent/CommissionLog'];
        foreach (array_keys($map) as $uri) {
            $this->get($uri)->assertStatus(302);
        }
        $agent = $this->makeAgent();
        foreach ($map as $uri => $component) {
            $this->actingAs($agent, 'agent')->get($uri)
                ->assertOk()->assertInertia(fn (Assert $p) => $p->component($component));
        }
    }

    public function test_admin_pages_guarded_and_mount_when_authed(): void
    {
        $map = ['/admin/dashboard' => 'Admin/Dashboard', '/admin/users' => 'Admin/GuardList', '/admin/deposit/all' => 'Admin/Deposits', '/admin/report/transaction' => 'Admin/Transactions'];
        foreach (array_keys($map) as $uri) {
            $this->get($uri)->assertStatus(302);
        }
        $admin = $this->makeAdmin();
        foreach ($map as $uri => $component) {
            $this->actingAs($admin, 'admin')->get($uri)
                ->assertOk()->assertInertia(fn (Assert $p) => $p->component($component));
        }
    }

    public function test_admin_notification_log_guarded_and_mounts(): void
    {
        // F13a: per-user notification log migrated to read-only Inertia (all three guards
        // share ManageGuardController@notificationLog).
        $user = $this->makeUser();
        $uri  = "/admin/users/notification-log/{$user->id}";

        $this->get($uri)->assertStatus(302); // guest redirected before controller runs

        $this->actingAs($this->makeAdmin(), 'admin')->get($uri)
            ->assertOk()->assertInertia(fn (Assert $p) => $p->component('Admin/NotificationLog'));
    }

    public function test_auth_recovery_pages_render_inertia(): void
    {
        foreach (['user', 'merchant', 'agent'] as $guard) {
            $this->get(route("$guard.password.request"))
                ->assertOk()->assertInertia(fn (Assert $p) => $p->component('Auth/ForgotPassword'));
        }
    }
}
