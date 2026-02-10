<?php

use Quentin\InfisicalSync\InfisicalClient;
use Quentin\InfisicalSync\InfisicalSyncManager;

it('excludes keys matching wildcard patterns', function () {
    $client = Mockery::mock(InfisicalClient::class);

    $manager = new InfisicalSyncManager(
        client: $client,
        excludeKeys: ['INFISICAL_*', 'AWS_*'],
    );

    expect($manager->isExcluded('INFISICAL_CLIENT_ID'))->toBeTrue();
    expect($manager->isExcluded('INFISICAL_SECRET'))->toBeTrue();
    expect($manager->isExcluded('AWS_ACCESS_KEY'))->toBeTrue();
    expect($manager->isExcluded('APP_KEY'))->toBeFalse();
    expect($manager->isExcluded('DB_PASSWORD'))->toBeFalse();
});

it('does not exclude when no patterns are set', function () {
    $client = Mockery::mock(InfisicalClient::class);

    $manager = new InfisicalSyncManager(
        client: $client,
        excludeKeys: [],
    );

    expect($manager->isExcluded('INFISICAL_CLIENT_ID'))->toBeFalse();
    expect($manager->isExcluded('APP_KEY'))->toBeFalse();
});

it('filters excluded keys from an array', function () {
    $client = Mockery::mock(InfisicalClient::class);

    $manager = new InfisicalSyncManager(
        client: $client,
        excludeKeys: ['INFISICAL_*'],
    );

    $filtered = $manager->filterExcluded([
        'APP_KEY' => 'abc',
        'INFISICAL_CLIENT_ID' => 'xxx',
        'DB_HOST' => 'localhost',
        'INFISICAL_SECRET' => 'yyy',
    ]);

    expect($filtered)->toHaveKeys(['APP_KEY', 'DB_HOST']);
    expect($filtered)->not->toHaveKey('INFISICAL_CLIENT_ID');
    expect($filtered)->not->toHaveKey('INFISICAL_SECRET');
});

it('supports question mark wildcards', function () {
    $client = Mockery::mock(InfisicalClient::class);

    $manager = new InfisicalSyncManager(
        client: $client,
        excludeKeys: ['DB_?OST'],
    );

    expect($manager->isExcluded('DB_HOST'))->toBeTrue();
    expect($manager->isExcluded('DB_POST'))->toBeTrue();
    expect($manager->isExcluded('DB_PASSWORD'))->toBeFalse();
});
