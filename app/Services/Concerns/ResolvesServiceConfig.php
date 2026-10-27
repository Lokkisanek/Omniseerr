<?php

namespace App\Services\Concerns;

use App\Models\ServiceConfig;
use RuntimeException;

trait ResolvesServiceConfig
{
    protected function resolveConfig(string $service, ?ServiceConfig $config = null): ServiceConfig
    {
        $resolved = $config ?? ServiceConfig::for($service);

        if (! $resolved || ! $resolved->is_enabled) {
            throw new RuntimeException(ucfirst($service).' is not configured. Complete the setup wizard first.');
        }

        return $resolved;
    }

    protected function normalizedBaseUrl(ServiceConfig $config): string
    {
        return rtrim((string) $config->base_url, '/');
    }
}
