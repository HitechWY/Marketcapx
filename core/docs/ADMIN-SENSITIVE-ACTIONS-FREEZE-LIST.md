# Admin Panel — Sensitive Actions Freeze List (F5)

**Status:** docs-only. These admin routes/actions are **FROZEN** — do NOT migrate to Inertia and
do NOT modify in any frontend phase. Money, KYC approvals, secrets, config, code/CMS writes, and
dangerous system operations. Companion: `ADMIN-READONLY-INERTIA-MIGRATION-PLAN.md`.

## 🔴 Money movement / balance mutation
- `admin.users|agents|merchants.add.sub.balance` — direct balance adjustment.
- `admin.deposit.approve` / `admin.deposit.reject` — credits/rejects deposits.
- `admin.withdraw.data.approve` / `admin.withdraw.data.reject` — pays out/rejects withdrawals.
- `admin.escrow.action` — escrow dispute resolution (releases/refunds funds).
- `admin.transaction.charges.update` — transaction charge config (affects money math).

## 🔴 KYC approval / PII
- `admin.users|agents|merchants.kyc.approve` / `.kyc.reject` — KYC verification decisions (hard-stop).
- 🟡 `admin.users|agents|merchants.kyc.details/{id}` — renders KYC documents/PII (read-only but sensitive; curate before any future exposure).
- `admin.kyc.setting` / `.kyc.setting.update` — KYC form definitions.

## 🔴 Account / impersonation / status
- `admin.users|agents|merchants.login/{id}` — **logs in AS the user** (impersonation).
- `admin.*.status` / `admin.*.update` — ban/activate/edit accounts.
- `admin.*.notification.single|all|all.send` — outbound notifications.
- `admin.profile.update`, `admin.password.update` — admin's own account.

## 🔴 Secrets-bearing settings (GET pages render secrets — never serialize to props)
- `admin.setting.system`, `admin.setting.general` / update — core config.
- `admin.setting.system.configuration`, `admin.setting.socialite.credentials` (+update/status) — **OAuth client IDs/secrets**.
- `admin.setting.notification.email` (SMTP host/user/**password**), `...sms` (SMS **API keys**), `...push` (**push/FCM keys**) + their `*.test` actions.
- `admin.setting.notification.global.email|sms|push` + `templates`/`template.update`.
- `admin.gateway.automatic.*` (edit/update/status/remove) — **payment gateway API keys/secrets**.
- `admin.gateway.manual.*` (create/store/edit/update/status) — manual method config.
- `admin.currency.add|update|api.update` — currency + **rate API keys**.
- `admin.withdraw.method.*` — payout method config.

## 🔴 System / code / CMS — high blast radius
- `admin.system.optimize`, `.optimize.clear`, `.update`, `.update.process`, `.update.log` — cache/app update ops.
- `admin.cron.*` — cron jobs & schedules (note: public `/cron` token risk tracked separately).
- `admin.module.update` — feature module toggles.
- `admin.language.*` writes (manage/store/update/delete/import/key ops) — language file writes.
- `admin.extensions.*`, `admin.plugins.*` (upload/update/status) — **code/plugin uploads** (RCE-adjacent).
- `admin.frontend.*` + `admin.frontend.manage.pages*` / page-builder / SEO — CMS content writes.
- `admin.setting.logo.icon`, `.custom.css`, `.sitemap`, `.robot`, `.cookie`, `.qr.code.template`, `.maintenance.mode` — site config/asset writes.
- `admin.category.store|status`, `admin.subscriber.send.email|remove`, `admin.ticket.reply|close|delete`.

## ⚪ Out of scope (not page migrations)
- Admin auth: `admin.login` (Blade, captcha — see F4d), `admin.password.*` recovery.
- Binary/file downloads: `admin.download.attachment`, `admin.ticket.download`, `admin.profit.export.csv`, `admin.setting.notification.push.download`.
- AJAX/JSON endpoints: chart endpoints, `admin.*.segment.count`, `admin.escrow.message.get`, slug checks.

## Bottom line
Only **read-only list/detail/report/dashboard** GET pages (🟢 in the migration plan) are ever
eligible for Inertia, and only after an `admin`-guard shared-props layer that is provably
secret-free. Everything in this file stays Blade.
