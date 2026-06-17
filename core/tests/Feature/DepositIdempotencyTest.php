<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Placeholder for Phase 3 (deposit atomicity / idempotency) coverage.
 *
 * These tests require a seeded test database (the app schema lives in the SQL
 * dump, not in migrations) and the Phase 3 refactor of
 * Gateway\PaymentController::userDataUpdate() to be transaction-wrapped and
 * idempotent. They are intentionally skipped until that work + a test DB exist.
 *
 * Intended assertions (do NOT run real gateway calls):
 *  - Calling the deposit credit path twice for the same trx credits the wallet
 *    exactly once (idempotent replay).
 *  - A deposit already in SUCCESS state is a no-op when reprocessed.
 *  - Concurrent processing of one deposit results in a single Transaction row.
 */
class DepositIdempotencyTest extends TestCase
{
    public function test_deposit_credit_is_idempotent(): void
    {
        $this->markTestSkipped('Phase 3: requires seeded test DB + transaction/idempotency refactor of userDataUpdate().');
    }
}
