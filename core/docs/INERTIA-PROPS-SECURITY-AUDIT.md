# Inertia Props Security Audit (F7a)

**Verdict: CLEAN.** Every Inertia response added F1–F6 was inventoried. No password, remember
token, API secret, gateway credential, callback/webhook secret, raw KYC document path, raw private
storage path, 2FA stored secret, or cross-actor/admin-only data is exposed in any prop. No code
change was required; a regression guard (`InertiaPropExposureTest`) was added.

## Method
Enumerated all `inertia(...)` calls across controllers, the shared props in
`app/Http/Middleware/HandleInertiaRequests.php`, and the row mappers in
`app/Traits/BuildsPanelDashboard.php` + `app/Traits/BuildsAdminReadonly.php`. Cross-checked each
field name and source against the hard-requirement list.

## Shared props (`HandleInertiaRequests`)
`auth.user` = `{id, username, fullname, email, image, kv, ts, type}` per guard (USER/AGENT/MERCHANT/
ADMIN). `modules`, `nav` (server-resolved `route()` URLs only), `flash.notify`, `locale`, `appName`.
- `image` is resolved via `getImage(getFilePath(...))` — never a raw storage path.
- **Excluded:** password, remember_token, ver_code, `tsc` (2FA secret), `secret_api_key`,
  `kyc_data`. `kv`/`ts` are integer status flags only.
- Admin branch is gated on `auth('admin')->check()`; exposes the same safe field set.

## Per-area inventory (verdict)
| Area | Components | Sensitive fields excluded | Notes |
|------|-----------|---------------------------|-------|
| Public | Home, Page, PolicyPage, Blogs, BlogDetails, Contact, CookiePolicy | n/a | CMS content is admin-authored HTML rendered via `v-html` (ProseContent/ServiceSection/blog). `Contact.captcha` is server-rendered captcha HTML. Images server-resolved. |
| User | Dashboard, Transactions, Wallets, DepositHistory, WithdrawHistory, Profile, ChangePassword, TwoFactor, KycInfo, QrCode | password, remember_token, tsc, kyc_data(raw) | `TwoFactor.secret` is an **ephemeral** `GoogleAuthenticator::createSecret()` for the setup QR (matches Blade), not the stored `tsc`. `statusBadge` is admin-authored HTML (deposit/withdraw history). KYC file values are `encrypt(getFilePath('verify')/...)` route URLs. `WithdrawHistory.information` is the actor's own submitted data. |
| Merchant | Dashboard, Transactions, Wallets | secret_api_key, public_api_key | API-key page stays **Blade** (renders the secret) — never Inertia. |
| Agent | Dashboard, Transactions, Wallets, CommissionLog, DepositHistory | — | Same shapes as user; read-only. |
| Admin | Dashboard, GuardList, Deposits, Withdrawals, SupportTickets, Transactions, LoginHistory | password, remember_token, balance, kyc_data, gateway `detail`, withdraw_information, secret_api_key | Rows explicitly field-mapped; status rendered as `{label,tone}` chips (no raw HTML). Dashboard exposes aggregate counts + chart-endpoint URLs only. |
| Auth (F4) | ForgotPassword, CodeVerify, ResetPassword (user/merchant/agent) | — | Props are `submitUrl`/`loginUrl`/`requestUrl`/`email`/`token` (reset token is the recovery token, already in the GET URL) + `securePassword` bool. |

## `v-html` usage (all safe)
`PanelDepositHistory`/`WithdrawHistory` (`statusBadge` — admin-authored), `ContactForm` (server
captcha HTML), `Pagination` (Laravel-generated `«`/`»` entities), `ProseContent`/`ServiceSection`
(admin-authored CMS). No user-supplied string is rendered as HTML.

## Ziggy / route exposure
`resources/views/app.blade.php` uses `@routes('public')`; `config/ziggy.php` exposes only the
guest `public` group (home, contact, blogs, blog.details, pages, policy.pages, cookie.policy,
cookie.accept, lang, subscribe). No user/merchant/agent/admin, money, gateway, IPN/webhook, or
auth-mutation route names reach the client. All in-app links are server-resolved `route()` props.

## Outcome
- No prop removed (nothing unsafe found).
- Added `tests/Feature/InertiaPropExposureTest.php` asserting sensitive fields are absent from
  `auth.user` across all four guards, that the user dashboard exposes `kyc.status` but not raw
  `kyc.kyc_data`, and that admin list rows carry no password/remember_token/balance/kyc_data.

## Standing rules (for future Inertia work)
Never serialize: password, remember_token, ver_code, tsc, secret_api_key, public_api_key, kyc_data,
withdraw_information (except the actor's own), gateway detail/credentials, raw file/storage paths.
Always resolve files via `getImage()`/`encrypt(getFilePath())`/`route()`; keep secret-bearing pages
(merchant API key, admin settings) in Blade.
