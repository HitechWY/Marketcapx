<?php

namespace App\Console\Commands;

use App\Models\Admin;
use App\Models\AdminAuditLog;
use App\Support\PermissionCatalog;
use Illuminate\Console\Command;

/**
 * RBAC would-deny report (F12). READ-ONLY review tool for the admin audit log.
 *
 * Summarises RBAC permission-decision rows (default: `would_deny`) so an owner can,
 * before ever enabling deny-by-default, see which admins hit which permissions on which
 * routes and grant the missing access (or confirm it should be denied). See
 * docs/F12-WOULD-DENY-REVIEW.md and docs/RBAC-DENY-BY-DEFAULT-READINESS.md.
 *
 * SAFETY:
 *  - Read-only: SELECTs only (via AdminAuditLog read scopes). Never writes/mutates.
 *  - Never emits sensitive columns: ip / user_agent / metadata / before_values /
 *    after_values are not selected or printed (table or JSON).
 *  - This command does NOT enable enforcement and does NOT change admin behavior.
 *  - Run it only against a DB you are authorised to read; real-DB review is owner-gated.
 *
 * Note: this reports RBAC *decision* rows (result ∈ would_deny|allowed|denied). Mutation
 * rows (succeeded|failed) are a separate concern and out of scope here.
 */
class RbacWouldDenyReport extends Command
{
    protected $signature = 'rbac:would-deny-report
        {--days=7 : Look back this many days (by created_at)}
        {--admin= : Filter to a single admin id}
        {--permission= : Filter to a single permission slug}
        {--route= : Filter to a single route name}
        {--result=would_deny : RBAC decision result to report (would_deny|allowed|denied)}
        {--group-by=permission : Group findings by permission|admin|route|result}
        {--format=table : Output format (table|json)}
        {--limit=100 : Max grouped rows to display}';

    protected $description = 'Read-only summary of RBAC would-deny (or allowed/denied) audit events for deny-by-default review.';

    private const VALID_RESULTS = ['would_deny', 'allowed', 'denied'];
    private const VALID_GROUPS = ['permission', 'admin', 'route', 'result'];
    private const VALID_FORMATS = ['table', 'json'];

