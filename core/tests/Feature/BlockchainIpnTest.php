<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Phase 2 coverage for the Blockchain gateway IPN authenticity fix.
 *
 * Requires a seeded test database (Deposit + a Blockchain gateway with a
 * configured `secret` in gateway_parameter). Skipped until a test DB exists.
 * Do NOT call the real blockchain.info API.
 *
 * Intended assertions:
 *  - Callback with a missing/empty configured secret -> 403, no credit, no detail saved.
 *  - Callback with a wrong `secret` -> 403, no credit, no detail saved.
 *  - Callback for an unknown trx or non-INITIATE deposit -> 404, nothing written.
 *  - Callback with correct secret + matching address/amount/confirmations -> deposit
 *    credited exactly once and detail persisted only after verification.
 */
class BlockchainIpnTest extends TestCase
{
    public function test_forged_blockchain_callback_is_rejected(): void
    {
        $this->markTestSkipped('Phase 2: requires seeded test DB with a Blockchain gateway configured.');
    }
}
