<?php

namespace Quentin\InfisicalSync\Commands;

use Illuminate\Console\Command;
use Quentin\InfisicalSync\InfisicalSyncManager;

class PullCommand extends Command
{
    protected $signature = 'infisical:pull
        {--env= : Infisical environment (overrides config)}
        {--path= : Infisical secret path (overrides config)}
        {--force : Skip confirmation prompt}
        {--backup : Create .env.backup before writing}
        {--dry-run : Show what would change without writing}
        {--show-values : Show actual secret values instead of masking}
        {--delete : Also remove local variables not present in Infisical}
        {--no-config-cache : Skip running config:cache after pull}';

    protected $description = 'Pull secrets from Infisical and update the local .env file';

    public function handle(InfisicalSyncManager $manager): int
    {
        $envFile = config('infisical-sync.env_file');
        $environment = $this->option('env');
        $secretPath = $this->option('path');
        $force = (bool) $this->option('force');
        $backup = (bool) $this->option('backup');
        $dryRun = (bool) $this->option('dry-run');
        $showValues = (bool) $this->option('show-values');
        $delete = (bool) $this->option('delete');

        try {
            $result = $manager->pull($envFile, $environment, $secretPath, dryRun: true);
        } catch (\Throwable $e) {
            $this->components->error("Failed to pull secrets: {$e->getMessage()}");

            return self::FAILURE;
        }

        $hasLocalOnly = count($result->localOnly) > 0;

        if (! $result->hasChanges() && ! $hasLocalOnly) {
            $this->components->info('No changes to apply. Local .env is already up to date.');

            return self::SUCCESS;
        }

        $this->displayChanges($result->created, $result->updated, $result->remoteValues, $showValues);

        $deleteKeys = [];

        if ($hasLocalOnly) {
            $this->newLine();
            $this->components->warn('Variables in local .env but not in Infisical:');
            $this->table(
                ['Key', 'Value'],
                collect($result->localOnly)->map(fn (string $key) => [
                    $key,
                    $showValues ? ($result->localValues[$key] ?? '') : '***',
                ])->all(),
            );

            if (! $dryRun && ($delete || (! $force && $this->components->confirm('Remove these variables from local .env?', false)))) {
                $deleteKeys = $result->localOnly;
            }
        }

        if ($dryRun) {
            $this->newLine();
            $this->components->warn('Dry run complete. No changes were written.');

            return self::SUCCESS;
        }

        if (! $result->hasChanges() && count($deleteKeys) === 0) {
            return self::SUCCESS;
        }

        if (! $force && count($deleteKeys) === 0 && ! $this->components->confirm('Apply these changes to your .env file?')) {
            $this->components->info('Operation cancelled.');

            return self::SUCCESS;
        }

        try {
            $result = $manager->pull($envFile, $environment, $secretPath, dryRun: false, backup: $backup, deleteKeys: $deleteKeys);
        } catch (\Throwable $e) {
            $this->components->error("Failed to write .env: {$e->getMessage()}");

            return self::FAILURE;
        }

        if ($backup) {
            $this->components->info('Backup created at '.$envFile.'.backup');
        }

        $this->components->info(sprintf(
            'Done! %d created, %d updated, %d deleted, %d unchanged.',
            count($result->created),
            count($result->updated),
            count($result->deleted),
            count($result->unchanged),
        ));

        $this->runConfigCache();

        return self::SUCCESS;
    }

    private function runConfigCache(): void
    {
        if ($this->option('no-config-cache') || ! config('infisical-sync.config_cache_after_sync', true)) {
            return;
        }

        $this->call('config:cache');
        $this->components->info('Config cache refreshed.');
    }

    /**
     * @param  string[]  $created
     * @param  string[]  $updated
     * @param  array<string, string>  $remoteValues
     */
    private function displayChanges(array $created, array $updated, array $remoteValues, bool $showValues): void
    {
        if (count($created) > 0) {
            $this->newLine();
            $this->components->warn('New variables to add:');
            $this->table(
                ['Key', 'Value'],
                collect($created)->map(fn (string $key) => [
                    $key,
                    $showValues ? ($remoteValues[$key] ?? '') : '***',
                ])->all(),
            );
        }

        if (count($updated) > 0) {
            $this->newLine();
            $this->components->warn('Variables to update:');
            $this->table(
                ['Key', 'New Value'],
                collect($updated)->map(fn (string $key) => [
                    $key,
                    $showValues ? ($remoteValues[$key] ?? '') : '***',
                ])->all(),
            );
        }
    }
}
