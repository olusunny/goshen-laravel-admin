# API Examples

## Create Booking

```http
POST /api/event-installments/v1/bookings
Authorization: Bearer <token>
Content-Type: application/json
```

```json
{
  "event_id": "01J...",
  "payment_plan_id": "01J...",
  "customer_name": "Ada Lovelace",
  "customer_email": "ada@example.com",
  "customer_phone": "+15555550100",
  "lines": [
    {
      "ticket_type_id": "01J...",
      "quantity": 2
    }
  ],
  "attendees": [
    {
      "ticket_type_id": "01J...",
      "first_name": "Ada",
      "last_name": "Lovelace",
      "email": "ada@example.com"
    },
    {
      "ticket_type_id": "01J...",
      "first_name": "Grace",
      "last_name": "Hopper",
      "email": "grace@example.com"
    }
  ]
}
```

## Start Installment Checkout

```http
POST /api/event-installments/v1/bookings/{booking_public_id}/installments/{installment_public_id}/checkout
Authorization: Bearer <token>
```

Response:

```json
{
  "gateway": "stripe",
  "reference": "ei_01J...",
  "checkoutUrl": "https://checkout.stripe.com/..."
}
```

## Check In Ticket

```http
POST /api/event-installments/v1/tickets/{ticket_identifier}/check-ins
Authorization: Bearer <token>
Content-Type: application/json
```

```json
{
  "status": "checked_in",
  "device_id": "flutter-device-01",
  "source": "flutter",
  "metadata": {
    "offline_sync": false
  }
}
```

## Bulk Check-In

```json
{
  "device_id": "flutter-device-01",
  "source": "flutter",
  "tickets": [
    {
      "identifier": "EVT-1-000001",
      "status": "checked_in"
    },
    {
      "identifier": "EVT-1-000002",
      "status": "checked_in",
      "day_number": 2
    }
  ]
}
```
