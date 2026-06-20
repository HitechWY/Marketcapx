<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckStatus;
use App\Http\Middleware\RegistrationStep;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesMoneyFixtures;
use Tests\TestCase;

/**
 * F3a: merchant dashboard renders via Inertia for an authenticated merchant; guests are
 * redirected. DatabaseTransactions = rollback (no persistent mutation).
 */
class MerchantDashboardTest extends TestCase
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
        ]);
    }

    public function test_guest_is_redirected(): void
    {
        $this->get('/merchant/dashboard')->assertStatus(302);
    }

    public function test_dashboard_renders_inertia_for_merchant(): void
    {
        $this->actingAs($this->makeMerchant(), 'merchant')
            ->get('/merchant/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->component('Merchant/Dashboard')
                ->has('wallets')
                ->has('totalBalance')
                ->has('histories')
                ->has('kyc'));
    }
}
