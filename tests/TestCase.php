<?php

namespace Quentin\InfisicalSync\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Quentin\InfisicalSync\InfisicalSyncServiceProvider;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app)
    {
        return [
            InfisicalSyncServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('infisical-sync.url', 'https://app.infisical.com');
        config()->set('infisical-sync.client_id', 'test-client-id');
        config()->set('infisical-sync.client_secret', 'test-client-secret');
        config()->set('infisical-sync.project_id', 'test-project-id');
        config()->set('infisical-sync.environment', 'dev');
        config()->set('infisical-sync.secret_path', '/');
        config()->set('infisical-sync.exclude_keys', ['INFISICAL_*']);
        config()->set('infisical-sync.env_file', sys_get_temp_dir().'/test-infisical-sync.env');
    }
}