    public function handle(): int
    {
        $result = (string) $this->option('result');
        $groupBy = (string) $this->option('group-by');
        $format = (string) $this->option('format');
        $days = (int) $this->option('days');
        $limit = (int) $this->option('limit');

        if (!in_array($result, self::VALID_RESULTS, true)) {
            $this->error("Invalid --result '{$result}'. Allowed: " . implode('|', self::VALID_RESULTS));
            return self::FAILURE;
        }
        if (!in_array($groupBy, self::VALID_GROUPS, true)) {
            $this->error("Invalid --group-by '{$groupBy}'. Allowed: " . implode('|', self::VALID_GROUPS));
            return self::FAILURE;
        }
        if (!in_array($format, self::VALID_FORMATS, true)) {
            $this->error("Invalid --format '{$format}'. Allowed: " . implode('|', self::VALID_FORMATS));
            return self::FAILURE;
        }
        if ($days < 1) {
            $this->error('--days must be >= 1.');
            return self::FAILURE;
        }

        $admin = $this->option('admin');
        $permission = $this->option('permission');
        $route = $this->option('route');

        // Fresh base query per use (read-only): RBAC decision rows with the chosen result + filters.
        $base = fn () => AdminAuditLog::query()
            ->rbacDecision()
            ->result($result)
            ->since($days)
            ->when($admin !== null, fn ($q) => $q->forAdmin($admin))
            ->when($permission !== null, fn ($q) => $q->permission((string) $permission))
            ->when($route !== null, fn ($q) => $q->routeName((string) $route));

        $total = (clone $base())->count();
        $distinctAdmins = (clone $base())->distinct()->count('admin_id');
        $distinctPermissions = (clone $base())->distinct()->count('permission_slug');
        $distinctRoutes = (clone $base())->distinct()->count('route_name');

        // Readiness warning: legacy null-role admins keep full access and NEVER produce
        // would_deny, so the report is incomplete until every admin has a role.
        $legacyNullRoleAdmins = Admin::whereNull('role_id')->count();

        // Slugs present in the matched set that are not in the permission catalog (mapping drift).
        $slugsInSet = (clone $base())->whereNotNull('permission_slug')->distinct()->pluck('permission_slug')->all();
        $unmappedSlugs = array_values(array_diff($slugsInSet, PermissionCatalog::slugs()));

        [$headers, $rows, $groups] = $this->buildGroups($base, $groupBy, $limit);

        if ($format === 'json') {
            $this->line((string) json_encode([
                'window_days' => $days,
                'result' => $result,
                'group_by' => $groupBy,
                'totals' => [
                    'matching' => $total,
                    'distinct_admins' => $distinctAdmins,
                    'distinct_permissions' => $distinctPermissions,
                    'distinct_routes' => $distinctRoutes,
                ],
                'warnings' => [
                    'legacy_null_role_admins' => $legacyNullRoleAdmins,
                    'unmapped_permission_slugs' => $unmappedSlugs,
                ],
                'groups' => $groups,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        // Table format.
        $this->info('RBAC permission-decision report (read-only)');
        $this->line("Window: last {$days} day(s)  |  result: {$result}  |  group-by: {$groupBy}");
        $this->line("Matching rows: {$total}  |  distinct admins: {$distinctAdmins}  |  distinct permissions: {$distinctPermissions}  |  distinct routes: {$distinctRoutes}");

        if ($legacyNullRoleAdmins > 0) {
            $this->warn("{$legacyNullRoleAdmins} admin(s) still have a null role (legacy full access). They never produce would_deny — assign roles before trusting this report.");
        }
        if (!empty($unmappedSlugs)) {
            $this->warn('Unmapped permission slugs (not in PermissionCatalog): ' . implode(', ', $unmappedSlugs));
        }

        if ($total === 0) {
            $this->line("No '{$result}' RBAC events in the last {$days} day(s).");
            return self::SUCCESS;
        }

        $this->table($headers, $rows);
        $this->line('Next: for each would_deny, grant the missing permission or confirm it should be denied. See docs/F12-WOULD-DENY-REVIEW.md.');

        return self::SUCCESS;
    }

    /**
     * Build grouped findings for the chosen dimension. Aggregates only (no sensitive
     * columns are selected).
     *
     * @return array{0: array<int,string>, 1: array<int,array<int,mixed>>, 2: array<int,array<string,mixed>>}
     */
    private function buildGroups(callable $base, string $groupBy, int $limit): array
    {
        switch ($groupBy) {
            case 'admin':
                $records = (clone $base())
                    ->selectRaw('admin_id, admin_username, COUNT(*) as event_count')
                    ->groupBy('admin_id', 'admin_username')
                    ->orderByDesc('event_count')
                    ->limit($limit)->get();
                $headers = ['admin_id', 'admin_username', 'count'];
                $rows = $records->map(fn ($r) => [$r->admin_id, $r->admin_username, (int) $r->event_count])->all();
                $groups = $records->map(fn ($r) => [
                    'admin_id' => $r->admin_id !== null ? (int) $r->admin_id : null,
                    'admin_username' => $r->admin_username,
                    'count' => (int) $r->event_count,
                ])->all();
                break;

            case 'route':
                $records = (clone $base())
                    ->selectRaw('route_name, COUNT(*) as event_count')
                    ->groupBy('route_name')
                    ->orderByDesc('event_count')
                    ->limit($limit)->get();
                $headers = ['route_name', 'count'];
                $rows = $records->map(fn ($r) => [$r->route_name, (int) $r->event_count])->all();
                $groups = $records->map(fn ($r) => ['route_name' => $r->route_name, 'count' => (int) $r->event_count])->all();
                break;

            case 'result':
                $records = (clone $base())
                    ->selectRaw('result, COUNT(*) as event_count')
                    ->groupBy('result')
                    ->orderByDesc('event_count')
                    ->limit($limit)->get();
                $headers = ['result', 'count'];
                $rows = $records->map(fn ($r) => [$r->result, (int) $r->event_count])->all();
                $groups = $records->map(fn ($r) => ['result' => $r->result, 'count' => (int) $r->event_count])->all();
                break;

            case 'permission':
            default:
                $records = (clone $base())
                    ->selectRaw('permission_slug, COUNT(*) as event_count')
                    ->groupBy('permission_slug')
                    ->orderByDesc('event_count')
                    ->limit($limit)->get();
                $headers = ['permission_slug', 'count'];
                $rows = $records->map(fn ($r) => [$r->permission_slug, (int) $r->event_count])->all();
                $groups = $records->map(fn ($r) => ['permission_slug' => $r->permission_slug, 'count' => (int) $r->event_count])->all();
                break;
        }

        return [$headers, $rows, $groups];
    }
}
