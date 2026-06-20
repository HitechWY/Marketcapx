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
 * F7a: regression guard proving the Inertia shared/admin/list props never serialize sensitive
 * fields (password, remember_token, 2FA secret, api secret, raw kyc_data, balance). Read-only;
 * DatabaseTransactions = rollback (no persistent mutation).
 */
class InertiaPropExposureTest extends TestCase
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

    public function test_auth_user_excludes_sensitive_fields_for_every_guard(): void
    {
        $cases = [
            [$this->makeUser(), null, '/user/dashboard'],
            [$this->makeAgent(), 'agent', '/agent/dashboard'],
            [$this->makeMerchant(), 'merchant', '/merchant/dashboard'],
            [$this->makeAdmin(), 'admin', '/admin/dashboard'],
        ];

        foreach ($cases as [$actor, $guard, $uri]) {
            $req = $guard ? $this->actingAs($actor, $guard) : $this->actingAs($actor);
            $req->get($uri)
                ->assertOk()
                ->assertInertia(fn (Assert $p) => $p
                    ->has('auth.user.id')
                    ->missing('auth.user.password')
                    ->missing('auth.user.remember_token')
                    ->missing('auth.user.tsc')
                    ->missing('auth.user.secret_api_key')
                    ->missing('auth.user.kyc_data'));
        }
    }

    public function test_user_dashboard_exposes_kyc_status_but_not_raw_kyc_data(): void
    {
        $this->actingAs($this->makeUser())
            ->get('/user/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->has('kyc.status')
                ->missing('kyc.kyc_data')
                ->missing('kyc.data'));
    }

    public function test_admin_user_list_rows_exclude_secret_fields(): void
    {
        $this->makeUser(); // ensures at least one row

        $this->actingAs($this->makeAdmin(), 'admin')
            ->get('/admin/users')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->has('users.data.0', fn (Assert $row) => $row
                    ->has('username')
                    ->has('email')
                    ->missing('password')
                    ->missing('remember_token')
                    ->missing('balance')
                    ->missing('kyc_data')
                    ->etc()));
    }
}
