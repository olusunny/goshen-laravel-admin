<?php

return [
    'api_routes_enabled' => env('EVENT_INSTALLMENTS_API_ROUTES_ENABLED', true),
    'route_prefix' => env('EVENT_INSTALLMENTS_ROUTE_PREFIX', 'event-installments'),
    'api_prefix' => env('EVENT_INSTALLMENTS_API_PREFIX', 'api/event-installments/v1'),

    'middleware' => [
        'web' => ['web', 'auth'],
        'api' => ['api', 'throttle:event-installments-api'],
        'auth' => ['auth:sanctum'],
        'webhooks' => ['api', 'throttle:event-installments-webhooks'],
    ],

    'ticket' => [
        'issue_policy' => env('EVENT_INSTALLMENTS_TICKET_ISSUE_POLICY', 'paid_in_full'),
        'identifier_prefix' => env('EVENT_INSTALLMENTS_TICKET_PREFIX', 'EVT'),
        'qr_payload_version' => 1,
        'qr_secret' => env('EVENT_INSTALLMENTS_QR_SECRET'),
        'email' => [
            'enabled' => env('EVENT_INSTALLMENTS_EMAIL_TICKETS', true),
            'attach_pdf' => env('EVENT_INSTALLMENTS_EMAIL_ATTACH_PDF', true),
            'attach_ics' => env('EVENT_INSTALLMENTS_EMAIL_ATTACH_ICS', true),
            'subject' => env('EVENT_INSTALLMENTS_TICKET_EMAIL_SUBJECT', 'Your event ticket'),
            'from_address' => env('EVENT_INSTALLMENTS_MAIL_FROM_ADDRESS'),
            'from_name' => env('EVENT_INSTALLMENTS_MAIL_FROM_NAME'),
        ],
    ],

    'payments' => [
        'default_gateway' => env('EVENT_INSTALLMENTS_PAYMENT_GATEWAY', 'null'),
        'currency' => env('EVENT_INSTALLMENTS_CURRENCY', 'USD'),
        'deposit_mode' => env('EVENT_INSTALLMENTS_DEPOSIT_MODE', 'percentage'),
        'deposit_value' => (float) env('EVENT_INSTALLMENTS_DEPOSIT_VALUE', 50),
        'grace_days' => (int) env('EVENT_INSTALLMENTS_PAYMENT_GRACE_DAYS', 3),
        'stripe' => [
            'secret' => env('STRIPE_SECRET'),
            'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
            'api_version' => env('STRIPE_API_VERSION', '2026-02-25.clover'),
            'webhook_tolerance' => (int) env('STRIPE_WEBHOOK_TOLERANCE', 300),
            'success_url' => env('EVENT_INSTALLMENTS_STRIPE_SUCCESS_URL'),
            'cancel_url' => env('EVENT_INSTALLMENTS_STRIPE_CANCEL_URL'),
        ],
        'paystack' => [
            'secret' => env('PAYSTACK_SECRET_KEY'),
            'webhook_secret' => env('PAYSTACK_WEBHOOK_SECRET', env('PAYSTACK_SECRET_KEY')),
            'callback_url' => env('EVENT_INSTALLMENTS_PAYSTACK_CALLBACK_URL'),
        ],
    ],

    'storage' => [
        'disk' => env('EVENT_INSTALLMENTS_STORAGE_DISK', 'local'),
        'qr_path' => 'event-installments/tickets/qr',
        'pdf_path' => 'event-installments/tickets/pdf',
        'ics_path' => 'event-installments/tickets/ics',
    ],

    'roles' => [
        'super_admin' => 'super-admin',
        'event_manager' => 'event-manager',
        'check_in_staff' => 'check-in-staff',
        'finance_manager' => 'finance-manager',
        'customer' => 'customer',
    ],
];
