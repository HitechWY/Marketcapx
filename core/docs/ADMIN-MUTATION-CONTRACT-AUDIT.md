# Admin Mutation Contract Audit (F8b)

**Status:** read-only audit. Documents the contract of every high-risk admin mutation **as it exists
today**. No mutation method was changed; no success path was executed outside test rollback.
Companions: `RBAC-PERMISSION-AUDIT.md` (F8a), `ADMIN-AUDIT-LOG-READINESS.md` (F8c),
`ADMIN-SENSITIVE-ACTIONS-FREEZE-LIST.md`. All routes below live in `routes/admin.php` under the
single `Route::middleware('admin')` group and are **frozen Blade** (not Inertia).

## Facts that hold for EVERY admin mutation (stated once)

- **CSRF:** protected. All admin POSTs run through the `web` group's CSRF verification. The
  `preventRequestForgery` except-list (`bootstrap/app.php` line 113–115) is `user/deposit`, `ipn*`,
  `test/ipn`, `payment/initiate`, `sandbox/payment/initiate` — **no admin path is excepted**.
- **Permission check:** **admin guard only** (`RedirectIfNotAdmin`). No per-route/controller
  authorization exists (see F8a). Every row below would read "admin-auth only" — not repeated per row.
- **FormRequest:** **none.** No admin mutation uses a dedicated FormRequest class; validation, where
  present, is inline `$request->validate([...])`.
- **Audit-log side effect:** **`AdminAuditLog` metadata only** (admin_id, username, method, path,
  route, ip, status, timestamp) to `storage/logs/admin-audit.log`. **No** target entity id, **no**
  before/after values, **no** user-agent (see F8c). Not repeated per row.
- **Risk legend:** 🔴 critical · 🟠 high · 🟡 medium · 🟢 lower.

---

## 1. User / Merchant / Agent status (block/unblock)

`ManageGuardController@status` (lines 419–436). Routes (one per guard via the shared
`$manageGuardRoutes` closure, applied to `users`/`agents`/`merchants`):
`admin.{guard}.status` · `POST /admin/{guard}/status/{id}`.

- **Inputs:** `{id}` (route); `reason` (body, **required|string|max:255** — but only validated when
  the account is currently active, i.e. on ban; unban path validates nothing).
- **Effect:** toggles `status` between `USER_ACTIVE`/`USER_BAN`; sets/clears `ban_reason`. **1 row.**
- **Balance impact:** none. **Notify/email:** none. **Transaction/atomicity:** none (single `save()`).
- **Idempotency risk:** 🟡 it's a toggle — a duplicate submit flips state back; no idempotency key.
- **Required tests before migration:** ban requires reason; unban clears reason; toggle correctness;
  authorization (post-RBAC); audit record with target id + before/after status.
- **Risk:** 🟠 (account lockout; no audit of who/why beyond `ban_reason`).

## 2. KYC approve / reject

`ManageGuardController@kycApprove` (212–222) and `@kycReject` (224–240). Routes per guard:
`admin.{guard}.kyc.approve` · `POST /admin/{guard}/kyc-approve/{id}`;
`admin.{guard}.kyc.reject` · `POST /admin/{guard}/kyc-reject/{id}`.

- **approve inputs:** `{id}` only. **No validation.** Sets `kv = KYC_VERIFIED`, `save()`,
  `notify(KYC_APPROVE)`. **1 row.**
- **reject inputs:** `{id}`; `reason` (**required**). Sets `kv = KYC_UNVERIFIED` +
  `kyc_rejection_reason`, `save()`, `notify(KYC_REJECT, reason)`. **1 row.**
- **Balance impact:** none. **Transaction/atomicity:** none. **Notify:** yes (user template).
- **Idempotency risk:** 🟠 approve is unconditional (no status pre-check) — re-approve re-fires the
  notification and re-stamps state; no idempotency key.
- **Required tests before migration:** approve sets verified + notifies once; reject requires reason
  + stores reason; no re-approve side effects; authorization (post-RBAC); audit with target id +
  before/after `kv`.
- **Risk:** 🔴 (KYC gate to financial onboarding; approve has no validation/guard/atomic audit).

