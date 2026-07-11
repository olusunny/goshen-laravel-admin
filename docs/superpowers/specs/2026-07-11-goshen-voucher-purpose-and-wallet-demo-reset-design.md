# Goshen voucher purpose and wallet demo reset

## Goal

Give authorised Goshen administrators a clear, safe way to issue two kinds of voucher:

- **For Payments**: redeemable by the existing eligible Goshen payment flows.
- **Wallet Funding**: redeemable only through a member's wallet top-up flow, where it credits that member's own wallet.

The distinction must be enforced by the Laravel API, so neither the web application nor Flutter can use a voucher for the wrong purpose. The same purpose selection must be available to authorised Flutter Control Hub users.

Before the feature is released, reset the current demo voucher and wallet financial state on both staging and production. The owner has confirmed that both environments contain demo-only data and no real-money wallet or voucher records.

## Scope and non-goals

Included:

- Explicit voucher-purpose persistence, display, filtering, and API payloads.
- Admin and Flutter Control Hub voucher generation with a required purpose selection.
- Purpose validation at every voucher redemption boundary.
- Voucher table selection, safe deletion, and bulk operations.
- An auditable, guarded command for the one-off demo voucher/wallet cleanup on both environments.
- Automated regression coverage.

Excluded:

- Changes to ordinary Stripe payments, bookings, attendees, tickets, receipts, Triumphant IDs, member accounts, or wallet security credentials.
- Deleting or altering any future used voucher. After the reset, used vouchers will remain immutable audit evidence.
- Introducing partial payments, complimentary bookings, instalments, or a new payment gateway.

## Data model

Add a non-null `purpose` column to `goshen_vouchers` with two application constants:

- `payments` (displayed as **For Payments**)
- `wallet_funding` (displayed as **Wallet Funding**)

The migration defaults existing rows to `payments`, preserving the requested compatibility for previously issued vouchers. `purpose` is set once at generation time and cannot be edited later. It is also included in voucher API responses and shown as a badge/filter in the Filament table.

`event_id` remains meaningful for payment vouchers. Wallet-funding vouchers are created without an event restriction, because their only valid use is adding their stated currency and amount to a wallet.

## Redemption rules

`redeemForBooking()` and all callers that settle a Goshen payment accept only `payments` vouchers. `redeemForWalletTopUp()` accepts only `wallet_funding` vouchers.

The checks occur inside the same database transaction and row locks already used for redemption, before any wallet balance, ledger entry, payment transaction, booking, or voucher-use counter is changed. A mismatch returns a clear validation error and leaves all records unchanged.

Wallet redemption continues to create the normal paid `voucher_top_up` wallet ledger entry, update the wallet balance atomically, create a `goshen_voucher_usages` audit row, and exhaust the voucher when the last permitted use is consumed. The member may redeem only into their own authenticated wallet.

## Admin and Flutter controls

### Laravel admin

The existing **Generate vouchers** action gains a required **Purpose** select. It defaults to **For Payments**, so current administrator behaviour remains familiar. The form adapts its explanatory text and event field for the selected purpose.

The voucher table gains:

- a Purpose column and filter;
- row selection, including select-all for the current filtered result set;
- a per-row Delete action for unused vouchers only;
- bulk Delete for selected unused vouchers; and
- bulk Void for selected unused vouchers.

Used vouchers cannot be deleted or voided. Bulk actions report which records were skipped because they have usage history. This retains the wallet/payment audit trail while still giving administrators efficient cleanup for unused codes.

The existing resource permission remains the authority for all web actions. Only administrators assigned that permission can list, generate, delete, or bulk-manage vouchers.

### Flutter Control Hub

The existing Goshen voucher management capability (`canManageGoshenVouchers`) remains the authority for the UI. Its generation form and request payload gain the same required Purpose selection and labels as the web admin. The member wallet screen continues to offer voucher top-up; it receives server-side rejection if a payment voucher is entered.

## Demo-data reset

Create a production-safe Artisan command specifically for this reset. It requires an explicit confirmation token and reports the affected counts before and after execution. It is not exposed through a web route or an admin button.

For the confirmed demo environments, the command will execute transactionally in dependency-safe order:

1. Delete Goshen voucher usages and all Goshen vouchers.
2. Remove Goshen wallet financial activity, including wallet ledger entries, withdrawal requests, saved automatic top-up plans, and wallet goals that contain demo funding targets.
3. Set every retained `goshen_wallets` balance to zero and clear wallet goal-summary amounts derived from the deleted data.

It preserves mobile users, web admins, wallet-to-user links, wallet security/PIN state, Triumphant IDs, bookings, tickets, non-wallet payments, and Stripe customer records. No external Stripe object is deleted by the command.

The command will be verified against the test database, run on staging, verified, then run on production and verified. Its output is limited to counts and identifiers; voucher codes, secrets, and credentials are never logged.

## Integration order

The completed `feature/paid-admin-ticket-issuance` work adds another voucher payment consumer. Apply the purpose enforcement to that branch before it is consolidated into `main`, so its payment path also rejects wallet-funding vouchers. This avoids shipping two divergent voucher contracts.

## Verification

Automated tests will prove:

- legacy vouchers default to `payments`;
- each creation channel persists and returns the selected purpose;
- a wallet-funding voucher credits a wallet and cannot settle a booking;
- a payment voucher settles an eligible payment and cannot credit a wallet;
- invalid, expired, exhausted, wrong-currency, and purpose-mismatched attempts change no state;
- permissions block unauthorised web and Flutter Control Hub users;
- used vouchers are not deletable or voidable, including bulk actions;
- the reset command removes only the stated demo financial records and leaves members, wallets, bookings, tickets, and non-wallet payments intact.

Code quality checks will include focused Laravel tests, Flutter analysis/tests for the modified client contract, PHP linting, formatting, migration checks, and a live post-deploy verification on staging and production.
