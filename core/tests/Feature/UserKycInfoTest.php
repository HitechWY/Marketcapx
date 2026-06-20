<?php

namespace Tests\Feature;

use App\Constants\Status;
use App\Http\Middleware\CheckStatus;
use App\Http\Middleware\RegistrationStep;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesMoneyFixtures;
use Tests\TestCase;

/**
 * F2d coverage: the read-only KYC data page renders via Inertia for an unverified user
 * and preserves the 403 for verified users. The KYC upload form stays Blade.
 * DatabaseTransactions = rollback (no persistent mutation).
 */
class UserKycInfoTest extends TestCase
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

    public function test_guest_is_redirected_to_login(): void
    {
        $res = $this->get('/user/kyc-data');
        $res->assertStatus(302);
        $this->assertStringContainsString('login', (string) $res->headers->get('Location'));
    }

    public function test_kyc_data_renders_inertia_for_unverified_user(): void
    {
        $user = $this->makeUser();
        $user->kv = 0; // unverified
        $user->save();

        $this->actingAs($user)
            ->get('/user/kyc-data')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('User/KycInfo')->has('fields'));
    }

    public function test_kyc_data_is_forbidden_for_verified_user(): void
    {
        $user = $this->makeUser();
        $user->kv = Status::VERIFIED;
        $user->save();

        $this->actingAs($user)->get('/user/kyc-data')->assertStatus(403);
    }
}
