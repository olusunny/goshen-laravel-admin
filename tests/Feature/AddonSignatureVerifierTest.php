<?php

namespace Tests\Feature;

use App\Services\Addons\AddonSignatureVerifier;
use RuntimeException;
use Tests\TestCase;
use ZipArchive;

class AddonSignatureVerifierTest extends TestCase
{
    private array $createdZips = [];

    protected function tearDown(): void
    {
        foreach ($this->createdZips as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }

        parent::tearDown();
    }

    public function test_required_signature_rejects_unsigned_zip(): void
    {
        config(['addons.signatures.required' => true]);
        $zip = $this->zip($this->entries());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('requires signed add-on ZIPs');

        $this->verifier()->verify($zip, ['checksum' => hash_file('sha256', $zip)]);
    }

    public function test_trusted_checksum_passes_without_signature(): void
    {
        $zip = $this->zip($this->entries());
        $checksum = hash_file('sha256', $zip);

        config([
            'addons.signatures.required' => true,
            'addons.signatures.trusted_checksums' => [$checksum],
        ]);

        $result = $this->verifier()->verify($zip, ['checksum' => $checksum]);

        $this->assertTrue($result['verified']);
        $this->assertSame('checksum_allowlist', $result['method']);
    }

    public function test_detached_signature_passes_with_trusted_public_key(): void
    {
        $entries = $this->entries();
        $payload = $this->canonicalPayload($entries);
        $key = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        if ($key === false) {
            $this->markTestSkipped('OpenSSL key generation is not available in this PHP environment.');
        }

        $this->assertTrue(openssl_pkey_export($key, $privateKey));
        $details = openssl_pkey_get_details($key);
        $publicKey = $details['key'] ?? null;
        $this->assertIsString($publicKey);
        $this->assertTrue(openssl_sign($payload, $signature, $privateKey, OPENSSL_ALGO_SHA256));

        $zip = $this->zip([
            ...$entries,
            'addon.sig' => base64_encode($signature),
        ]);

        config([
            'addons.signatures.required' => true,
            'addons.signatures.public_keys' => ['test' => $publicKey],
        ]);

        $result = $this->verifier()->verify($zip, ['checksum' => hash_file('sha256', $zip)]);

        $this->assertTrue($result['verified']);
        $this->assertSame('openssl_sha256', $result['method']);
        $this->assertSame('test', $result['key_id']);
    }

    private function verifier(): AddonSignatureVerifier
    {
        return new AddonSignatureVerifier();
    }

    /**
     * @return array<string, string>
     */
    private function entries(): array
    {
        return [
            'addon.json' => '{"schema_version":"1.0","package_key":"sunny.test"}',
            'composer.json' => '{"name":"sunny/test"}',
            'src/TestServiceProvider.php' => '<?php',
        ];
    }

    /**
     * @param array<string, string> $entries
     */
    private function canonicalPayload(array $entries): string
    {
        ksort($entries);

        return collect($entries)
            ->map(fn (string $contents, string $name): string => $name.':'.strlen($contents).':'.hash('sha256', $contents))
            ->implode("\n");
    }

    /**
     * @param array<string, string> $entries
     */
    private function zip(array $entries): string
    {
        if (! class_exists(ZipArchive::class)) {
            $this->markTestSkipped('ZipArchive is not available.');
        }

        $path = tempnam(sys_get_temp_dir(), 'addon-signature-test-');
        $this->createdZips[] = $path;

        $zip = new ZipArchive();
        $this->assertTrue($zip->open($path, ZipArchive::OVERWRITE));

        foreach ($entries as $name => $contents) {
            $this->assertTrue($zip->addFromString($name, $contents));
        }

        $this->assertTrue($zip->close());

        return $path;
    }
}
