<?php

use Quentin\InfisicalSync\InfisicalClient;

beforeEach(function () {
    $this->envFile = sys_get_temp_dir().'/test-push-'.uniqid().'.env';
    config()->set('infisical-sync.env_file', $this->envFile);

    $this->mockClient = Mockery::mock(InfisicalClient::class);
    $this->app->instance(InfisicalClient::class, $this->mockClient);
});

afterEach(function () {
    @unlink($this->envFile);
});

it('creates new secrets for local-only keys', function () {
    file_put_contents($this->envFile, "APP_KEY=value1\nNEW_KEY=value2");

    $remoteSecrets = [
        (object) ['secretKey' => 'APP_KEY', 'secretValue' => 'value1'],
    ];

    // Dry-run call first
    $this->mockClient
        ->shouldReceive('listSecrets')
        ->twice()
        ->andReturn($remoteSecrets);

    $this->mockClient
        ->shouldReceive('createSecret')
        ->once()
        ->with('NEW_KEY', 'value2', null, null);

    $this->artisan('infisical:push --force')
        ->assertExitCode(0);
});

it('updates secrets with different values', function () {
    file_put_contents($this->envFile, 'APP_KEY=new-value');

    $remoteSecrets = [
        (object) ['secretKey' => 'APP_KEY', 'secretValue' => 'old-value'],
    ];

    $this->mockClient
        ->shouldReceive('listSecrets')
        ->twice()
        ->andReturn($remoteSecrets);

    $this->mockClient
        ->shouldReceive('updateSecret')
        ->once()
        ->with('APP_KEY', 'new-value', null, null);

    $this->artisan('infisical:push --force')
        ->assertExitCode(0);
});

it('does not call API in dry-run mode', function () {
    file_put_contents($this->envFile, 'APP_KEY=new-value');

    $this->mockClient
        ->shouldReceive('listSecrets')
        ->once()
        ->andReturn([
            (object) ['secretKey' => 'APP_KEY', 'secretValue' => 'old-value'],
        ]);

    $this->mockClient->shouldNotReceive('createSecret');
    $this->mockClient->shouldNotReceive('updateSecret');

    $this->artisan('infisical:push --dry-run')
        ->assertExitCode(0);
});

it('shows no changes when already in sync', function () {
    file_put_contents($this->envFile, 'APP_KEY=value');

    $this->mockClient
        ->shouldReceive('listSecrets')
        ->once()
        ->andReturn([
            (object) ['secretKey' => 'APP_KEY', 'secretValue' => 'value'],
        ]);

    $this->artisan('infisical:push --force')
        ->assertExitCode(0);
});

it('excludes INFISICAL_* keys from push', function () {
    file_put_contents($this->envFile, "APP_KEY=value\nINFISICAL_CLIENT_ID=xxx");

    $this->mockClient
        ->shouldReceive('listSecrets')
        ->once()
        ->andReturn([]);

    // Only APP_KEY should be created, not INFISICAL_CLIENT_ID
    // Dry-run only since there are changes but we check the dry-run result
    $this->artisan('infisical:push --dry-run')
        ->doesntExpectOutputToContain('INFISICAL_CLIENT_ID')
        ->assertExitCode(0);
});

it('passes --env and --path options to the client', function () {
    file_put_contents($this->envFile, 'APP_KEY=value');

    $this->mockClient
        ->shouldReceive('listSecrets')
        ->twice()
        ->with('production', '/api')
        ->andReturn([]);

    $this->mockClient
        ->shouldReceive('createSecret')
        ->once()
        ->with('APP_KEY', 'value', 'production', '/api');

    $this->artisan('infisical:push --force --env=production --path=/api')
        ->assertExitCode(0);
});
