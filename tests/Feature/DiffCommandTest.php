<?php

use Quentin\InfisicalSync\InfisicalClient;

beforeEach(function () {
    $this->envFile = sys_get_temp_dir().'/test-diff-'.uniqid().'.env';
    config()->set('infisical-sync.env_file', $this->envFile);

    $this->mockClient = Mockery::mock(InfisicalClient::class);
    $this->app->instance(InfisicalClient::class, $this->mockClient);
});

afterEach(function () {
    @unlink($this->envFile);
});

it('shows no differences when in sync', function () {
    file_put_contents($this->envFile, "APP_KEY=value1\nDB_HOST=localhost");

    $this->mockClient
        ->shouldReceive('listSecrets')
        ->once()
        ->andReturn([
            (object) ['secretKey' => 'APP_KEY', 'secretValue' => 'value1'],
            (object) ['secretKey' => 'DB_HOST', 'secretValue' => 'localhost'],
        ]);

    $this->artisan('infisical:diff')
        ->assertExitCode(0);
});

it('shows local-only variables with exit code 2', function () {
    file_put_contents($this->envFile, "APP_KEY=value1\nDB_HOST=localhost");

    $this->mockClient
        ->shouldReceive('listSecrets')
        ->once()
        ->andReturn([
            (object) ['secretKey' => 'APP_KEY', 'secretValue' => 'value1'],
        ]);

    $this->artisan('infisical:diff')
        ->assertExitCode(2);
});

it('shows remote-only variables with exit code 2', function () {
    file_put_contents($this->envFile, 'APP_KEY=value1');

    $this->mockClient
        ->shouldReceive('listSecrets')
        ->once()
        ->andReturn([
            (object) ['secretKey' => 'APP_KEY', 'secretValue' => 'value1'],
            (object) ['secretKey' => 'NEW_SECRET', 'secretValue' => 'remote-value'],
        ]);

    $this->artisan('infisical:diff')
        ->assertExitCode(2);
});

it('shows variables with different values', function () {
    file_put_contents($this->envFile, 'APP_KEY=local-value');

    $this->mockClient
        ->shouldReceive('listSecrets')
        ->once()
        ->andReturn([
            (object) ['secretKey' => 'APP_KEY', 'secretValue' => 'remote-value'],
        ]);

    $this->artisan('infisical:diff')
        ->assertExitCode(2);
});

it('masks values by default', function () {
    file_put_contents($this->envFile, 'APP_KEY=local-value');

    $this->mockClient
        ->shouldReceive('listSecrets')
        ->once()
        ->andReturn([
            (object) ['secretKey' => 'APP_KEY', 'secretValue' => 'remote-value'],
        ]);

    $this->artisan('infisical:diff')
        ->expectsOutputToContain('***')
        ->doesntExpectOutputToContain('local-value')
        ->doesntExpectOutputToContain('remote-value')
        ->assertExitCode(2);
});

it('shows values with --show-values flag', function () {
    file_put_contents($this->envFile, 'APP_KEY=local-value');

    $this->mockClient
        ->shouldReceive('listSecrets')
        ->once()
        ->andReturn([
            (object) ['secretKey' => 'APP_KEY', 'secretValue' => 'remote-value'],
        ]);

    $exitCode = $this->withoutMockingConsoleOutput()
        ->artisan('infisical:diff', ['--show-values' => true]);

    $output = \Illuminate\Support\Facades\Artisan::output();

    expect($output)->toContain('local-value');
    expect($output)->toContain('remote-value');
    expect($exitCode)->toBe(2);
});

it('excludes INFISICAL_* keys', function () {
    file_put_contents($this->envFile, "APP_KEY=value\nINFISICAL_CLIENT_ID=xxx");

    $this->mockClient
        ->shouldReceive('listSecrets')
        ->once()
        ->andReturn([
            (object) ['secretKey' => 'APP_KEY', 'secretValue' => 'value'],
            (object) ['secretKey' => 'INFISICAL_CLIENT_ID', 'secretValue' => 'yyy'],
        ]);

    // Both are excluded, so APP_KEY matches and INFISICAL_* is ignored → no diff
    $this->artisan('infisical:diff')
        ->assertExitCode(0);
});

it('passes --env and --path options to the client', function () {
    file_put_contents($this->envFile, 'APP_KEY=value');

    $this->mockClient
        ->shouldReceive('listSecrets')
        ->once()
        ->with('staging', '/backend')
        ->andReturn([
            (object) ['secretKey' => 'APP_KEY', 'secretValue' => 'value'],
        ]);

    $this->artisan('infisical:diff --env=staging --path=/backend')
        ->assertExitCode(0);
});
