# Central Transaction History Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a central admin transaction hub plus per-user financial history covering Stripe/payment records, wallet ledger entries, and voucher usage, with timestamp and payer IP context where available.

**Architecture:** Keep existing payment/wallet/voucher tables as the source of truth. Add a normalized `goshen_transaction_entries` projection table populated from source records so Filament can search/filter/sort without fragile cross-domain joins.

**Tech Stack:** Laravel 12, Filament 5, MySQL, existing event-installments package models, PHPUnit feature tests.

## Global Constraints

- Use only `C:\Appbuild\Goshen-Laravel-Admin-Staging` for Laravel implementation.
- Do not use or modify the old Covenant workspace.
- Preserve existing payment/source records; the transaction hub is read-only and must not mutate payments.
- Store one canonical `occurred_at` timestamp and derive date/month/year/time in UI.
- Include IP context when captured; historical records may display “Not captured”.
- Commit, push, and deploy after verification.

---

### Task 1: Projection schema and model

**Files:**
- Create: `database/migrations/2026_07_11_160000_create_goshen_transaction_entries_table.php`
- Create: `app/Models/GoshenTransactionEntry.php`
- Test: `tests/Feature/GoshenTransactionEntrySyncTest.php`

**Interfaces:**
- Produces `App\Models\GoshenTransactionEntry`
- Produces table columns used by sync service and Filament resource.

- [ ] Write failing test asserting transaction rows can be created with source pointer, mobile user, amount, provider, occurred timestamp, and IP hash/display fields.
- [ ] Run targeted test and confirm it fails before the migration/model exist.
- [ ] Add migration and model.
- [ ] Re-run syntax/test checks.

### Task 2: Sync service and backfill command

**Files:**
- Create: `app/Services/GoshenTransactionEntrySyncService.php`
- Create: `app/Console/Commands/SyncGoshenTransactionEntries.php`
- Modify: `routes/console.php` only if scheduling is needed; initial implementation will not schedule.
- Test: `tests/Feature/GoshenTransactionEntrySyncTest.php`

**Interfaces:**
- Consumes source records from `PaymentTransaction`, `GoshenWalletLedgerEntry`, and `GoshenVoucherUsage`.
- Produces `syncAll(): array`, `syncPaymentTransaction($record)`, `syncWalletLedgerEntry($record)`, `syncVoucherUsage($record)`.

- [ ] Add tests for syncing one event payment, one wallet ledger row, and one voucher usage row.
- [ ] Confirm tests fail before the service exists.
- [ ] Implement idempotent upsert logic keyed by `source_table + source_id`.
- [ ] Implement command `goshen:sync-transaction-entries --fresh`.
- [ ] Re-run targeted checks.

### Task 3: Admin central resource and per-user relation

**Files:**
- Create: `app/Filament/Resources/GoshenTransactionEntryResource.php`
- Create: `app/Filament/Resources/GoshenTransactionEntryResource/Pages/ListGoshenTransactionEntries.php`
- Create: `app/Filament/Resources/MobileUserResource/RelationManagers/TransactionEntriesRelationManager.php`
- Modify: `app/Filament/Resources/MobileUserResource.php`
- Test: `tests/Feature/GoshenTransactionEntryAdminTest.php`

**Interfaces:**
- Consumes `GoshenTransactionEntry` records.
- Produces read-only central list and per-user relation manager.

- [ ] Add tests that the resource is read-only and that user relation filters by `mobile_user_id`.
- [ ] Confirm tests fail before resource/relation exist.
- [ ] Implement central Filament resource under `Finance`.
- [ ] Implement user detail “Financial activity” relation manager.
- [ ] Add filters: source, provider, status, currency, date range.
- [ ] Add columns: member, source, provider, reference, amount, status, occurred date/time, month, year, IP status/hash.

### Task 4: Verification, sync, commit, deploy

**Files:**
- No new production code expected unless verification exposes a bug.

- [ ] Run PHP syntax checks on all touched files.
- [ ] Run `git diff --check`.
- [ ] Attempt targeted PHPUnit tests; if local Artisan hangs, document the limitation.
- [ ] Commit and push.
- [ ] Deploy production.
- [ ] Run remote migration and sync command through deploy flow.
- [ ] Verify release marker, setting/bootstrap health, and transaction table count.
