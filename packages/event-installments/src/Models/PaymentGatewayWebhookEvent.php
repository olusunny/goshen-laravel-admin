<?php

namespace Personal\EventInstallments\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentGatewayWebhookEvent extends Model
{
    protected $table = 'ei_payment_gateway_webhook_events';

    protected $fillable = [
        'gateway',
        'provider_event_id',
        'event_type',
        'status',
        'payload',
        'processed_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'processed_at' => 'datetime',
    ];
}
