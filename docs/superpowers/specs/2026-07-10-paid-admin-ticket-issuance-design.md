# Paid Admin Ticket Issuance Design

## Purpose

Replace the complimentary admin ticket path with a paid issuance flow that preserves the same booking, payment, reporting, ticket, and audit invariants used by Goshen Retreat registrations in the Flutter app.

An authorized web admin can issue one ticket to a selected member only after settling the full listed amount through one of two methods:

- **Voucher**: an existing valid Goshen voucher code.
- **Wallet**: the issuing web admin's own linked Goshen wallet.

No complimentary, waived, zero-total, or zero-payment ticket issuance is permitted by this flow.

## Existing Behaviour and Constraints

- Flutter already has a manager-assisted member-registration screen. It creates or selects a member, requires a voucher code, and calls the normal Goshen booking endpoint with the selected member as beneficiary.
- The existing voucher service records a payment transaction and voucher usage, then settles the booking through `PaymentSettlementService`.
- The existing wallet flow records a wallet debit ledger entry and payment transaction, then settles the booking through `PaymentSettlementService`.
- Goshen sales and gateway summaries are derived from normal booking totals and paid payment transactions. A paid admin-issued ticket must therefore have a non-zero booking total and a settled payment record.
- A Goshen wallet belongs to one `MobileUser`, not to a web `User`.
- Existing mobile and web accounts are linked by normalized email through `MergedAccountCredentialService`. The current service synchronizes credentials when both records exist but does not create a missing `MobileUser`.
- A Triumphant ID belongs only to `MobileUser`. Its sequence and formatted value are unique, so one linked web/mobile identity must result in exactly one mobile record and one Triumphant ID.

## Account and Wallet Provisioning

### Canonical identity

The normalized email address is the identity key between the web-admin `User` and `MobileUser` records.

When a web user is created or saved with a usable email and password:

1. Resolve an existing `MobileUser` by normalized email.
2. If one exists and is active, keep that record and synchronize the password hash through the existing credential service.
3. If none exists, create exactly one linked `MobileUser` with the web user's name, normalized email, and the same password hash. Mark the linked record verified and active, and set a dedicated linkage login type.
4. Let the existing mobile-user lifecycle assign one Triumphant ID. No second mobile record is created for the same email.

Every newly created `MobileUser`, regardless of whether it originated from Flutter, the web application, or manager-assisted member creation, receives a zero-balance Goshen wallet immediately. A one-time idempotent backfill provisions wallets for all existing non-deleted mobile users that do not yet have one.

The solution must preserve existing mobile users and their Triumphant IDs. It must never merge records by deleting or reassigning an existing mobile account merely because a matching web user exists.

## Admin Ticket Form and Authorization

The existing `goshen_ticket.issue` permission remains the create-only permission. It can be assigned to any web admin role. It does not grant access to the ticket index, ticket detail, update, or delete actions.

The ticket-issuance page includes:

- recipient member;
- published retreat edition;
- active ticket type belonging to that edition;
- required issuance reason;
- payment method (`voucher` or `wallet`);
- required voucher code only when voucher is selected;
- the full ticket amount and currency; and
- for wallet, the resolved issuing admin's linked wallet identity and available balance, followed by explicit confirmation.

Wallet payment is available only if the authenticated web admin resolves to one active, verified, non-blocked `MobileUser` with the same normalized email and an enabled wallet. The form must not accept a selected payer or arbitrary member wallet. If the linked account cannot be resolved, is blocked, or is under a wallet security reset restriction, wallet payment is rejected with a clear message.

Voucher codes are never stored in plaintext. Existing voucher verification and redemption stores only the safe suffix and usage references.

## Paid Issuance Lifecycle

All state changes occur in a database transaction.

1. Lock and validate the selected recipient, event, and ticket type. Reject blocked/deleted recipients, unpublished events, inactive ticket types, and a duplicate member/event/ticket-type registration.
2. Create a normal pending booking owned by the recipient. Its subtotal and total equal the listed ticket price, and `paid_total` is zero.
3. Create the matching booking line, attendee, and a single pending full-payment installment for the full amount.
4. Settle the booking with the selected payment method:
   - **Voucher:** validate and redeem the code through `GoshenVoucherService`. This creates a voucher usage and paid voucher transaction, then calls `PaymentSettlementService`.
   - **Wallet:** resolve and lock only the issuing admin's linked wallet; enforce active status, security-reset guard, currency match, and sufficient balance; debit it; create a paid `retreat_payment` ledger entry and paid wallet transaction with payer and beneficiary references; then call `PaymentSettlementService`.
5. Rely on `PaymentSettlementService` to mark the installment and booking paid, calculate `paid_total`, issue the QR-backed ticket, send normal post-payment notifications, and execute payment-dependent referral behaviour.
6. Add an event audit log and ticket metadata that identify the web admin issuer, recipient, payment method, payment transaction reference, payer mobile-user ID for wallet, voucher usage ID for voucher, and issuance reason.

The issuance service must not set `total` to zero, set a booking to paid directly, invoke `TicketIssuer` directly, set complimentary/waiver metadata, or create a ticket without a corresponding settled payment record.

## Reporting and Data Invariants

For every successful paid admin issuance:

- booking `subtotal` and `total` equal the charged amount;
- booking `paid_total` equals `total` and status is paid;
- one installment has the charged amount, paid amount, paid timestamp, and paid status;
- one paid payment transaction has gateway, amount, currency, timestamp, and reference;
- voucher payment has a linked `GoshenVoucherUsage` record;
- wallet payment has a matching wallet debit ledger entry; and
- the ticket is issued only after settlement and is attributable through the audit trail.

This makes the ticket appear in regular sales totals and attributes gateway revenue accurately to voucher or wallet instead of classifying it as free.

## Error Handling

The flow rejects and rolls back fully for duplicate tickets, invalid or exhausted vouchers, voucher event/currency/amount mismatches, insufficient wallet balance, wallet currency mismatches, wallet security restrictions, missing/blocked linked payer accounts, inactive ticket types, unpublished events, and payment-settlement failures.

## Testing and Verification

Focused Laravel tests will cover:

- immediate wallet provisioning for new mobile users and idempotent provisioning for existing users;
- web-admin/mobile linkage by normalized email without creating a second mobile account or Triumphant ID;
- voucher issuance creates normal booking, installment, transaction, voucher usage, settlement, ticket, and audit records;
- wallet issuance debits only the issuer's same-email wallet and creates normal ledger, transaction, settlement, ticket, and audit records;
- sales/gateway summary includes each payment correctly;
- invalid voucher, insufficient balance, blocked/missing linked payer, security restriction, duplicate ticket, and unauthorized access paths fail safely; and
- issue-only administrators can create tickets but cannot manage existing tickets.

Flutter requires no feature change: its existing manager-assisted voucher registration remains the compatibility reference for backend behaviour.
