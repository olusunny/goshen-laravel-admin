<?php

namespace App\Services;

use App\Models\AppSetting;

class GoshenTicketPdfTemplateSettings
{
    public const KEY = 'goshen_ticket_pdf_template';

    public const DEFAULT = 'executive_white';

    /**
     * @return array<string, array{name: string, subtitle: string, description: string, accent: string}>
     */
    public function templates(): array
    {
        return [
            'executive_white' => [
                'name' => 'Executive White Border',
                'subtitle' => 'Bright classic layout',
                'description' => 'Closest to the current ticket, but brighter, sharper, and easier to read.',
                'accent' => '#d9a920',
            ],
            'boarding_pass' => [
                'name' => 'Premium Boarding Pass',
                'subtitle' => 'Modern entry pass',
                'description' => 'A polished travel-style ticket with a strong QR stub and premium header.',
                'accent' => '#0d4b55',
            ],
            'identity_credential' => [
                'name' => 'Identity Credential',
                'subtitle' => 'Photo-first recognition',
                'description' => 'A clean credential-style layout that gives attendee identity strong prominence.',
                'accent' => '#08283a',
            ],
            'qr_hero' => [
                'name' => 'Clean QR Hero',
                'subtitle' => 'Maximum scan clarity',
                'description' => 'Puts the QR code at the centre with supporting ticket details below.',
                'accent' => '#0f2430',
            ],
            'certificate' => [
                'name' => 'Elegant Certificate Ticket',
                'subtitle' => 'Formal premium paper',
                'description' => 'A ceremonial ticket style with a premium certificate feel.',
                'accent' => '#b8860b',
            ],
        ];
    }

    public function active(): string
    {
        return $this->normalize((string) AppSetting::value(self::KEY, self::DEFAULT));
    }

    public function save(string $template): string
    {
        $template = $this->normalize($template);

        AppSetting::query()->updateOrCreate(
            ['key' => self::KEY],
            [
                'group' => 'goshen_retreat',
                'value' => $template,
                'is_secret' => false,
                'description' => 'Preferred PDF ticket template for Goshen Retreat tickets.',
            ],
        );

        return $template;
    }

    public function normalize(string $template): string
    {
        return array_key_exists($template, $this->templates()) ? $template : self::DEFAULT;
    }
}
