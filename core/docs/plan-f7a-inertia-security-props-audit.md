# F7a — Inertia Security Props Audit (Plan)

Audit every Inertia response (F1–F6) for data exposure; remove unsafe fields if any; add a
regression guard test. No business-logic changes.

## Method
Enumerate all `inertia(...)` calls + `HandleInertiaRequests` shared props + the prop-builder
traits (`BuildsPanelDashboard`, `BuildsAdminReadonly`) and their row mappers. For each, record
component → controller@method → prop keys, and flag any sensitive field (password, remember_token,
ver_code, tsc/2FA secret, secret/public api key, kyc_data, withdraw_information, gateway detail,
callback/webhook secrets, raw file/storage paths, raw HTML).

## Hard requirements (must all hold)
No password / remember_token / API secret / gateway credential / callback secret / raw KYC path /
raw private storage path in any prop; `withdraw_information` only where already the actor's own data;
no cross-actor or admin-only data leaked to user/merchant/agent surfaces.

## Deliverables
- `core/docs/INERTIA-PROPS-SECURITY-AUDIT.md` (full inventory + verdicts).
- `tests/Feature/InertiaPropExposureTest.php` (assert sensitive fields absent across guards).

## Allowed changes
Remove unnecessary prop fields if safe; add absence assertions. No logic changes. Gates standard;
route count 798.
