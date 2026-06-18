<?php

namespace Tests\Unit;

use App\Models\Agent;
use App\Models\Merchant;
use PHPUnit\Framework\TestCase;

/**
 * B7: auth/secret fields must never appear in array/JSON serialisation of the
 * Agent and Merchant models, while remaining readable via direct property access
 * (the password hasher and the merchant API-key Blade rely on direct access).
 *
 * DB-free: operates on unsaved model instances; $hidden is applied by toArray().
 */
class ModelHiddenAttributesTest extends TestCase
{
    public function test_merchant_hides_secrets_in_serialisation_but_keeps_direct_access(): void
    {
        $merchant = new Merchant();
        $merchant->password       = 'hashed-secret';
        $merchant->remember_token = 'remember-me';
        $merchant->secret_api_key = 'sk_live_should_not_leak';
        $merchant->public_api_key = 'pk_live_ok';

        $array = $merchant->toArray();

        foreach (['password', 'remember_token', 'secret_api_key'] as $hidden) {
            $this->assertArrayNotHasKey($hidden, $array, "$hidden must not be serialised");
        }
        // Public key is fine to serialise, and direct access still works.
        $this->assertSame('pk_live_ok', $array['public_api_key'] ?? null);
        $this->assertSame('sk_live_should_not_leak', $merchant->secret_api_key);
        $this->assertSame('hashed-secret', $merchant->password);
    }

    public function test_agent_hides_auth_secrets_in_serialisation(): void
    {
        $agent = new Agent();
        $agent->password       = 'hashed-secret';
        $agent->remember_token = 'remember-me';

        $array = $agent->toArray();

        $this->assertArrayNotHasKey('password', $array);
        $this->assertArrayNotHasKey('remember_token', $array);
        $this->assertSame('hashed-secret', $agent->password);
    }
}
