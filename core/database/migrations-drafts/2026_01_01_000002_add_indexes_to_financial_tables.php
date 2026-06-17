<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DRAFT (Phase 7) — DO NOT RUN against the live DB. See migrations-drafts/README.md.
 *
 * Adds non-unique performance indexes on high-traffic financial lookups. These
 * are additive and safe (no uniqueness), but should still be validated on a COPY.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->index('user_id', 'transactions_user_id_index');
            $table->index('wallet_id', 'transactions_wallet_id_index');
            $table->index('trx', 'transactions_trx_index');
        });

        Schema::table('deposits', function (Blueprint $table) {
            $table->index('trx', 'deposits_trx_index');
        });

        Schema::table('withdrawals', function (Blueprint $table) {
            $table->index('user_id', 'withdrawals_user_id_index');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex('transactions_user_id_index');
            $table->dropIndex('transactions_wallet_id_index');
            $table->dropIndex('transactions_trx_index');
        });

        Schema::table('deposits', function (Blueprint $table) {
            $table->dropIndex('deposits_trx_index');
        });

        Schema::table('withdrawals', function (Blueprint $table) {
            $table->dropIndex('withdrawals_user_id_index');
        });
    }
};
