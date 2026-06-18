# Model / Eloquent Audit (B7)

Audit of the 52 models in `core/app/Models`. This sprint applies only **safe,
non-breaking** changes (hidden auth secrets, with tests) and documents the rest as
recommendations — no schema changes and no money-behaviour changes.

## Changes applied in B7

| Model | Change | Why safe |
|-------|--------|----------|
| `Agent` | add `$hidden = ['password','remember_token']` | Auth model that had **no** `$hidden`. `$hidden` only affects array/JSON output; the hasher reads `->password` directly. Mirrors `Admin`. |
| `Merchant` | add `$hidden = ['password','remember_token','secret_api_key']` | Same as Agent, plus the merchant's **HMAC signing key**. Verified `secret_api_key` is read only via direct Blade access (`merchant()->secret_api_key`) and is serialised by **no** API, so hiding it from JSON cannot break a response. |

Covered by `tests/Unit/ModelHiddenAttributesTest.php` (DB-free): the secrets are
absent from `toArray()` while direct property access still returns them.

Baseline for reference: `User` hides `password, remember_token, ver_code, balance,
kyc_data`; `Admin` hides `password, remember_token`. Agent/Merchant previously
hid nothing.

## Recommended (NOT changed — needs API-response verification first)

These are likely-good but could change an existing API payload, so they are left
for a verified follow-up rather than applied blind:

- **Agent/Merchant**: also hide `ver_code` (OTP) and `kyc_data` (PII), as `User`
  does. `ver_code` is almost certainly safe; `kyc_data` must first be confirmed not
  to be read back from a serialised Agent/Merchant model in the mobile API
  (the user/agent/merchant KYC-view endpoints).
- **Password-reset models** (`AgentPasswordReset`, `MerchantResetPassword`,
  `AdminPasswordReset`): hide `token`. Server-side only today, so low risk, but
  confirm no flow serialises them.
- **2FA secret** (`ts` column on users/agents/merchants): consider hiding — it is a
  TOTP secret. Not currently hidden on `User` either; treat as its own slice.

## Other observations (documented, not actioned)

- **Mass assignment**: `Agent`, `Merchant` (and others) use `protected $guarded = []`,
  so every column is mass-assignable. Balance/status/kv/api-key columns are
  therefore writable if a request ever reaches `fill()`/`update($request->all())`.
  Controllers currently assign fields explicitly, so this is a latent risk, not an
  active bug. Proper fix = explicit `$fillable` per model — a behaviour-review
  change, deferred (could break existing mass-assign call sites).
- **Casts**: money columns (`balance`, `amount`, `charge`, …) are stored as DB
  decimals and read as strings/floats; the code consistently uses `getAmount()` /
  `showAmount()` for formatting. Adding a `decimal:N` cast would change the
  returned type and risks money-formatting regressions, so casts on money fields
  are intentionally **not** added here. `kyc_data`/`address` are already cast to
  `object` on the auth models.
- **N+1**: the relationship definitions eager-scope currency
  (`wallets()->with('currency')`), which mitigates the common wallet-list N+1. Admin
  list screens that iterate `->statusBadge`/relations should be spot-checked under a
  query profiler in a credentialed environment — not reproducible without seeded
  accounts.

## Scope guarantees
No schema/migration. No money-behaviour change. No relationship/scope semantics
changed. Only additive `$hidden` arrays + a DB-free test + this doc.
