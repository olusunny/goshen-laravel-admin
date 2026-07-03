# Stripe Sandbox Setup

This package uses Stripe-hosted Checkout Sessions for each booking installment. Card details stay on Stripe-hosted pages, webhook events reconcile payments back to local `ei_payment_transactions`, and tickets can be issued after full payment or deposit payment according to the payment plan policy.

## Why No Bundled Secret Keys

Stripe test secret keys and webhook signing secrets belong to your Stripe account. They should never be committed to this addon or shared as "standard" credentials. Use Stripe test mode keys from your own Dashboard or Stripe CLI.

Safe test card values are documented by Stripe and can be entered on the hosted Checkout page:

- Card number: `4242 4242 4242 4242`
- Expiry: any future date, for example `12/34`
- CVC: any three digits
- Postal code: any value required by your checkout form

## Environment

Set the gateway to Stripe and provide test-mode credentials:

```dotenv
EVENT_INSTALLMENTS_PAYMENT_GATEWAY=stripe
EVENT_INSTALLMENTS_CURRENCY=USD

STRIPE_SECRET=sk_test_your_account_key
STRIPE_WEBHOOK_SECRET=whsec_your_endpoint_secret
STRIPE_API_VERSION=2026-02-25.clover
STRIPE_WEBHOOK_TOLERANCE=300

EVENT_INSTALLMENTS_STRIPE_SUCCESS_URL=https://your-app.test/event-installments/payments/stripe/success?session_id={CHECKOUT_SESSION_ID}
EVENT_INSTALLMENTS_STRIPE_CANCEL_URL=https://your-app.test/event-installments/payments/stripe/cancel
```

For Nigerian Naira, use:

```dotenv
EVENT_INSTALLMENTS_CURRENCY=NGN
```

Stripe expects amounts in minor units; the gateway converts `100.00` into `10000` for currencies with cents/kobo.

## Local Webhook Testing

Install and authenticate the Stripe CLI, then forward events to the package webhook route:

```bash
stripe listen --forward-to https://your-app.test/webhooks/event-installments/stripe
```

Copy the printed `whsec_...` signing secret into `STRIPE_WEBHOOK_SECRET`.

Required webhook events:

- `checkout.session.completed`
- `checkout.session.async_payment_succeeded`
- `checkout.session.async_payment_failed`
- `checkout.session.expired`
- `payment_intent.succeeded`
- `payment_intent.payment_failed`
- `payment_intent.canceled`

The Checkout Session includes `client_reference_id` and matching metadata so the webhook can locate the local transaction even when Stripe sends a PaymentIntent event.

## Booking Flow

1. Create an event, ticket types, and a payment plan.
2. Create a booking through `POST /api/event-installments/v1/bookings`.
3. Start payment for one installment through:

```http
POST /api/event-installments/v1/bookings/{booking}/installments/{installment}/checkout
```

The response contains a Stripe Checkout URL. Redirect the customer there. After payment, Stripe sends a webhook to:

```http
POST /webhooks/event-installments/stripe
```

The webhook marks the transaction/installment paid, updates the booking status, and issues tickets when the configured ticket issue policy is satisfied.

## Production Checklist

- Use live `STRIPE_SECRET` only in production secrets storage, never in code.
- Use HTTPS for success, cancel, and webhook URLs.
- Configure webhook signing secrets per environment.
- Keep `STRIPE_WEBHOOK_TOLERANCE` at `300` seconds unless your infrastructure has a known clock-skew issue.
- Ensure queue/mail workers are healthy before enabling automatic ticket emails.
- Restrict booking checkout and mobile check-in routes with Sanctum abilities or host-app policies.
- Run package tests and a real Stripe test-mode checkout before switching to live mode.
