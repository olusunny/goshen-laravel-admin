<?php

namespace Personal\EventInstallments\Services;

use Illuminate\Support\Facades\Config;
use Personal\EventInstallments\Models\Ticket;
use RuntimeException;

class QrPayloadService
{
    public function payloadFor(Ticket $ticket): array
    {
        $payload = [
            'v' => (int) config('event-installments.ticket.qr_payload_version', 1),
            'ticket' => $ticket->public_id,
            'event' => $ticket->event->public_id,
            'number' => $ticket->formatted_number ?: $ticket->ticket_number,
        ];

        $payload['sig'] = $this->sign($payload);

        return $payload;
    }

    public function encodedPayloadFor(Ticket $ticket): string
    {
        return base64_encode(json_encode($this->payloadFor($ticket), JSON_THROW_ON_ERROR));
    }

    public function verify(array $payload): bool
    {
        if (! isset($payload['sig'])) {
            return false;
        }

        $signature = $payload['sig'];
        unset($payload['sig']);

        return hash_equals($signature, $this->sign($payload));
    }

    private function sign(array $payload): string
    {
        $secret = Config::get('event-installments.ticket.qr_secret');

        if (! is_string($secret) || $secret === '') {
            throw new RuntimeException('EVENT_INSTALLMENTS_QR_SECRET must be configured before generating QR payloads.');
        }

        ksort($payload);

        return hash_hmac('sha256', json_encode($payload, JSON_THROW_ON_ERROR), $secret);
    }
}
