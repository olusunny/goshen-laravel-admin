<?php

namespace Tests\Feature;

use App\Models\SmtpSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SmtpSettingTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_the_latest_activated_smtp_setting_remains_active(): void
    {
        $zoho = SmtpSetting::query()->create([
            'name' => 'Zoho SMTP',
            'host' => 'smtp.zoho.com',
            'port' => 587,
            'encryption' => 'tls',
            'username' => 'mail@example.test',
            'password' => 'zoho-secret',
            'from_address' => 'mail@example.test',
            'from_name' => 'MFM Triumphant Church',
            'is_active' => true,
        ]);

        $gmail = SmtpSetting::query()->create([
            'name' => 'Gmail SMTP',
            'host' => 'smtp.gmail.com',
            'port' => 587,
            'encryption' => 'tls',
            'username' => 'church@gmail.com',
            'password' => 'gmail-app-password',
            'from_address' => 'church@gmail.com',
            'from_name' => 'MFM Triumphant Church',
            'is_active' => true,
        ]);

        $this->assertFalse($zoho->fresh()->is_active);
        $this->assertTrue($gmail->fresh()->is_active);
        $this->assertTrue(SmtpSetting::active()->is($gmail));
    }

    public function test_reactivating_an_existing_smtp_setting_deactivates_other_settings(): void
    {
        $zoho = SmtpSetting::query()->create([
            'name' => 'Zoho SMTP',
            'host' => 'smtp.zoho.com',
            'port' => 587,
            'encryption' => 'tls',
            'username' => 'mail@example.test',
            'password' => 'zoho-secret',
            'from_address' => 'mail@example.test',
            'from_name' => 'MFM Triumphant Church',
            'is_active' => true,
        ]);

        $gmail = SmtpSetting::query()->create([
            'name' => 'Gmail SMTP',
            'host' => 'smtp.gmail.com',
            'port' => 587,
            'encryption' => 'tls',
            'username' => 'church@gmail.com',
            'password' => 'gmail-app-password',
            'from_address' => 'church@gmail.com',
            'from_name' => 'MFM Triumphant Church',
            'is_active' => true,
        ]);

        $zoho->fresh()->forceFill(['is_active' => true])->save();

        $this->assertTrue($zoho->fresh()->is_active);
        $this->assertFalse($gmail->fresh()->is_active);
        $this->assertTrue(SmtpSetting::active()->is($zoho));
    }
}
