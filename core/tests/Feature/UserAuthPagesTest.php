<?php

namespace Tests\Feature;

use App\Models\PasswordReset;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesMoneyFixtures;
use Tests\TestCase;

/**
 * F4b: the captcha-free user password-recovery GET pages render via Inertia with native-POST
 * contracts (action URL props point at the UNCHANGED controllers). Validation/auth/captcha/
 * reset-token logic is untouched. DatabaseTransactions = rollback (no persistent rows).
 */
class UserAuthPagesTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesMoneyFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([
            \Laramin\Utility\Utility::class,
            \Laramin\Utility\GoToCore::class,
        ]);
    }

    public function test_forgot_password_renders_inertia_with_native_post_contract(): void
    {
        $this->get(route('user.password.request'))
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->component('Auth/ForgotPassword')
                ->where('submitUrl', route('user.password.email'))
                ->where('loginUrl', route('user.login')));
    }

    public function test_code_verify_requires_session_and_renders_inertia(): void
    {
        // No session -> redirected back to the request page.
        $this->get(route('user.password.code.verify'))->assertStatus(302);

        $this->withSession(['pass_res_mail' => 'recover@example.test'])
            ->get(route('user.password.code.verify'))
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->component('Auth/CodeVerify')
                ->where('email', 'recover@example.test')
                ->where('submitUrl', route('user.password.verify.code')));
    }

    public function test_reset_password_renders_inertia_for_valid_token(): void
    {
        $email = 'reset@example.test';
        $token = '123456';

        $pr             = new PasswordReset();
        $pr->email      = $email;
        $pr->token      = $token;
        $pr->created_at = now();
        $pr->save();

        $this->withSession(['fpass_email' => $email])
            ->get(route('user.password.reset', $token))
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->component('Auth/ResetPassword')
                ->where('email', $email)
                ->where('token', $token)
                ->where('submitUrl', route('user.password.update')));
    }

    public function test_reset_password_invalid_token_redirects(): void
    {
        $this->withSession(['fpass_email' => 'nobody@example.test'])
            ->get(route('user.password.reset', 'badtoken'))
            ->assertStatus(302);
    }
}
