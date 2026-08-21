<?php

namespace Tests\Unit;

use App\Services\YouTube\YouTubeOAuthStateService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

class YouTubeOAuthStateServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('cache.default', 'array');
        app('cache')->forgetDriver();
    }

    public function test_it_encrypts_the_cached_pkce_payload_and_consumes_it_once(): void
    {
        $service = app(YouTubeOAuthStateService::class);
        $created = $service->create(adminId: 42, connectionId: 7);
        $cacheKey = 'goshen-youtube-oauth-state:'.$created['state'];

        $cached = Cache::get($cacheKey);

        $this->assertIsString($cached);
        $this->assertStringNotContainsString($created['code_verifier'], $cached);
        $this->assertSame([
            'admin_id' => 42,
            'connection_id' => 7,
            'code_verifier' => $created['code_verifier'],
        ], json_decode(Crypt::decryptString($cached), true, 512, JSON_THROW_ON_ERROR));

        $this->assertSame([
            'admin_id' => 42,
            'connection_id' => 7,
            'code_verifier' => $created['code_verifier'],
        ], $service->consume($created['state'], 42));

        $this->expectException(AuthorizationException::class);
        $service->consume($created['state'], 42);
    }

    public function test_it_rejects_a_tampered_cached_pkce_payload_without_leaking_decryption_details(): void
    {
        $state = str()->random(80);
        Cache::put('goshen-youtube-oauth-state:'.$state, 'tampered-ciphertext', now()->addMinute());

        $this->expectException(AuthorizationException::class);
        app(YouTubeOAuthStateService::class)->consume($state, 42);
    }
}