## 3. Account edit (incl. KYC data wipe) & impersonation

- **Update** — `ManageGuardController@update` (242–317). `admin.{guard}.update` ·
  `POST /admin/{guard}/update/{id}`. Inputs: `firstname,lastname,email,country` (validated),
  plus `mobile,address,city,state,zip,ev,sv,ts,kv`. **Notably: when `kv` is falsy it sets `kv=0`,
  deletes KYC files from disk via `fileManager()->removeFile(...)`, and nulls `kyc_data`** — a
  destructive PII action folded into a profile update. No transaction. **Risk:** 🟠.
- **Impersonation (login-as)** — `ManageGuardController@login` (405–417). `admin.{guard}.login` ·
  **`GET /admin/{guard}/login/{id}`**. Logs the admin in **as** the target (`Auth::loginUsingId`).
  No validation, no confirmation, **and a GET route** (no CSRF on GET; though admin-guarded).
  Not captured by `AdminAuditLog` (which only logs POST/PUT/PATCH/DELETE). **Risk:** 🔴
  (full account takeover, currently *unaudited* because it's a GET).

## 4. Manual balance adjustment

`ManageGuardController@addSubBalance` (319–403). Routes per guard:
`admin.{guard}.add.sub.balance` · `POST /admin/{guard}/add-sub-balance/{id}`.

- **Inputs:** `{id}`; `amount` (**required|numeric|gt:0**), `act` (**required|in:add,sub**),
  `remark` (**required|string|max:255**), `wallet_id` (body, **not validated** — looked up scoped to
  the user).
- **Effect:** **atomic (Phase 5)** — `DB::transaction` + `lockForUpdate` on the wallet, re-checks
  sufficient balance under the lock for `sub`, writes a `Transaction` ledger row (`balance_add` /
  `balance_subtract`) with `post_balance`. **2 rows** (wallet + transaction). **Notify:** BAL_ADD/SUB.
- **Idempotency risk:** 🔴 **no idempotency/duplicate-submit guard** — resubmitting the form adds/
  subtracts again. The ledger row is the only durable trace; there is no admin-attributed audit
  (the Transaction has no admin_id).
- **Required tests before migration:** amount/act/remark validation; insufficient-balance rejection
  under lock; ledger correctness; **duplicate-submit protection**; authorization (post-RBAC); audit
  with admin_id + before/after balance.
- **Risk:** 🔴 (direct money creation/destruction; atomic but not idempotent, not admin-audited).

## 5. Deposit approve / reject

`DepositController` — `admin.deposit.approve` · `POST /admin/deposit/approve/{id}` (230–239);
`admin.deposit.reject` · `POST /admin/deposit/reject` (241–267).

- **approve inputs:** `{id}` only. Loads deposit `where status = PAYMENT_PENDING` then
  `firstOrFail()` → **status pre-check = idempotency guard** (re-approve ⇒ 404). Delegates to
  `PaymentController::userDataUpdate($deposit, true)` which **credits the wallet atomically (Phase
  3)** and writes the transaction ledger. **Notify:** yes. **Risk:** 🟠 (atomic + idempotent;
  no FormRequest; relies on shared `PaymentController`; no admin-attributed audit).
- **reject inputs:** `id` (**required|integer**), `message` (**required|string|max:255**). Status
  PENDING `firstOrFail`. Sets `admin_feedback` + `PAYMENT_REJECT`, `save()`, `notify(DEPOSIT_REJECT)`.
  No balance change → no transaction needed. Idempotent via status guard. **Risk:** 🟡.

## 6. Withdrawal approve / reject

`WithdrawalController` — `admin.withdraw.data.approve` · `POST /admin/withdraw/approve` (204–225);
`admin.withdraw.data.reject` · `POST /admin/withdraw/reject` (227–310).

- **approve inputs:** `id` (**required|integer**); `details` (body, **not validated**, stored to
  `admin_feedback`). Status PENDING `firstOrFail`; sets `PAYMENT_SUCCESS`; `notify(WITHDRAW_APPROVE)`.
  No wallet change on approve (funds were debited at request time). Idempotent via status guard.
  **Risk:** 🟠 (marks a payout authorized; the actual disbursement is external/manual).
- **reject inputs:** `id` (**required|integer**); `details` (unvalidated). **Atomic + idempotent
  (Phase 13)** — `DB::transaction` + `lockForUpdate`, re-check status under lock, **refund wallet**,
  `Transaction` ledger (`withdraw_reject`), notify after commit. **2 rows.** **Risk:** 🟡 (well-guarded;
  no FormRequest; no admin-attributed audit).

## 7. Escrow action (dispute resolution / fund movement)

`EscrowController` — `admin.escrow.action` · `POST /admin/escrow/action` (line 136);
`admin.escrow.message.reply` · `POST /admin/escrow/message` (134). The `action` endpoint releases/
refunds escrow funds. **Frozen and out of scope to inspect deeply** (escrow logic is off-limits this
sprint); documented here for completeness as 🔴 money-movement requiring atomicity + admin audit
before any migration. **Do not migrate without escrow-owner review.**

## 8. Support ticket reply / close / delete

`SupportTicketController` (+ `SupportTicketManager` trait) — `admin.ticket.reply` ·
`POST /admin/ticket/reply/{id}`; `admin.ticket.close` · `POST /admin/ticket/close/{id}`;
`admin.ticket.delete` · `POST /admin/ticket/delete/{id}`; download `GET /admin/ticket/download/{attachment_id}`.

- **reply:** creates a message (+ optional attachment upload). **close:** updates status. **delete:**
  **hard-deletes** the message row and its attachment files from disk. No transaction; validation in
  trait. **Risk:** reply/close 🟢; **delete 🟠** (destructive, no soft-delete, no audit of content).

## 9. Gateway / payment-method enable·disable·update (secrets-bearing)

| Action | Route | Method/URL | Notes |
|---|---|---|---|
| Automatic gateway update | `admin.gateway.automatic.update` | POST `/admin/gateway/automatic/update/{code}` | writes `gateway_parameters` JSON (**API keys/secrets**) 🔴 |
| Automatic gateway status/remove | `admin.gateway.automatic.status` / `.remove` | POST `/admin/gateway/automatic/{status\|remove}/{id}` | toggle / delete `GatewayCurrency` 🟠 |
| Manual gateway store/update/status | `admin.gateway.manual.store` / `.update` / `.status` | POST `/admin/gateway/manual/{new\|update/{id}\|status/{id}}` | method config 🟠 |
| Withdraw method store/update/status | `admin.withdraw.method.store` / `.update` / `.status` | POST `/admin/withdraw/method/{create\|edit/{id}\|status/{id}}` | payout method config 🟠 |
| Currency add/update/api-key | `admin.currency.add` / `.update` / `.api.update` | POST `/admin/currency/{add\|update\|api-key/update}` | **rate API keys** 🔴 |
| Transaction charges update | `admin.transaction.charges.update` | POST `/admin/transaction/charges/update` | affects money math 🟠 |

**Secrets caveat:** GET edit pages render existing secrets; mutation payloads carry secrets — must
never be serialized to Inertia props (see freeze list / F8d).

## 10. System settings (config / secrets / site assets)

`GeneralSettingController` — all POST, paired with GET pages:
`general-setting` (`generalUpdate`), `setting/system-configuration` (toggles KYC/email/SMS/push/2FA/
SSL/registration), `setting/qr-code/template`, `setting/social/credentials/update/{key?}` +
`/status/{key?}` (**OAuth client id/secret** 🔴), `setting/logo-icon`, `custom-css`, `sitemap`,
`robot`, `cookie`, `maintenance-mode`. Mostly inline validation; file/disk writes for logo/css/
sitemap/robot. **Risk:** config 🟠, socialite credentials 🔴.

## 11. Notification / email / SMS / push settings + templates (secrets-bearing)

`NotificationController` (prefix `notification`, names `admin.setting.notification.*`):
`global/email/update`, `global/sms/update`, `global/push/update`, `template/update/{type}/{id}`,
`email/setting` (**SMTP host/user/password** 🔴), `sms/setting` (**SMS API keys** 🔴),
`push/setting` (**push/FCM keys** 🔴), `email/test`, `sms/test`, `push/setting/upload`. **Risk:**
settings 🔴 (secrets); template body update 🟠 (stored content / potential HTML).

## 12. Content / CMS / language (file & content writes)

- **Frontend/CMS** — `FrontendController` + `PageBuilderController` (prefix `frontend`):
  `frontend-content/{key}`, `update-seo`, `remove/{id}`, `manage-pages` (save/update/delete),
  `manage-section/{id}` (+update), `manage-seo/{id}`, `templates` activate; plus top-level `seo`.
  Content/SEO writes; some delete. **Risk:** 🟡 (content), delete 🟠.
- **Language** — `LanguageController` (prefix `language`): `manage.store`, `manage.update`,
  `manage.delete`, `import.lang`, `store.key`, `update.key`, `delete.key` — **language file writes**.
  **Risk:** 🟠.
- **Category / Subscriber** — `admin.category.store` / `.status`; `admin.subscriber.send.email`
  (bulk email) / `.remove`. **Risk:** 🟡.
- **Notifications to accounts** — `admin.{guard}.notification.single` / `.all.send` (bulk outbound).
  **Risk:** 🟡 (outbound spam / cost).

## 13. System / code — high blast radius

- **Cron** — `CronConfigurationController` (prefix `cron`): `store`, `update`, `delete/{id}`,
  `schedule.store`, `schedule.status/{id}`, `schedule.pause/{id}` (**GET**), `schedule.log.resolved`,
  `log.flush`. **Risk:** 🔴 (scheduled execution).
- **Module** — `admin.module.update` (feature toggles). 🟠.
- **KYC setting** — `admin.kyc.setting.update` (KYC form definition). 🟠.
- **Extensions / Plugins** — `admin.extensions.update` / `.status`; `admin.plugins.upload` /
  `.update/{id}` / `.status` — **code/plugin upload (RCE-adjacent)**. **Risk:** 🔴.
- **System ops** — `SystemController`: `optimize` (GET), `optimize-clear` (GET),
  `system-update` (POST `update.process`), `update.log`. **Risk:** 🔴 (app/code update).

## 14. Private file / PII download (read, but sensitive)

`GET` downloads (not mutations, listed for migration safety): `admin.download.attachment`,
`admin.ticket.download/{attachment_id}`, `admin.profit.export.csv`,
`admin.setting.notification.push.download`, and KYC detail pages
`admin.{guard}.kyc.details` (render PII). Keep server-side; never expose raw storage paths in props.

---

## Cross-cutting findings

1. **No admin attribution in business data.** Money ledgers (`Transaction`) and state changes record
   the *target* but **never the acting admin**. Combined with metadata-only `AdminAuditLog` (no entity
   id), there is **no queryable "which admin approved deposit #X / adjusted balance #Y" trail.**
2. **Idempotency is uneven.** Deposit/withdraw approve/reject self-guard via a `status = PENDING`
   pre-check; **balance add/sub, KYC approve, and status toggle have no idempotency/duplicate-submit
   protection.**
3. **Atomicity is uneven.** Money paths that move balances are atomic (Phase 3/5/13). KYC approve,
   status, account update (incl. KYC-file deletion) are plain single/multi `save()` with no
   transaction.
4. **Validation is inline and partial.** No FormRequests; several inputs that reach persistence are
   unvalidated (`wallet_id`, withdraw/deposit `details`, account `update` mobile/address).
5. **Impersonation and several "ops" actions are GET** (`login/{id}`, cron `schedule.pause`, system
   `optimize`), so they are **not** captured by the POST-only `AdminAuditLog`.

## Required tests before any of these migrate (summary)

For each money/KYC/status mutation: validation rules; success path **inside DB rollback only**;
idempotency/duplicate-submit; atomicity (balance consistency under concurrency); authorization
(post-RBAC, allow + deny); audit record presence with admin_id + target id + before/after. For
secrets-bearing settings: assert **no secret is serialized to any Inertia prop** (extends the F7a
prop-exposure guard). See F8d for the safe endpoint design these tests target.
