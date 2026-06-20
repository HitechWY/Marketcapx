<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckStatus;
use App\Http\Middleware\RegistrationStep;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesMoneyFixtures;
use Tests\TestCase;

/**
 * F2c coverage: profile / change-password / 2FA / QR pages render via Inertia, and the
 * native-POST forms still hit the UNCHANGED backend handlers with the same field names.
 * DatabaseTransactions = rollback (no persistent mutation).
 */
class UserProfileSettingsTest extends TestCase
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

    public function test_guests_are_redirected_to_login(): void
    {
        foreach (['/user/profile-setting', '/user/change-password', '/user/twofactor', '/user/qr-code'] as $uri) {
            $res = $this->get($uri);
            $res->assertStatus(302);
            $this->assertStringContainsString('login', (string) $res->headers->get('Location'), "Route $uri");
        }
    }

    public function test_profile_page_renders_inertia(): void
    {
        $this->actingAs($this->makeUser())
            ->get('/user/profile-setting')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('User/Profile')->has('profile')->has('actionUrl'));
    }

    public function test_change_password_page_renders_inertia(): void
    {
        $this->actingAs($this->makeUser())
            ->get('/user/change-password')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('User/ChangePassword')->has('actionUrl'));
    }

    public function test_twofactor_and_qr_pages_render_inertia(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user)->get('/user/twofactor')->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('User/TwoFactor')->has('secret')->has('enableUrl'));
        $this->actingAs($user)->get('/user/qr-code')->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('User/QrCode')->has('qrCode'));
    }

    public function test_profile_post_rejects_missing_required_fields(): void
    {
        $this->actingAs($this->makeUser())
            ->from('/user/profile-setting')
            ->post('/user/profile-setting', ['firstname' => '', 'lastname' => ''])
            ->assertRedirect()
            ->assertSessionHasErrors(['firstname', 'lastname']);
    }

    public function test_profile_post_success_updates_name(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user)
            ->post('/user/profile-setting', ['firstname' => 'Updated', 'lastname' => 'Surname'])
            ->assertRedirect();
        $this->assertSame('Updated', $user->fresh()->firstname);
        // Rolled back by DatabaseTransactions.
    }

    public function test_password_post_rejects_missing_fields(): void
    {
        $this->actingAs($this->makeUser())
            ->from('/user/change-password')
            ->post('/user/change-password', [])
            ->assertRedirect()
            ->assertSessionHasErrors(['current_password', 'password']);
    }

    public function test_twofactor_enable_requires_code(): void
    {
        $this->actingAs($this->makeUser())
            ->from('/user/twofactor')
            ->post('/user/twofactor/enable', ['key' => 'SOMEKEY'])
            ->assertRedirect()
            ->assertSessionHasErrors(['code']);
    }
}
