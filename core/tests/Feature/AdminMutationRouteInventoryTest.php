<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * F8b coverage: a read-only inventory of the high-risk admin mutation routes
 * documented in docs/ADMIN-MUTATION-CONTRACT-AUDIT.md.
 *
 * Asserts each route EXISTS with the EXPECTED HTTP verb. This is route-collection
 * inspection only — NO endpoint is dispatched and NO DB/balance is touched — so it
 * is safe to run without a seeded test database and cannot move money or approval
 * state. It is a drift guard: if a frozen mutation route's verb/name changes, this
 * fails and forces a deliberate review.
 */
class AdminMutationRouteInventoryTest extends TestCase
{
    /** name => expected HTTP method. */
    private const MUTATION_ROUTES = [
        // status (block/unblock) — one per guard
        'admin.users.status'              => 'POST',
        'admin.agents.status'             => 'POST',
        'admin.merchants.status'          => 'POST',
        // KYC approve/reject
        'admin.users.kyc.approve'         => 'POST',
        'admin.users.kyc.reject'          => 'POST',
        'admin.merchants.kyc.approve'     => 'POST',
        // account edit + impersonation (impersonation is a GET today)
        'admin.users.update'              => 'POST',
        'admin.users.login'               => 'GET',
        // manual balance adjustment
        'admin.users.add.sub.balance'     => 'POST',
        'admin.agents.add.sub.balance'    => 'POST',
        'admin.merchants.add.sub.balance' => 'POST',
        // deposits
        'admin.deposit.approve'           => 'POST',
        'admin.deposit.reject'            => 'POST',
        // withdrawals
        'admin.withdraw.data.approve'     => 'POST',
        'admin.withdraw.data.reject'      => 'POST',
        // escrow
        'admin.escrow.action'             => 'POST',
        // support tickets
        'admin.ticket.reply'              => 'POST',
        'admin.ticket.close'              => 'POST',
        'admin.ticket.delete'             => 'POST',
        // gateways / methods / currency (secrets-bearing)
        'admin.gateway.automatic.update'  => 'POST',
        'admin.gateway.manual.update'     => 'POST',
        'admin.currency.update'           => 'POST',
        'admin.transaction.charges.update'=> 'POST',
        // notification settings (secrets-bearing)
        'admin.setting.notification.global.email.update' => 'POST',
        // code / system / cron (high blast radius)
        'admin.plugins.upload'            => 'POST',
        'admin.extensions.update'         => 'POST',
        'admin.system.update.process'     => 'POST',
        'admin.cron.store'                => 'POST',
    ];

    public function test_documented_admin_mutation_routes_exist_with_expected_verb(): void
    {
        $routes = Route::getRoutes();

        foreach (self::MUTATION_ROUTES as $name => $method) {
            $route = $routes->getByName($name);

            $this->assertNotNull($route, "Frozen admin mutation route [{$name}] is missing.");
            $this->assertContains(
                $method,
                $route->methods(),
                "[{$name}] should accept {$method} (frozen contract drift?)."
            );
        }
    }
}
