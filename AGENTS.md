# AGENTS.md — Goshen Laravel Admin/Web App

This repository is the Laravel admin/web app for the Goshen Retreat / MFM Triumphant Church platform.

Authoritative repo path:

- `C:\Appbuild\Goshen-Laravel-Admin-Staging`

GitHub:

- `olusunny/goshen-laravel-admin`

Related Flutter repo:

- `C:\Appbuild\Goshen-Flutter-App`
- `olusunny/goshen-flutter-app`

Do not use or modify older `C:\ScriptsDev\CovenantofmercyAPP...` workspaces unless explicitly asked.

## Required operating rules

- Preserve existing conventions.
- Check git status before changing files.
- Do not expose secrets, `.env` values, credentials, API keys, tokens, private keys, or database passwords.
- Commit and push completed implementation changes.
- Deploy only pushed commits when production deployment is requested/appropriate.
- Verify desktop and mobile browser views for web UI changes.
- Inspect Flutter behavior when changing a feature that also exists in the mobile app.

## Production context

- Production portal: `https://portal.goshenretreat.uk`
- cPanel account: `goshenretreat`
- Known SSH host/IP: `31.220.76.23`
- Known SSH port: `2222`

Never print private key contents.

Previous security hardening commits:

- `06d293c Harden portal after malware incident`
- `1011c45 Allow deploy composer install flags`

Latest known deployed hardening commit:

- `1011c45b638c3e9983107989ae0d81ab69d0c6f5`

## Security baseline

A previous public-webroot malware/webshell incident was discovered and remediated. Keep these controls intact:

- Public webroot should only execute Laravel `index.php`.
- Block PHP-like files outside the intended front controller:
  - `.php`
  - `.php3`
  - `.php4`
  - `.php5`
  - `.php7`
  - `.php8`
  - `.phtml`
  - `.phar`
- `storage/app/public` must deny PHP-like execution.
- Deploy should fail if unexpected PHP-like files are exposed.
- Suspicious files should be quarantined, not silently deleted, unless explicitly approved.
- Keep Composer dependencies patched.
- Run `composer audit` and `npm audit --omit=dev` for security/dependency work.

Recommended server follow-up after any malware concern:

- WHM/root audit
- Imunify360 history
- SSH/cPanel login history
- PHP `auto_prepend_file`
- cron jobs
- recent webroot file mtimes
- credential rotation where appropriate

## Core product rules

- Laravel database is the source of truth for users and member records.
- Google login must not create detached users outside the real database.
- A user must not receive duplicate Triumphant IDs.
- One email/person must not have two Triumphant IDs.
- Registered users should have linked wallet records.
- Admin-created users should get required linked `MobileUser` and wallet records according to system rules.
- Paystack is suspended; Stripe is active.
- Do not reintroduce installment payments.
- Payments are full payment only, using Stripe, wallet, or voucher.
- Do not issue tickets through complimentary/zero-payment bookings unless explicitly requested.
- Ticket issuance must preserve payment/audit/reporting integrity.

## Wallet/voucher rules

- Wallet transactions should maintain complete audit metadata.
- Web wallet transactions should require a six-digit email OTP before transaction completion.
- Voucher purpose types:
  - `Wallet Funding`
  - `For Payments`
- `Wallet Funding` vouchers can only top up wallets.
- `For Payments` vouchers can be used for supported retreat/ticket/payment flows.
- Admin wallet top-up must be permission-gated, activation-setting controlled, and fully audited.
- Transaction details should display metadata in human-readable form, not raw JSON.

## Goshen retreat rules

- Retreat editions should support start date and end date.
- Countdown must use retreat start date.
- Do not show “Dates will be announced” when a start date exists.
- Ticket selection must apply `min_per_booking` and `max_per_booking`.
- `Goshen Family` attendee quantity field should show only when that ticket type is selected.
- Attendee details must be preserved when count changes.
- Ticket PDFs should include event logo, event title, large QR code, ticket details, amount paid, and live QR scan instruction.

## Admin permissions

If an admin role lacks permission for a feature/module:

- Hide navigation links.
- Hide settings links.
- Block direct access server-side.

Navigation visibility is not enough; always enforce authorization.

## Testing/deploy checklist

For Laravel changes:

1. PHP lint changed PHP files.
2. Run focused PHPUnit tests for touched features.
3. Run route/list checks where relevant.
4. Run dependency audits for security/dependency work.
5. Commit and push relevant changes.
6. Deploy pushed commit when appropriate.
7. Verify production routes/pages.

Expected scheduler:

```bash
* * * * * cd /home/goshenretreat/apps/portal/current && /opt/cpanel/ea-php84/root/usr/bin/php artisan schedule:run >> /home/goshenretreat/apps/portal/shared/storage/logs/scheduler.log 2>&1
```
