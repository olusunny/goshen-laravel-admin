<?php

namespace Tests\Feature;

use App\Http\Middleware\RedirectLegacyGoshenHost;
use Illuminate\Http\Request;
use Tests\TestCase;

class LegacyGoshenHostRedirectTest extends TestCase
{
    public function test_legacy_goshen_shotfaz_host_redirects_to_current_portal(): void
    {
        $response = (new RedirectLegacyGoshenHost())->handle(
            Request::create('https://goshen.shotfaz.com/app'),
            fn () => response('not redirected'),
        );

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('https://portal.goshenretreat.uk/app', $response->headers->get('Location'));
    }

    public function test_www_legacy_goshen_shotfaz_host_redirects_to_current_portal(): void
    {
        $response = (new RedirectLegacyGoshenHost())->handle(
            Request::create('https://www.goshen.shotfaz.com/api/member/me'),
            fn () => response('not redirected'),
        );

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('https://portal.goshenretreat.uk/api/member/me', $response->headers->get('Location'));
    }
}
