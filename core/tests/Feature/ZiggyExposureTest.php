<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * F7b: the client Ziggy route table (`@routes('public')`) must expose ONLY guest/public route
 * names — never user/merchant/agent/admin, money, gateway/IPN, KYC, or auth-mutation routes.
 * Also pins the total route count.
 */
class ZiggyExposureTest extends TestCase
{
    public function test_public_ziggy_group_has_no_sensitive_routes(): void
    {
        $public = config('ziggy.groups.public');
        $this->assertIsArray($public, 'ziggy public group must be defined');
        $this->assertNotEmpty($public);

        foreach ($public as $name) {
            $this->assertDoesNotMatchRegularExpression(
                '/^(admin|user|merchant|agent)\./',
                $name,
                "Ziggy public group must not expose panel route: $name",
            );
            $this->assertDoesNotMatchRegularExpression(
                '/(withdraw|deposit|payment|gateway|ipn|webhook|kyc|transfer|exchange|money|escrow|voucher|invoice|api\.key)/i',
                $name,
                "Ziggy public group must not expose sensitive route: $name",
            );
        }
    }

    public function test_route_count_is_pinned(): void
    {
        $this->assertSame(798, count(Route::getRoutes()->getRoutes()), 'Route count must remain 798');
    }
}
