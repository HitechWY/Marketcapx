<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckStatus;
use App\Http\Middleware\RegistrationStep;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesMoneyFixtures;
use Tests\TestCase;

/**
 * F2a coverage: the authenticated user dashboard (GET /user/dashboard) renders via
 * Inertia for a logged-in web user, and still redirects guests to login.
 *
 * Uses DatabaseTransactions (rollback-only — no persistent mutation). Disables the
 * bundled license middleware (Laramin\Utility\*) which 302s un-activated installs, plus
 * the status/registration gates (orthogonal to the view-layer migration under test).
 * The `auth` middleware is intentionally NOT disabled (guest-redirect assertion).
 */
class UserDashboardTest extends TestCase
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

    public function test_dashboard_redirects_guest_to_login(): void
    {
        $response = $this->get('/user/dashboard');
        $response->assertStatus(302);
        $this->assertStringContainsString('login', (string) $response->headers->get('Location'));
    }

    public function test_dashboard_renders_inertia_for_authenticated_user(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)
            ->get('/user/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('User/Dashboard')
                ->has('wallets')
                ->has('totalBalance')
                ->has('insight')
                ->has('histories')
                ->has('kyc')
                ->where('auth.user.username', $user->username));
    }
}
