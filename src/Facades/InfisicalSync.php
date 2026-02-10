<?php

namespace Quentin\InfisicalSync\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Quentin\InfisicalSync\InfisicalSyncManager
 *
 * @method static \Quentin\InfisicalSync\DiffResult diff(string $envFilePath, ?string $environment = null, ?string $secretPath = null)
 * @method static \Quentin\InfisicalSync\PullResult pull(string $envFilePath, ?string $environment = null, ?string $secretPath = null, bool $dryRun = false, bool $backup = false)
 * @method static \Quentin\InfisicalSync\PushResult push(string $envFilePath, ?string $environment = null, ?string $secretPath = null, bool $dryRun = false, ?\Closure $onProgress = null)
 */
class InfisicalSync extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Quentin\InfisicalSync\InfisicalSyncManager::class;
    }
}
