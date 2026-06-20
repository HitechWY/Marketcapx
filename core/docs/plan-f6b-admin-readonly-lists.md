# F6b — Admin Read-Only Lists (Plan, as shipped)

Migrate read-only admin LIST pages to Inertia; no mutation actions migrated.

## Pages
- Users/Agents/Merchants managers (`ManageGuardController` 10 list scopes) → shared
  `Admin/GuardList.vue` (guard prop). Columns: User, Email·Mobile, Country, Joined, Action
  (Details + KYC-data link on kyc-pending).
- Deposits (`DepositController` list/pending/approved/rejected/successful/initiated) → `Admin/Deposits.vue`
  (+ summary cards on the "all" view).
- Withdrawals (`WithdrawalController` pending/approved/rejected/all) → `Admin/Withdrawals.vue` (+ summary).
- Support tickets (`SupportTicketController` tickets/pending/closed/answered) → `Admin/SupportTickets.vue`.

## Shared pieces
`Traits/BuildsAdminReadonly` (status→{label,tone} chips + currency/userType options).
`Components/Admin/{AdminTable,AdminFilterBar,AdminStatusBadge}.vue`; reuse `Public/Pagination`.

## Rules / gates
Rows explicitly mapped — NO password/remember_token/balance/kyc_data/gateway-`detail`/
`withdraw_information`/`secret_api_key`. Status rendered as tone chips (no raw admin badge HTML).
Filters/pagination/query params preserved (`appends(request()->all())`; `user_type` echo lowercased
to match the select). "Details" → server URLs to the unchanged Blade detail pages. Detail/KYC/
ban/balance/approve/reject/ticket-reply stay Blade. Blade list views kept as one-line rollback.
Test `AdminReadonlyListsTest` (6). Route count 798.
