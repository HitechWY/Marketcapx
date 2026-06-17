<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DRAFT (Phase 7) — DO NOT RUN against the live DB. See migrations-drafts/README.md.
 *
 * Adds a uniqueness guard so each (user_id, user_type, currency_id) has exactly
 * one wallet, preventing duplicate wallets and split balances. Validate for
 * existing duplicates on a COPY before applying (the index creation will fail
 * if duplicates exist).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wallets', function (Blueprint $table) {
            $table->unique(['user_id', 'user_type', 'currency_id'], 'wallets_owner_currency_unique');
        });
    }

    public function down(): void
    {
        Schema::table('wallets', function (Blueprint $table) {
            $table->dropUnique('wallets_owner_currency_unique');
        });
    }
};
