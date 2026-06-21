<?php

namespace App\Services;

use App\Models\AdminAuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Writes structured admin audit rows (F10). Single sink for:
 *  - RBAC permission decisions (PermissionMiddleware, F10c)
 *  - admin state-changing requests (AdminAuditLog middleware, F10d)
 *
 * Guarantees:
 *  - NEVER logs secrets/credentials/tokens/keys/raw bodies (see SENSITIVE_KEYS +
 *    sanitizeMetadata); the middlewares never read the request body.
 *  - NEVER breaks a request: every write is wrapped; on failure it falls back to the
 *    file channel and swallows the error (fail-open in F10 — fail-closed for future
 *    high-risk mutations is a separate, owner-gated decision).
 */
class AdminAuditLogger
{
    /** Substrings that mark a key as sensitive (case-insensitive). */
    private const SENSITIVE_KEYS = [
        'password', 'pass', 'pwd', 'token', 'secret', 'api_key', 'apikey', 'key',
        'credential', 'authorization', 'auth', 'card', 'cvv', 'cvc', 'pin', 'ssn',
        'account_number', 'private', 'signature', 'webhook', 'callback', 'kyc',
    ];

    private const MAX_METADATA_BYTES = 2048;
    private const MAX_STRING_LEN = 500;
    private const MAX_UA_LEN = 255;

    /** RBAC permission decision (allowed | would_deny | denied). */
    public function logPermissionDecision(?object $admin, string $slug, string $result, Request $request, ?int $httpStatus = null): void
    {
        [$entityType, $entityId] = $this->inferEntity($request);

        $this->write([
            'action'          => 'rbac.permission.' . $result,
            'permission_slug' => $slug,
            'result'          => $result,
            'http_status'     => $httpStatus,
            'entity_type'     => $entityType,
            'entity_id'       => $entityId,
        ], $admin, $request);
    }

    /** Admin state-changing request (POST/PATCH/PUT/DELETE), result from response status. */
    public function logAdminRequest(Request $request, $response): void
    {
        $status = (is_object($response) && method_exists($response, 'getStatusCode'))
            ? $response->getStatusCode() : null;

        $failed = $status !== null && $status >= 400;
        [$entityType, $entityId] = $this->inferEntity($request);

        $this->write([
            'action'      => $failed ? 'admin.request.failed' : 'admin.request.mutation',
            'result'      => $failed ? 'failed' : 'succeeded',
            'http_status' => $status,
            'entity_type' => $entityType,
            'entity_id'   => $entityId,
        ], $request->user('admin'), $request);
    }

    /**
     * Semantic admin mutation event (F14+). Captures action + before/after. When a
     * Request is passed (HTTP context) the row also carries method/path/ip/route_name/
     * correlation_id; with null (default) it remains a CLI-style row (back-compatible).
     */
    public function logMutation(string $action, ?object $admin, ?string $entityType, ?int $entityId, array $before = [], array $after = [], array $metadata = [], ?Request $request = null): void
    {
        $this->write([
            'action'        => $action,
            'result'        => 'succeeded',
            'entity_type'   => $entityType,
            'entity_id'     => $entityId,
            'before_values' => $this->sanitizeMetadata($before),
            'after_values'  => $this->sanitizeMetadata($after),
            'metadata'      => $this->sanitizeMetadata($metadata),
        ], $admin, $request);
    }

    /** Redact sensitive keys, truncate long strings, cap total size. */
    public function sanitizeMetadata(array $data): array
    {
        $clean = $this->redact($data);

        if (strlen((string) json_encode($clean)) > self::MAX_METADATA_BYTES) {
            return ['_truncated' => true, '_note' => 'metadata exceeded ' . self::MAX_METADATA_BYTES . ' bytes'];
        }

        return $clean;
    }

    private function redact(array $data): array
    {
        $clean = [];
        foreach ($data as $key => $value) {
            if ($this->isSensitiveKey((string) $key)) {
                $clean[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $clean[$key] = $this->redact($value);
            } elseif (is_string($value)) {
                $clean[$key] = $this->truncate($value, self::MAX_STRING_LEN);
            } elseif (is_scalar($value) || is_null($value)) {
                $clean[$key] = $value;
            } else {
                $clean[$key] = '[unsupported]';
            }
        }
        return $clean;
    }

    private function isSensitiveKey(string $key): bool
    {
        $k = strtolower($key);
        foreach (self::SENSITIVE_KEYS as $needle) {
            if (str_contains($k, $needle)) {
                return true;
            }
        }
        return false;
    }

    private function truncate(string $value, int $max): string
    {
        return strlen($value) > $max ? substr($value, 0, $max) . '…' : $value;
    }

    /**
     * Best-effort, non-invasive entity inference from route metadata only (no DB
     * lookup, no body). entity_type = the segment after `admin.` in the route name;
     * entity_id = a numeric `id` route param. Null when not determinable.
     *
     * @return array{0: ?string, 1: ?int}
     */
    private function inferEntity(Request $request): array
    {
        $route = $request->route();
        if (!$route) {
            return [null, null];
        }

        $name = method_exists($route, 'getName') ? $route->getName() : null;
        $type = null;
        if (is_string($name) && str_starts_with($name, 'admin.')) {
            $parts = explode('.', $name);
            $type = $parts[1] ?? null;
        }

        $params = method_exists($route, 'parameters') ? $route->parameters() : [];
        $rawId = $params['id'] ?? null;
        $id = is_numeric($rawId) ? (int) $rawId : null;

        return [$type, $id];
    }

    /** Core writer. Never throws; on failure falls back to the file channel. */
    private function write(array $attributes, ?object $admin, ?Request $request): void
    {
        try {
            $base = [
                'admin_id'       => $admin->id ?? null,
                'admin_username' => $admin->username ?? null,
                'method'         => $request ? $request->method() : 'CLI',
                'path'           => $request ? $request->path() : null,
                'route_name'     => $request && $request->route() && method_exists($request->route(), 'getName')
                    ? $request->route()->getName() : null,
                'ip'             => $request ? $request->ip() : null,
                'user_agent'     => $request ? $this->truncate((string) $request->userAgent(), self::MAX_UA_LEN) : null,
                'correlation_id' => $request ? $request->headers->get('X-Request-Id') : null,
            ];

            AdminAuditLog::create(array_merge($base, $attributes));
        } catch (\Throwable $e) {
            $this->fallback($attributes, $admin);
        }
    }

    /** File-channel fallback so a DB failure never loses the trail (and never breaks the request). */
    private function fallback(array $attributes, ?object $admin): void
    {
        try {
            Log::build([
                'driver' => 'daily',
                'path'   => storage_path('logs/admin-audit.log'),
                'days'   => 30,
            ])->warning('admin.audit.db_write_failed', array_merge(
                ['admin_id' => $admin->id ?? null],
                array_intersect_key($attributes, array_flip(['action', 'result', 'permission_slug', 'route_name']))
            ));
        } catch (\Throwable $e) {
            // Auditing must never break an admin action.
        }
    }
}
