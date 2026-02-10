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
        {--show-values : Show actual secret values instead of masking}';

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

        try {
            $result = $manager->pull($envFile, $environment, $secretPath, dryRun: true);
        } catch (\Throwable $e) {
            $this->components->error("Failed to pull secrets: {$e->getMessage()}");

            return self::FAILURE;
        }

        if (! $result->hasChanges()) {
            $this->components->info('No changes to apply. Local .env is already up to date.');

            return self::SUCCESS;
        }

        $this->displayChanges($result->created, $result->updated, $result->remoteValues, $showValues);

        if ($dryRun) {
            $this->newLine();
            $this->components->warn('Dry run complete. No changes were written.');

            return self::SUCCESS;
        }

        if (! $force && ! $this->components->confirm('Apply these changes to your .env file?')) {
            $this->components->info('Operation cancelled.');

            return self::SUCCESS;
        }

        try {
            $result = $manager->pull($envFile, $environment, $secretPath, dryRun: false, backup: $backup);
        } catch (\Throwable $e) {
            $this->components->error("Failed to write .env: {$e->getMessage()}");

            return self::FAILURE;
        }

        if ($backup) {
            $this->components->info('Backup created at '.$envFile.'.backup');
        }

        $this->components->info(sprintf(
            'Done! %d created, %d updated, %d unchanged.',
            count($result->created),
            count($result->updated),
            count($result->unchanged),
        ));

        return self::SUCCESS;
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
