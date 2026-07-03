<?php

namespace App\Services\Addons;

use RuntimeException;
use ZipArchive;

class AddonSignatureVerifier
{
    /**
     * @param array{checksum?: string} $inspection
     * @return array{verified: bool|null, method: string|null, key_id: string|null}
     */
    public function verify(string $zipPath, array $inspection): array
    {
        $checksum = strtolower((string) ($inspection['checksum'] ?? ''));

        if ($checksum !== '' && in_array($checksum, $this->trustedChecksums(), true)) {
            return ['verified' => true, 'method' => 'checksum_allowlist', 'key_id' => null];
        }

        $signature = $this->readSignature($zipPath);
        $keys = $this->trustedPublicKeys();

        if ($signature && $keys !== []) {
            $payload = $this->canonicalZipPayload($zipPath);
            $decoded = base64_decode($signature['value'], true);

            if ($decoded === false || $decoded === '') {
                throw new RuntimeException('The add-on signature is not valid base64.');
            }

            foreach ($keys as $keyId => $publicKey) {
                $result = openssl_verify($payload, $decoded, $publicKey, OPENSSL_ALGO_SHA256);

                if ($result === 1) {
                    return ['verified' => true, 'method' => 'openssl_sha256', 'key_id' => (string) $keyId];
                }
            }

            throw new RuntimeException('The add-on ZIP signature could not be verified with the configured public keys.');
        }

        if ($this->signaturesRequired()) {
            throw new RuntimeException('This environment requires signed add-on ZIPs or a trusted checksum allowlist entry.');
        }

        return ['verified' => null, 'method' => null, 'key_id' => null];
    }

    private function signaturesRequired(): bool
    {
        return (bool) config('addons.signatures.required', false);
    }

    /**
     * @return array<int, string>
     */
    private function trustedChecksums(): array
    {
        return array_map(
            fn (mixed $checksum): string => strtolower((string) $checksum),
            (array) config('addons.signatures.trusted_checksums', []),
        );
    }

    /**
     * @return array<string, string>
     */
    private function trustedPublicKeys(): array
    {
        $keys = [];

        foreach ((array) config('addons.signatures.public_keys', []) as $keyId => $key) {
            if (is_string($key) && trim($key) !== '') {
                $keys[(string) $keyId] = $key;
            }
        }

        foreach ((array) config('addons.signatures.public_key_paths', []) as $path) {
            if (! is_string($path) || trim($path) === '') {
                continue;
            }

            $contents = is_file($path) ? file_get_contents($path) : false;
            if (is_string($contents) && trim($contents) !== '') {
                $keys[$path] = $contents;
            }
        }

        return $keys;
    }

    /**
     * @return array{value: string}
     */
    private function readSignature(string $zipPath): ?array
    {
        $entryName = (string) config('addons.signatures.signature_entry', 'addon.sig');
        $zip = $this->open($zipPath);

        try {
            $payload = $zip->getFromName($entryName);
        } finally {
            $zip->close();
        }

        if (! is_string($payload) || trim($payload) === '') {
            return null;
        }

        $decoded = json_decode($payload, true);
        if (is_array($decoded) && is_string($decoded['signature'] ?? null)) {
            return ['value' => trim($decoded['signature'])];
        }

        return ['value' => trim($payload)];
    }

    private function canonicalZipPayload(string $zipPath): string
    {
        $zip = $this->open($zipPath);
        $signatureEntry = str_replace('\\', '/', (string) config('addons.signatures.signature_entry', 'addon.sig'));
        $entries = [];

        try {
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $entry = $zip->statIndex($index);
                $name = str_replace('\\', '/', (string) ($entry['name'] ?? ''));

                if ($name === '' || str_ends_with($name, '/') || $name === $signatureEntry) {
                    continue;
                }

                $stream = $zip->getStream($name);
                if (! $stream) {
                    throw new RuntimeException("Unable to read ZIP entry [{$name}] for signature verification.");
                }

                $hash = hash_init('sha256');
                $size = 0;

                try {
                    while (! feof($stream)) {
                        $chunk = fread($stream, 1024 * 1024);
                        if ($chunk === false) {
                            throw new RuntimeException("Unable to read ZIP entry [{$name}] for signature verification.");
                        }

                        $size += strlen($chunk);
                        hash_update($hash, $chunk);
                    }
                } finally {
                    fclose($stream);
                }

                $entries[$name] = $size.':'.hash_final($hash);
            }
        } finally {
            $zip->close();
        }

        ksort($entries);

        return collect($entries)
            ->map(fn (string $fingerprint, string $name): string => $name.':'.$fingerprint)
            ->implode("\n");
    }

    private function open(string $zipPath): ZipArchive
    {
        $zip = new ZipArchive();

        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException('The uploaded file is not a readable ZIP archive.');
        }

        return $zip;
    }
}
