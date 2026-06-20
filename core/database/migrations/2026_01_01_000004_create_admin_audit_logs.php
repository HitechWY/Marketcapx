<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F10a — Admin audit-log foundation: schema.
 *
 * Append-only structured audit trail for admin actions (RBAC permission decisions
 * + admin state-changing requests). Additive; no existing table touched.
 *
 * SAFETY: run only against the disposable test DB `marketcapx_test`. Running against
 * any real/dev/prod DB (esp. `paytabz_DB`) requires explicit owner approval in a
 * maintenance window with a fresh backup. See docs/ADMIN-AUDIT-LOG-SCHEMA.md.
 *
 * JSON: on MariaDB 11.4 `$table->json()` maps to LONGTEXT (JSON is an alias). The
 * AdminAuditLog model casts these columns to array. No separate longtext fallback.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('admin_audit_logs')) {
            return;
        }

        Schema::create('admin_audit_logs', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('admin_id')->nullable()->index();
            $table->string('admin_username')->nullable();

            $table->string('action')->index();
            $table->string('permission_slug')->nullable()->index();
            $table->string('route_name')->nullable()->index();
            $table->string('method', 10);
            $table->string('path');

            $table->string('entity_type')->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();

            $table->string('result')->index(); // allowed | would_deny | denied | succeeded | failed
            $table->unsignedSmallInteger('http_status')->nullable();

            $table->string('ip', 45)->nullable();
            $table->string('user_agent')->nullable();      // truncated by the logger
            $table->string('correlation_id', 64)->nullable()->index();

            // Reserved for the audited mutation-endpoint phases (F14+); null in F10.
            $table->json('before_values')->nullable();
            $table->json('after_values')->nullable();

            $table->json('metadata')->nullable();           // sanitized + size-capped
            $table->string('error_code')->nullable();       // sanitized; no stack/secret

            // Append-only: created_at only, no updated_at.
            $table->timestamp('created_at')->nullable()->index();

            $table->index(['entity_type', 'entity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_audit_logs');
    }
};
