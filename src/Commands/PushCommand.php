<?php

namespace Quentin\InfisicalSync\Commands;

use Illuminate\Console\Command;
use Quentin\InfisicalSync\InfisicalSyncManager;

class PushCommand extends Command
{
    protected $signature = 'infisical:push
        {--env= : Infisical environment (overrides config)}
        {--path= : Infisical secret path (overrides config)}
        {--force : Skip confirmation prompt}
        {--dry-run : Show what would be pushed without sending}
        {--show-values : Show actual secret values instead of masking}';

    protected $description = 'Push local .env variables to Infisical';

    public function handle(InfisicalSyncManager $manager): int
    {
        $envFile = config('infisical-sync.env_file');
        $environment = $this->option('env');
        $secretPath = $this->option('path');
        $force = (bool) $this->option('force');
        $dryRun = (bool) $this->option('dry-run');
        $showValues = (bool) $this->option('show-values');

        // First, dry-run to preview changes
        try {
            $result = $manager->push($envFile, $environment, $secretPath, dryRun: true);
        } catch (\Throwable $e) {
            $this->components->error("Failed to read secrets: {$e->getMessage()}");

            return self::FAILURE;
        }

        if (! $result->hasChanges()) {
            $this->components->info('No changes to push. Infisical is already up to date.');

            return self::SUCCESS;
        }

        $this->displayChanges($result, $envFile, $showValues);

        if ($dryRun) {
            $this->newLine();
            $this->components->warn('Dry run complete. Nothing was pushed.');

            return self::SUCCESS;
        }

        if (! $force && ! $this->components->confirm('Push these changes to Infisical?')) {
            $this->components->info('Operation cancelled.');

            return self::SUCCESS;
        }

        // Actual push with progress
        try {
            $total = count($result->created) + count($result->updated);
            $bar = $this->output->createProgressBar($total);
            $bar->start();

            $result = $manager->push($envFile, $environment, $secretPath, dryRun: false, onProgress: function () use ($bar) {
                $bar->advance();
            });

            $bar->finish();
            $this->newLine(2);
        } catch (\Throwable $e) {
            $this->newLine();
            $this->components->error("Failed to push secrets: {$e->getMessage()}");

            return self::FAILURE;
        }

        $this->components->info(sprintf(
            'Done! %d created, %d updated, %d unchanged.',
            count($result->created),
            count($result->updated),
            count($result->unchanged),
        ));

        return self::SUCCESS;
    }

    private function displayChanges(\Quentin\InfisicalSync\PushResult $result, string $envFile, bool $showValues): void
    {
        $envFileObj = (new \Quentin\InfisicalSync\EnvFile($envFile))->parse();
        $localVars = $envFileObj->variables();

        if (count($result->created) > 0) {
            $this->newLine();
            $this->components->warn('New secrets to create in Infisical:');
            $this->table(
                ['Key', 'Value'],
                collect($result->created)->map(fn (string $key) => [
                    $key,
                    $showValues ? ($localVars[$key] ?? '') : '***',
                ])->all(),
            );
        }

        if (count($result->updated) > 0) {
            $this->newLine();
            $this->components->warn('Secrets to update in Infisical:');
            $this->table(
                ['Key', 'New Value'],
                collect($result->updated)->map(fn (string $key) => [
                    $key,
                    $showValues ? ($localVars[$key] ?? '') : '***',
                ])->all(),
            );
        }
    }
}
