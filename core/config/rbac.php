<?php

return [

    /*
    |--------------------------------------------------------------------------
    | RBAC enforcement mode
    |--------------------------------------------------------------------------
    |
    | false = allow-by-default (F9 default): an admin lacking a required permission
    |         is LOGGED ("would_deny") but still allowed through. Zero behavior change
    |         versus the legacy flat-admin panel.
    |
    | true  = deny-by-default: a missing permission returns HTTP 403.
    |         OWNER-GATED — do NOT enable until the checklist in
    |         docs/RBAC-DENY-BY-DEFAULT-READINESS.md is complete (all admins have
    |         roles, super-admin verified, route coverage confirmed, would-deny log
    |         reviewed). Rollback = set this back to false.
    |
    | Default is literal false; an optional RBAC_ENFORCE env var allows a controlled
    | rollout without code changes. It must stay false until owner approval.
    |
    */
    'enforce' => (bool) env('RBAC_ENFORCE', false),

];
