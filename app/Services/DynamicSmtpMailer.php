<?php

namespace App\Services;

use App\Models\SmtpSetting;
use Illuminate\Support\Facades\Mail;

class DynamicSmtpMailer
{
    public function sendRaw(string $to, string $subject, string $body, ?SmtpSetting $setting = null): void
    {
        $setting ??= SmtpSetting::active();

        if (! $setting) {
            throw new \RuntimeException('No active SMTP setting is configured.');
        }

        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.transport' => 'smtp',
            'mail.mailers.smtp.host' => $setting->host,
            'mail.mailers.smtp.port' => $setting->port,
            'mail.mailers.smtp.encryption' => $setting->encryption ?: null,
            'mail.mailers.smtp.username' => $setting->username,
            'mail.mailers.smtp.password' => $setting->password,
            'mail.from.address' => $setting->from_address,
            'mail.from.name' => $setting->from_name,
        ]);

        Mail::mailer('smtp')->raw($body, function ($message) use ($to, $subject, $setting) {
            $message
                ->to($to)
                ->from($setting->from_address, $setting->from_name)
                ->subject($subject);
        });
    }

    public function sendHtml(string $to, string $subject, string $html, ?string $plainText = null, ?SmtpSetting $setting = null): void
    {
        $setting ??= SmtpSetting::active();

        if (! $setting) {
            throw new \RuntimeException('No active SMTP setting is configured.');
        }

        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.transport' => 'smtp',
            'mail.mailers.smtp.host' => $setting->host,
            'mail.mailers.smtp.port' => $setting->port,
            'mail.mailers.smtp.encryption' => $setting->encryption ?: null,
            'mail.mailers.smtp.username' => $setting->username,
            'mail.mailers.smtp.password' => $setting->password,
            'mail.from.address' => $setting->from_address,
            'mail.from.name' => $setting->from_name,
        ]);

        Mail::mailer('smtp')->html($html, function ($message) use ($to, $subject, $setting) {
            $message
                ->to($to)
                ->from($setting->from_address, $setting->from_name)
                ->subject($subject);
        });
    }
}
