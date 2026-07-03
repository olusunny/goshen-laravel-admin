<?php

namespace ChurchTools\CloudBackup\Services\Cloud;

use ChurchTools\CloudBackup\Contracts\CloudProvider;
use InvalidArgumentException;

class CloudProviderFactory
{
    public function make(string $provider): CloudProvider
    {
        return match ($provider) {
            'google' => app(GoogleDriveProvider::class),
            'onedrive' => app(OneDriveProvider::class),
            default => throw new InvalidArgumentException("Unsupported cloud provider [{$provider}]."),
        };
    }
}
