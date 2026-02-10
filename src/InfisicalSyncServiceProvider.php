<?php

namespace Quentin\InfisicalSync;

use Quentin\InfisicalSync\Commands\DiffCommand;
use Quentin\InfisicalSync\Commands\PullCommand;
use Quentin\InfisicalSync\Commands\PushCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class InfisicalSyncServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-infisical-sync')
            ->hasConfigFile('infisical-sync')
            ->hasCommands([
                PullCommand::class,
                PushCommand::class,
                DiffCommand::class,
            ]);
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(InfisicalClient::class, function () {
            return new InfisicalClient(
                url: (string) config('infisical-sync.url', 'https://app.infisical.com'),
                clientId: (string) config('infisical-sync.client_id', ''),
                clientSecret: (string) config('infisical-sync.client_secret', ''),
                projectId: (string) config('infisical-sync.project_id', ''),
                environment: (string) config('infisical-sync.environment', 'dev'),
                secretPath: (string) config('infisical-sync.secret_path', '/'),
            );
        });

        $this->app->singleton(InfisicalSyncManager::class, function ($app) {
            return new InfisicalSyncManager(
                client: $app->make(InfisicalClient::class),
                excludeKeys: (array) config('infisical-sync.exclude_keys', []),
            );
        });
    }
}
