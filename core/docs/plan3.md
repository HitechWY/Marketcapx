# F1c-3 — Contact Page + Native POST Form (Plan 3)

**Branch:** `frontend/inertia-public-contact` (from `master` after Plan 2 merge)

## Scope
- `contact` — `GET /contact` → `SiteController@contact` migrated to Inertia.
- `POST /contact` → `SiteController@contactSubmit` **UNCHANGED** (backend untouched).

## GET /contact contract (read-only, migrate)
Renders contact info (`getContent('contact_us.content')`: title, heading, address, email_address,
contact_number), the form, and any `secs` sections. Logged-in users (`userGuard()`) get name/email
prefilled + readonly.

## POST /contact contract (PRESERVE EXACTLY — do not change backend)
- **Action/method:** `POST` to `/contact` (form `action = route('contact')` — same URL; POST route unnamed).
- **Inputs:** `name`, `email`, `subject`, `message`, hidden `_token`. Captcha (when enabled): custom →
  `captcha` + `captcha_secret`; reCAPTCHA → `g-recaptcha-response`.
- **CSRF:** hidden `_token` (read from `<meta name="csrf-token">` / passed prop). Web-group CSRF on.
- **Captcha:** server-rendered HTML from `<x-captcha/>` (`partials/captcha.blade.php`) passed as a prop
  and `v-html`-injected. **Currently inactive** (custom + reCAPTCHA both off) → empty slot. If reCAPTCHA
  is later enabled its `api.js` must execute (Inertia `<Head>` injection) — STOP-check, not needed now.
- **Validation:** name required, email required, subject required|string|max:255, message required.
- **DB writes:** creates SupportTicket + AdminNotification + SupportMessage (no email).
- **Success:** redirect to `ticket.view` with flash success (lands on still-Blade ticket page).
- **Approach:** **native HTML `<form method="POST" :action="route('contact')">`** (NOT Inertia useForm /
  fetch). Native full-page POST → `contactSubmit()` → redirect. Byte-identical contract.

## Vue pages/components to create
- `resources/js/Pages/Public/Contact.vue` (info tiles + `ContactForm` + `SectionRenderer`)
- `resources/js/Components/Public/ContactForm.vue` (native form + captcha v-html + prefill/readonly)
- Extend `resources/js/types/public.ts` (Contact props).

## Controller change
`contact()` returns `inertia('Public/Contact', { seo, content, user, captcha (HTML), sections })`;
original `view(...)` kept as commented rollback. **`contactSubmit()` not touched.**

## Tests
- **Markup contract test (Feature):** GET `/contact` → assert response has `method="post"`,
  `action=".../contact"`, an `name="_token"`, and `name/email/subject/message` inputs; captcha slot
  present-or-empty matching settings.
- **Validation-failure test (non-mutating):** POST `/contact` with missing fields → assert redirect
  back + errors; assert `SupportTicket::count()` unchanged (validation fails before any write).
- **Success-path test (rollback only):** `use RefreshDatabase` (or `DatabaseTransactions`); POST valid
  payload (captcha disabled) → assert SupportTicket + AdminNotification + SupportMessage created +
  redirect to `ticket.view`; rolled back automatically. **Never** run a successful POST against dev DB
  outside a test transaction.

## Fallback Blade path (unchanged)
`templates/basic/contact.blade.php` retained untouched.

## Rollback
Restore commented `view('Template::contact', …)` in `contact()`. Per branch: revert branch.

## Tests / gates
- build / typecheck / composer validate / PHP lint / PHPUnit (incl. new contact tests) / route:list 798.
- Smoke: `/` Inertia; `/contact` Inertia + form renders; `/announces` Inertia; policy/static Inertia;
  `/user/login`, `/admin` still Blade; dashboards 302.
- Visual QA (Playwright): desktop 1440 + mobile 390, dark/light, no horizontal overflow, no console errors.
- Ziggy scan clean. No payment/wallet/KYC/admin/checkout change. No non-test DB mutation. No secrets.

## Auto-merge criteria
All gates green (incl. contact tests + visual QA); only F1c-3 files changed; `contactSubmit()` untouched;
form contract byte-identical; Blade fallback intact; no high-risk Ziggy exposure.

## Stop conditions
Captcha cannot be preserved; success test would require non-test DB mutation; form contract would change;
`contactSubmit()` would need business changes; visual QA unacceptable; Ziggy exposure; tests fail unfixably.
