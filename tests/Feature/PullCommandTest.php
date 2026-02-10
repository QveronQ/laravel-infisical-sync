<?php

use Quentin\InfisicalSync\InfisicalClient;

beforeEach(function () {
    $this->envFile = sys_get_temp_dir().'/test-pull-'.uniqid().'.env';
    config()->set('infisical-sync.env_file', $this->envFile);

    $this->mockClient = Mockery::mock(InfisicalClient::class);
    $this->app->instance(InfisicalClient::class, $this->mockClient);
});

afterEach(function () {
    @unlink($this->envFile);
    @unlink($this->envFile.'.backup');
});

it('pulls secrets and updates .env with --force', function () {
    file_put_contents($this->envFile, 'APP_KEY=old-value');

    $remoteSecrets = [
        (object) ['secretKey' => 'APP_KEY', 'secretValue' => 'new-value'],
        (object) ['secretKey' => 'NEW_SECRET', 'secretValue' => 'added'],
    ];

    $this->mockClient
        ->shouldReceive('listSecrets')
        ->twice()
        ->andReturn($remoteSecrets);

    $this->artisan('infisical:pull --force')
        ->assertExitCode(0);

    $content = file_get_contents($this->envFile);
    expect($content)->toContain('APP_KEY=new-value');
    expect($content)->toContain('NEW_SECRET=added');
});

it('shows no changes when already in sync', function () {
    file_put_contents($this->envFile, 'APP_KEY=value');

    $this->mockClient
        ->shouldReceive('listSecrets')
        ->once()
        ->andReturn([
            (object) ['secretKey' => 'APP_KEY', 'secretValue' => 'value'],
        ]);

    $this->artisan('infisical:pull --force')
        ->assertExitCode(0);
});

it('does not write in dry-run mode', function () {
    $originalContent = 'APP_KEY=old-value';
    file_put_contents($this->envFile, $originalContent);

    $this->mockClient
        ->shouldReceive('listSecrets')
        ->once()
        ->andReturn([
            (object) ['secretKey' => 'APP_KEY', 'secretValue' => 'new-value'],
        ]);

    $this->artisan('infisical:pull --dry-run')
        ->assertExitCode(0);

    expect(file_get_contents($this->envFile))->toBe($originalContent);
});

it('creates backup when --backup is passed', function () {
    $originalContent = 'APP_KEY=old-value';
    file_put_contents($this->envFile, $originalContent);

    $this->mockClient
        ->shouldReceive('listSecrets')
        ->twice()
        ->andReturn([
            (object) ['secretKey' => 'APP_KEY', 'secretValue' => 'new-value'],
        ]);

    $this->artisan('infisical:pull --force --backup')
        ->assertExitCode(0);

    expect(file_exists($this->envFile.'.backup'))->toBeTrue();
    expect(file_get_contents($this->envFile.'.backup'))->toBe($originalContent);
});

it('preserves comments and blank lines after pull', function () {
    $content = <<<'ENV'
# Application config
APP_NAME=MyApp

# Database
DB_HOST=localhost
ENV;

    file_put_contents($this->envFile, $content);

    $this->mockClient
        ->shouldReceive('listSecrets')
        ->twice()
        ->andReturn([
            (object) ['secretKey' => 'APP_NAME', 'secretValue' => 'UpdatedApp'],
            (object) ['secretKey' => 'DB_HOST', 'secretValue' => 'localhost'],
            (object) ['secretKey' => 'NEW_KEY', 'secretValue' => 'new-value'],
        ]);

    $this->artisan('infisical:pull --force')
        ->assertExitCode(0);

    $result = file_get_contents($this->envFile);
    expect($result)->toContain('# Application config');
    expect($result)->toContain('# Database');
    expect($result)->toContain('APP_NAME=UpdatedApp');
    expect($result)->toContain('NEW_KEY=new-value');
});

it('excludes INFISICAL_* keys from pull', function () {
    file_put_contents($this->envFile, 'APP_KEY=value');

    $this->mockClient
        ->shouldReceive('listSecrets')
        ->once()
        ->andReturn([
            (object) ['secretKey' => 'APP_KEY', 'secretValue' => 'value'],
            (object) ['secretKey' => 'INFISICAL_TOKEN', 'secretValue' => 'should-not-appear'],
        ]);

    $this->artisan('infisical:pull --force')
        ->assertExitCode(0);

    $content = file_get_contents($this->envFile);
    expect($content)->not->toContain('INFISICAL_TOKEN');
});
