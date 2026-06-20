<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckStatus;
use App\Http\Middleware\Module;
use App\Http\Middleware\RegistrationStep;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\CreatesMoneyFixtures;
use Tests\TestCase;

/**
 * F7b: sensitive surfaces intentionally NOT migrated must remain Blade (no Inertia `data-page`
 * root), and no money-form Inertia page may exist. Read-only; DatabaseTransactions = rollback.
 */
class SensitiveRoutesRemainBladeTest extends TestCase
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
            Module::class,
        ]);
    }

    public function test_admin_login_is_blade(): void
    {
        // Guest sees the admin login (Blade) — not an Inertia component.
        $this->get('/admin')->assertOk()->assertDontSee('data-page', false);
    }

    public function test_merchant_api_key_page_is_blade(): void
    {
        // Structural: the secret-bearing api-key page must NOT be an Inertia page, and its
        // Blade view must be retained. (HTTP-rendering it would mutate/generate keys.)
        $pages = array_map('basename', glob(resource_path('js/Pages/Merchant/*.vue')) ?: []);
        $this->assertNotContains('ApiKey.vue', $pages, 'Merchant API key must remain Blade');
        $this->assertFileExists(resource_path('views/templates/basic/merchant/api_key.blade.php'));
    }

    public function test_money_forms_were_not_migrated_to_inertia(): void
    {
        $pages = array_map('basename', glob(resource_path('js/Pages/User/*.vue')) ?: []);
        foreach (['Withdraw.vue', 'Deposit.vue', 'Transfer.vue', 'Exchange.vue', 'MoneyOut.vue', 'MakePayment.vue', 'Payment.vue', 'Voucher.vue', 'Escrow.vue'] as $forbidden) {
            $this->assertNotContains($forbidden, $pages, "Money form $forbidden must remain Blade (no Inertia page)");
        }
    }

    public function test_money_blade_fallbacks_retained(): void
    {
        foreach ([
            'templates/basic/user/withdraw/withdraw_money.blade.php',
            'templates/basic/user/money_out/form.blade.php',
            'templates/basic/user/exchange/form.blade.php',
            'templates/basic/merchant/api_key.blade.php',
        ] as $view) {
            $this->assertFileExists(resource_path('views/' . $view));
        }
    }
}
