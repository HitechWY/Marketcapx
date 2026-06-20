<?php

namespace Tests\Feature;

use App\Models\AgentPasswordReset;
use App\Models\MerchantResetPassword;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesMoneyFixtures;
use Tests\TestCase;

/**
 * F4c: the captcha-free merchant/agent password-recovery GET pages render via the shared
 * Inertia Auth/* components with native-POST contracts pointing at the UNCHANGED controllers.
 * DatabaseTransactions = rollback (no persistent rows).
 */
class MerchantAgentAuthPagesTest extends TestCase
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

    public function test_merchant_forgot_password_renders_inertia(): void
    {
        $this->get(route('merchant.password.request'))
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->component('Auth/ForgotPassword')
                ->where('submitUrl', route('merchant.password.email'))
                ->where('loginUrl', route('merchant.login')));
    }

    public function test_merchant_code_verify_session_gate_and_render(): void
    {
        $this->get(route('merchant.password.code.verify'))->assertStatus(302);

        $this->withSession(['pass_res_mail' => 'm@example.test'])
            ->get(route('merchant.password.code.verify'))
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->component('Auth/CodeVerify')
                ->where('email', 'm@example.test')
                ->where('submitUrl', route('merchant.password.verify.code')));
    }

    public function test_merchant_reset_renders_for_valid_token(): void
    {
        $email = 'mreset@example.test';
        $token = '654321';

        $pr             = new MerchantResetPassword();
        $pr->email      = $email;
        $pr->token      = $token;
        $pr->created_at = now();
        $pr->save();

        $this->withSession(['fpass_email' => $email])
            ->get(route('merchant.password.reset', $token))
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->component('Auth/ResetPassword')
                ->where('email', $email)
                ->where('token', $token)
                ->where('submitUrl', route('merchant.password.update')));
    }

    public function test_agent_forgot_password_renders_inertia(): void
    {
        $this->get(route('agent.password.request'))
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->component('Auth/ForgotPassword')
                ->where('submitUrl', route('agent.password.email'))
                ->where('loginUrl', route('agent.login')));
    }

    public function test_agent_code_verify_session_gate_and_render(): void
    {
        $this->get(route('agent.password.code.verify'))->assertStatus(302);

        $this->withSession(['pass_res_mail' => 'a@example.test'])
            ->get(route('agent.password.code.verify'))
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->component('Auth/CodeVerify')
                ->where('email', 'a@example.test')
                ->where('submitUrl', route('agent.password.verify.code')));
    }

    public function test_agent_reset_renders_for_valid_token(): void
    {
        $email = 'areset@example.test';
        $token = '111222';

        $pr             = new AgentPasswordReset();
        $pr->email      = $email;
        $pr->token      = $token;
        $pr->created_at = now();
        $pr->save();

        $this->withSession(['fpass_email' => $email])
            ->get(route('agent.password.reset', $token))
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->component('Auth/ResetPassword')
                ->where('email', $email)
                ->where('token', $token)
                ->where('submitUrl', route('agent.password.update')));
    }
}
