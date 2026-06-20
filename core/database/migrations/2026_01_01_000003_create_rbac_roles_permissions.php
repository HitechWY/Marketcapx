<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F9a — RBAC enforcement foundation: schema.
 *
 * Promoted from database/migrations-drafts/ (RBAC-DESIGN.md rollout step 1).
 * Additive and backward-compatible: `admins.role_id` is nullable, so existing
 * admins keep full (legacy) access until roles are seeded and the `permission`
 * middleware is flipped to deny-by-default (rbac.enforce=true) — an owner-gated
 * step that is NOT part of F9.
 *
 * SAFETY: run only against the disposable test DB `marketcapx_test`. Running
 * against any real/dev/prod DB (esp. `paytabz_DB`) requires explicit owner
 * approval in a maintenance window with a fresh backup. See docs/RBAC-SCHEMA.md.
 *
 * Notes:
 * - Pivot FKs reference the two tables created here, so they are safe to add now.
 * - `admins.role_id` is left FK-less (indexed only): the `admins` table ships in
 *   the SQL dump; an FK there would couple to its exact engine/collation and is
 *   deferred per RBAC-DESIGN.md. Referential integrity for role_id is enforced in
 *   the app layer (Admin::role()).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('group')->nullable(); // dashboard | users | kyc | finance | gateway | settings | support | content | system | reports | roles
            $table->timestamps();
        });

        Schema::create('permission_role', function (Blueprint $table) {
            $table->unsignedBigInteger('role_id');
            $table->unsignedBigInteger('permission_id');
            $table->unique(['role_id', 'permission_id'], 'permission_role_unique');

            // Safe: both sides are tables created in this migration.
            $table->foreign('role_id')->references('id')->on('roles')->cascadeOnDelete();
            $table->foreign('permission_id')->references('id')->on('permissions')->cascadeOnDelete();
        });

        if (!Schema::hasColumn('admins', 'role_id')) {
            Schema::table('admins', function (Blueprint $table) {
                // nullable => null means legacy full access (backward-compatible).
                // Indexed for lookups; FK deliberately deferred (see docblock).
                $table->unsignedBigInteger('role_id')->nullable()->after('id')->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('admins', 'role_id')) {
            Schema::table('admins', function (Blueprint $table) {
                $table->dropColumn('role_id');
            });
        }

        Schema::dropIfExists('permission_role');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
    }
};
