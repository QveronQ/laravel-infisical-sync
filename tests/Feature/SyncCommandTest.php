<?php

use Quentin\InfisicalSync\InfisicalClient;

beforeEach(function () {
    $this->envFile = sys_get_temp_dir().'/test-sync-'.uniqid().'.env';
    config()->set('infisical-sync.env_file', $this->envFile);

    $this->mockClient = Mockery::mock(InfisicalClient::class);
    $this->app->instance(InfisicalClient::class, $this->mockClient);
});

afterEach(function () {
    @unlink($this->envFile);
    @unlink($this->envFile.'.backup');
});

it('shows no changes when already in sync', function () {
    file_put_contents($this->envFile, 'APP_KEY=value');

    $this->mockClient
        ->shouldReceive('listSecrets')
        ->once()
        ->andReturn([
            (object) ['secretKey' => 'APP_KEY', 'secretValue' => 'value'],
        ]);

    $this->artisan('infisical:sync --force')
        ->assertExitCode(0);
});

it('pushes local-only variables to Infisical', function () {
    file_put_contents($this->envFile, "APP_KEY=value\nLOCAL_ONLY=secret");

    $this->mockClient
        ->shouldReceive('listSecrets')
        ->twice()
        ->andReturn([
            (object) ['secretKey' => 'APP_KEY', 'secretValue' => 'value'],
        ]);

    $this->mockClient
        ->shouldReceive('createSecret')
        ->once()
        ->with('LOCAL_ONLY', 'secret', null, null);

    $this->artisan('infisical:sync --force')
        ->assertExitCode(0);
});

it('pulls remote-only variables to local .env', function () {
    file_put_contents($this->envFile, 'APP_KEY=value');

    $this->mockClient
        ->shouldReceive('listSecrets')
        ->twice()
        ->andReturn([
            (object) ['secretKey' => 'APP_KEY', 'secretValue' => 'value'],
            (object) ['secretKey' => 'REMOTE_ONLY', 'secretValue' => 'from-infisical'],
        ]);

    $this->artisan('infisical:sync --force')
        ->assertExitCode(0);

    $content = file_get_contents($this->envFile);
    expect($content)->toContain('REMOTE_ONLY=from-infisical');
});

it('skips conflicts by default', function () {
    file_put_contents($this->envFile, 'APP_KEY=local-value');

    $this->mockClient
        ->shouldReceive('listSecrets')
        ->once()
        ->andReturn([
            (object) ['secretKey' => 'APP_KEY', 'secretValue' => 'remote-value'],
        ]);

    $this->mockClient->shouldNotReceive('updateSecret');
    $this->mockClient->shouldNotReceive('createSecret');

    $this->artisan('infisical:sync --force')
        ->assertExitCode(0);

    expect(file_get_contents($this->envFile))->toBe('APP_KEY=local-value');
});

it('resolves conflicts with remote strategy', function () {
    file_put_contents($this->envFile, 'APP_KEY=local-value');

    $this->mockClient
        ->shouldReceive('listSecrets')
        ->twice()
        ->andReturn([
            (object) ['secretKey' => 'APP_KEY', 'secretValue' => 'remote-value'],
        ]);

    $this->artisan('infisical:sync --force --conflict=remote')
        ->assertExitCode(0);

    expect(file_get_contents($this->envFile))->toContain('APP_KEY=remote-value');
});

it('resolves conflicts with local strategy', function () {
    file_put_contents($this->envFile, 'APP_KEY=local-value');

    $this->mockClient
        ->shouldReceive('listSecrets')
        ->twice()
        ->andReturn([
            (object) ['secretKey' => 'APP_KEY', 'secretValue' => 'remote-value'],
        ]);

    $this->mockClient
        ->shouldReceive('updateSecret')
        ->once()
        ->with('APP_KEY', 'local-value', null, null);

    $this->artisan('infisical:sync --force --conflict=local')
        ->assertExitCode(0);

    expect(file_get_contents($this->envFile))->toContain('APP_KEY=local-value');
});

it('does not write in dry-run mode', function () {
    $originalContent = "APP_KEY=value\nLOCAL_ONLY=secret";
    file_put_contents($this->envFile, $originalContent);

    $this->mockClient
        ->shouldReceive('listSecrets')
        ->once()
        ->andReturn([
            (object) ['secretKey' => 'APP_KEY', 'secretValue' => 'value'],
            (object) ['secretKey' => 'REMOTE_ONLY', 'secretValue' => 'new'],
        ]);

    $this->mockClient->shouldNotReceive('createSecret');
    $this->mockClient->shouldNotReceive('updateSecret');

    $this->artisan('infisical:sync --dry-run')
        ->assertExitCode(0);

    expect(file_get_contents($this->envFile))->toBe($originalContent);
});

it('creates backup when --backup is passed', function () {
    $originalContent = 'APP_KEY=value';
    file_put_contents($this->envFile, $originalContent);

    $this->mockClient
        ->shouldReceive('listSecrets')
        ->twice()
        ->andReturn([
            (object) ['secretKey' => 'APP_KEY', 'secretValue' => 'value'],
            (object) ['secretKey' => 'NEW_KEY', 'secretValue' => 'new-value'],
        ]);

    $this->artisan('infisical:sync --force --backup')
        ->assertExitCode(0);

    expect(file_exists($this->envFile.'.backup'))->toBeTrue();
    expect(file_get_contents($this->envFile.'.backup'))->toBe($originalContent);
});

it('excludes INFISICAL_* keys', function () {
    file_put_contents($this->envFile, "APP_KEY=value\nINFISICAL_TOKEN=local-token");

    $this->mockClient
        ->shouldReceive('listSecrets')
        ->once()
        ->andReturn([
            (object) ['secretKey' => 'APP_KEY', 'secretValue' => 'value'],
            (object) ['secretKey' => 'INFISICAL_SECRET', 'secretValue' => 'should-not-sync'],
        ]);

    $this->mockClient->shouldNotReceive('createSecret');

    $this->artisan('infisical:sync --force')
        ->assertExitCode(0);

    expect(file_get_contents($this->envFile))->not->toContain('INFISICAL_SECRET');
});

it('rejects invalid conflict strategy', function () {
    file_put_contents($this->envFile, 'APP_KEY=value');

    $this->artisan('infisical:sync --conflict=invalid')
        ->assertExitCode(1);
});

it('uses config conflict strategy by default', function () {
    config()->set('infisical-sync.sync_conflict_strategy', 'remote');
    file_put_contents($this->envFile, 'APP_KEY=local-value');

    $this->mockClient
        ->shouldReceive('listSecrets')
        ->twice()
        ->andReturn([
            (object) ['secretKey' => 'APP_KEY', 'secretValue' => 'remote-value'],
        ]);

    $this->artisan('infisical:sync --force')
        ->assertExitCode(0);

    expect(file_get_contents($this->envFile))->toContain('APP_KEY=remote-value');
});
