<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DRAFT (Phase 6 / RBAC) — DO NOT RUN against the live DB.
 * See migrations-drafts/README.md and docs/RBAC-DESIGN.md.
 *
 * Implements rollout step 1 of docs/RBAC-DESIGN.md: the schema for admin
 * role-based access control. Backward-compatible — `admins.role_id` is nullable
 * so existing admins keep full (legacy) access until roles are seeded and the
 * `permission` middleware is flipped to deny-by-default (rollout steps 3-5).
 *
 * Validate on a disposable COPY of the database (never paytabz_DB). The
 * `admins` table already exists in the SQL dump, so the role_id column is added
 * via Schema::table; guard the add with a hasColumn check when applying.
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
            $table->string('group')->nullable(); // finance | gateways | settings | users | content
            $table->timestamps();
        });

        Schema::create('permission_role', function (Blueprint $table) {
            $table->unsignedBigInteger('role_id');
            $table->unsignedBigInteger('permission_id');
            $table->unique(['role_id', 'permission_id'], 'permission_role_unique');
            // FK candidates (add only after orphan-check on a COPY):
            // $table->foreign('role_id')->references('id')->on('roles')->cascadeOnDelete();
            // $table->foreign('permission_id')->references('id')->on('permissions')->cascadeOnDelete();
        });

        if (!Schema::hasColumn('admins', 'role_id')) {
            Schema::table('admins', function (Blueprint $table) {
                // nullable => null means legacy full access (backward-compatible)
                $table->unsignedBigInteger('role_id')->nullable()->after('id');
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
