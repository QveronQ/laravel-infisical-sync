<?php

namespace Quentin\InfisicalSync\Commands;

use Illuminate\Console\Command;
use Quentin\InfisicalSync\InfisicalSyncManager;

class DiffCommand extends Command
{
    protected $signature = 'infisical:diff
        {--env= : Infisical environment (overrides config)}
        {--path= : Infisical secret path (overrides config)}
        {--show-values : Show actual secret values instead of masking}';

    protected $description = 'Compare local .env variables with Infisical secrets';

    public function handle(InfisicalSyncManager $manager): int
    {
        $envFile = config('infisical-sync.env_file');
        $environment = $this->option('env');
        $secretPath = $this->option('path');
        $showValues = (bool) $this->option('show-values');

        try {
            $result = $manager->diff($envFile, $environment, $secretPath);
        } catch (\Throwable $e) {
            $this->components->error("Failed to compare: {$e->getMessage()}");

            return self::FAILURE;
        }

        if (! $result->hasDifferences()) {
            $this->components->info('No differences found. Local .env and Infisical are in sync.');

            return self::SUCCESS;
        }

        if (count($result->localOnly) > 0) {
            $this->newLine();
            $this->components->warn('Variables only in local .env (would be pushed):');
            $this->table(
                ['Key', 'Value'],
                collect($result->localOnly)->map(fn (string $value, string $key) => [
                    $key,
                    $showValues ? $value : '***',
                ])->values()->all(),
            );
        }

        if (count($result->remoteOnly) > 0) {
            $this->newLine();
            $this->components->warn('Variables only in Infisical (would be pulled):');
            $this->table(
                ['Key', 'Value'],
                collect($result->remoteOnly)->map(fn (string $value, string $key) => [
                    $key,
                    $showValues ? $value : '***',
                ])->values()->all(),
            );
        }

        if (count($result->different) > 0) {
            $this->newLine();
            $this->components->warn('Variables with different values:');
            $this->table(
                ['Key', 'Local', 'Remote'],
                collect($result->different)->map(fn (array $values, string $key) => [
                    $key,
                    $showValues ? $values['local'] : '***',
                    $showValues ? $values['remote'] : '***',
                ])->values()->all(),
            );
        }

        $total = count($result->localOnly) + count($result->remoteOnly) + count($result->different);
        $this->newLine();
        $this->components->info("Found {$total} difference(s).");

        return 2;
    }
}
