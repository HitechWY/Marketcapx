# Inertia Migration — Completion Map (source of truth)

Status of every area after F0–F7. **Inertia** = migrated Vue page. **Blade** = retained (frozen or
deferred). Every migrated controller method kept its Blade view as a one-line rollback. Backend
money/KYC/gateway/IPN/checkout/escrow logic is unchanged throughout.

## Public (guest) — ✅ fully Inertia
Home, custom Pages, Policy pages, Cookie policy, Blogs list, Blog details, Contact.
(`SiteController@*` → `Public/*`.) POST flows (contact/subscribe/cookie-accept) unchanged.

## User (web guard)
| Inertia | Blade (frozen) |
|---------|----------------|
| Dashboard, Transactions, Wallets, Deposit History, Withdraw History, Profile, Change Password, 2FA (GET), KYC Info (GET), QR Code | Deposit, Withdraw, Transfer, Exchange, Money-Out, Make-Payment, Request-Money, Invoice, Voucher, Escrow forms; KYC submit; all POST mutations |

## Merchant
| Inertia | Blade (frozen) |
|---------|----------------|
| Dashboard, Transactions, Wallets | **API key page (renders secret)**, Withdraw flow + methods, Profile/Password/2FA/KYC forms |

## Agent
| Inertia | Blade (frozen) |
|---------|----------------|
| Dashboard, Transactions, Wallets, Commission Log, Add-Money History | Money-In, Withdraw + commission withdrawal, Deposit, Profile/Password/2FA/KYC forms |

## Auth
| Inertia | Blade (frozen) |
|---------|----------------|
| Forgot-password, Code-verify, Reset-password (user + merchant + agent), native POST | **All login + register (user/merchant/agent/admin)** and **admin login** — render `<x-captcha>`; deferred to avoid changing captcha logic |

## Admin
| Inertia (read-only) | Blade (frozen) |
|---------------------|----------------|
| Dashboard (+ charts via unchanged `admin.chart.*` JSON), Users/Agents/Merchants lists, Deposits list, Withdrawals list, Support Tickets list, Transaction report, Login history (+ by-IP) | All detail/KYC pages; ticket reply/close/delete; approve/reject (deposit/withdraw/KYC); status/balance mutations; gateway/payment-method/system/general/notification/cron/role/extension/plugin/frontend settings; notification-history + email-details (sensitive payloads); admin login |

## Frozen lists (do not migrate without a separate audited effort)
- **Money forms:** deposit, withdraw, transfer, exchange, money-in/out, make-payment, request-money, invoice, voucher, escrow, milestone — all guards. See `MERCHANT-SENSITIVE-FORMS-MIGRATION-AUDIT.md`, `AGENT-SENSITIVE-FORMS-MIGRATION-AUDIT.md`.
- **Admin sensitive actions:** see `ADMIN-SENSITIVE-ACTIONS-FREEZE-LIST.md` (approvals, settings, secrets, system/cron, roles, deletes).
- **KYC / upload / approval:** KYC submit (all guards) + admin KYC approve/reject + KYC document viewers.
- **Gateway / IPN / webhook / checkout / escrow:** untouched end-to-end (`Gateway\*`, `routes/ipn.php`, checkout templates, `work/escrow-atomicity`).

## Blade fallbacks retained (rollback)
Every migrated GET method keeps a one-line Blade-view rollback comment; the corresponding Blade
views are all still present (verified by `SensitiveRoutesRemainBladeTest` + per-phase scans). No
Blade view was deleted in any phase.

## Client route exposure
Ziggy serializes only the `public` group (`config/ziggy.php`); all in-app links are server-resolved
`route()` props. No panel/money/gateway/auth-mutation route names reach the client
(`ZiggyExposureTest`). Route count steady at **798** across F0–F7.

## Security posture
`InertiaPropExposureTest` + `INERTIA-PROPS-SECURITY-AUDIT.md`: no password/remember_token/2FA-secret/
api-secret/gateway-credential/raw-KYC-path/cross-actor data in any prop.

## Remaining high-risk phases & recommended order
1. **RBAC + admin mutation endpoints** — implement role/permission enforcement (draft only today),
   extract admin inline mutations (approve/reject/status/balance) into dedicated audited endpoints,
   THEN migrate admin detail/approval pages to Inertia.
2. **Sanitized notification/email viewer** — safe read-only viewer before migrating notification
   history / email details.
3. **In-SPA captcha strategy** — verify reCAPTCHA/custom-captcha rendering in Vue, then migrate
   login + register (all guards) and admin login.
4. **Money forms (highest risk)** — only after full money-flow regression coverage; may remain Blade
   indefinitely by policy. Gateway/IPN/webhook/checkout/escrow stay frozen.
