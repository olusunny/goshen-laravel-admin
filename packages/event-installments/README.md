# Laravel Event Installments

Reusable Laravel addon for event creation, ticket booking, installment payments, QR/PDF/ICS ticket documents, and mobile check-in APIs.

This package is a clean Laravel rebuild of the event/ticket logic reviewed from the WordPress FooEvents plugin. It intentionally does not include FooEvents license validation, Envato keys, or update URLs.

## Current Build Status

Implemented:

- Composer package structure.
- Service provider, config, routes, migrations.
- Normalized Eloquent models for events, schedules, ticket types, attendee fields, bookings, attendees, tickets, check-ins, installments, transactions, webhooks, documents, and audit logs.
- Mobile/API routes for event sync, ticket sync, ticket lookup, single/bulk/multiday check-in.
- Booking creation with attendee capture and installment schedule generation.
- Payment gateway contract.
- Stripe Checkout/webhook/refund adapter using the official Stripe PHP SDK.
- Paystack checkout/webhook adapter.
- Null gateway for local testing.
- Signed QR payload service.
- Ticket issuance service.
- QR/PDF/ICS document service.
- Generic Laravel web admin routes and Blade screens for event CRUD.
- Admin management for schedules, ticket types, and payment plans.
- Policy registration for events, bookings, tickets, check-ins, and downloads.
- Signed QR/PDF/ICS download controller.
- Ticket email mailable, email template, send/resend service, and email audit logs.
- Payment-triggered ticket emails after successful issuance.
- Testbench/PHPUnit scaffold with core service tests.

## Install In A Laravel App

Add the package as a path repository in the host Laravel app:

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "../packages/event-installments"
        }
    ],
    "require": {
        "personal/event-installments": "*"
    }
}
```

Then run:

```bash
composer update personal/event-installments
php artisan vendor:publish --tag=event-installments-config
php artisan vendor:publish --tag=event-installments-migrations
php artisan migrate
```

Recommended optional packages:

```bash
composer require laravel/sanctum simplesoftwareio/simple-qrcode barryvdh/laravel-dompdf
```

Stripe support is included through `stripe/stripe-php`.

For package tests:

```bash
composer install
vendor/bin/phpunit
```

## Required Environment

```dotenv
EVENT_INSTALLMENTS_QR_SECRET=change-me-to-a-long-random-secret
EVENT_INSTALLMENTS_PAYMENT_GATEWAY=null
EVENT_INSTALLMENTS_CURRENCY=USD
EVENT_INSTALLMENTS_EMAIL_TICKETS=true
EVENT_INSTALLMENTS_EMAIL_ATTACH_PDF=true
EVENT_INSTALLMENTS_EMAIL_ATTACH_ICS=true
EVENT_INSTALLMENTS_TICKET_EMAIL_SUBJECT=Your event ticket
EVENT_INSTALLMENTS_MAIL_FROM_ADDRESS=
EVENT_INSTALLMENTS_MAIL_FROM_NAME=

STRIPE_SECRET=
STRIPE_WEBHOOK_SECRET=
STRIPE_API_VERSION=2026-02-25.clover
STRIPE_WEBHOOK_TOLERANCE=300
EVENT_INSTALLMENTS_STRIPE_SUCCESS_URL=
EVENT_INSTALLMENTS_STRIPE_CANCEL_URL=

PAYSTACK_SECRET_KEY=
PAYSTACK_WEBHOOK_SECRET=
EVENT_INSTALLMENTS_PAYSTACK_CALLBACK_URL=
```

## API Routes

Base path defaults to:

```text
/api/event-installments/v1
```

Authenticated endpoints:

- `GET /events`
- `GET /events/{event}`
- `GET /events/{event}/tickets`
- `GET /events/{event}/tickets/updated?since=...`
- `GET /tickets/{identifier}`
- `POST /tickets/{identifier}/check-ins`
- `POST /tickets/bulk-check-ins`
- `POST /tickets/{identifier}/days/{day}/check-ins`
- `POST /bookings`
- `GET /bookings/{booking}`
- `POST /bookings/{booking}/installments/{installment}/checkout`

Webhook endpoints:

- `POST /webhooks/event-installments/stripe`
- `POST /webhooks/event-installments/paystack`
- `POST /webhooks/event-installments/null`

## Admin Routes

Base admin path defaults to:

```text
/event-installments
```

Routes:

- `GET /event-installments/events`
- `GET /event-installments/events/create`
- `POST /event-installments/events`
- `GET /event-installments/events/{event}`
- `GET /event-installments/events/{event}/edit`
- `PUT /event-installments/events/{event}`
- `DELETE /event-installments/events/{event}`
- `POST /event-installments/events/{event}/schedules`
- `POST /event-installments/events/{event}/ticket-types`
- `POST /event-installments/events/{event}/payment-plans`

Ticket documents use signed routes:

```php
URL::temporarySignedRoute(
    'event-installments.tickets.documents.show',
    now()->addMinutes(15),
    ['ticket' => $ticket, 'type' => 'pdf']
);
```

Supported document types: `qr`, `pdf`, `ics`.

Ticket emails can be resent by event managers:

```http
POST /event-installments/tickets/{ticket}/email
```

Optional body:

```json
{
  "recipient": "alternate@example.com"
}
```

## Important Security Notes

- Mobile/API routes are under `auth:sanctum` by default.
- Stripe uses hosted Checkout Sessions, account-owned API keys, and signed webhooks. See [Stripe Sandbox Setup](docs/stripe-sandbox.md).
- Webhooks verify gateway signatures in the Stripe and Paystack adapters.
- Webhook events are stored with provider event IDs and processed idempotently.
- Stripe webhook processing sends ticket emails only after the database transaction commits.
- QR payloads are HMAC signed and require `EVENT_INSTALLMENTS_QR_SECRET`.
- The included document route requires Laravel signed URLs and authenticated web middleware.
- Every ticket email attempt is stored in `ei_ticket_email_logs`.
- Host app policies should restrict event visibility and check-in permissions by role.

## Not Ported By Design

- FooEvents license key settings.
- Envato API key settings.
- FooEvents update helper.
- `https://updates.fooevents.com`
- `https://www.fooevents.com/update_info/...`
- XML-RPC API.
- WordPress/WooCommerce post meta storage.

## Next Implementation Slices

1. Stripe and Paystack integration tests using fake HTTP clients.
2. Filament/Nova/Backpack adapters for teams that want native admin resources.
3. Flutter API contract examples and offline sync conflict handling.
4. Seat-map and booking-slot inventory locking.
5. Booking capacity and race-condition tests.
