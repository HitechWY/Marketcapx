<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckStatus;
use App\Http\Middleware\RegistrationStep;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesMoneyFixtures;
use Tests\TestCase;

/**
 * F3c: agent dashboard renders via Inertia for an authenticated agent; guests are
 * redirected. DatabaseTransactions = rollback (no persistent mutation).
 */
class AgentDashboardTest extends TestCase
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
        $this->get('/agent/dashboard')->assertStatus(302);
    }

    public function test_dashboard_renders_inertia_for_agent(): void
    {
        $this->actingAs($this->makeAgent(), 'agent')
            ->get('/agent/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->component('Agent/Dashboard')
                ->has('wallets')
                ->has('totalBalance')
                ->has('histories')
                ->has('kyc'));
    }
}
