<?php

namespace Quentin\InfisicalSync\Commands;

use Illuminate\Console\Command;
use Quentin\InfisicalSync\InfisicalSyncManager;
use Quentin\InfisicalSync\SyncResult;

class SyncCommand extends Command
{
    protected $signature = 'infisical:sync
        {--env= : Infisical environment (overrides config)}
        {--path= : Infisical secret path (overrides config)}
        {--conflict= : Conflict resolution strategy: remote, local, skip (overrides config)}
        {--force : Skip confirmation prompt}
        {--backup : Create .env.backup before writing}
        {--dry-run : Show what would change without writing}
        {--show-values : Show actual secret values instead of masking}
        {--delete : Remove local-only variables from .env instead of pushing to Infisical}
        {--no-config-cache : Skip running config:cache after sync}';

    protected $description = 'Bidirectional sync between local .env and Infisical';

    public function handle(InfisicalSyncManager $manager): int
    {
        $envFile = config('infisical-sync.env_file');
        $environment = $this->option('env');
        $secretPath = $this->option('path');
        $conflictStrategy = $this->option('conflict') ?? config('infisical-sync.sync_conflict_strategy', 'skip');
        $force = (bool) $this->option('force');
        $backup = (bool) $this->option('backup');
        $dryRun = (bool) $this->option('dry-run');
        $showValues = (bool) $this->option('show-values');
        $delete = (bool) $this->option('delete');

        if (! in_array($conflictStrategy, ['remote', 'local', 'skip'], true)) {
            $this->components->error("Invalid conflict strategy: {$conflictStrategy}. Use: remote, local, or skip.");

            return self::FAILURE;
        }

        try {
            $result = $manager->sync($envFile, $environment, $secretPath, $conflictStrategy, dryRun: true);
        } catch (\Throwable $e) {
            $this->components->error("Failed to sync secrets: {$e->getMessage()}");

            return self::FAILURE;
        }

        $hasLocalOnly = count($result->pushed) > 0;

        if (! $result->hasChanges() && count($result->conflictsSkipped) === 0 && ! $hasLocalOnly) {
            $this->components->info('Everything is in sync. No changes needed.');

            return self::SUCCESS;
        }

        $deleteKeys = [];

        if ($hasLocalOnly) {
            $this->displayLocalOnly($result, $showValues);

            if (! $dryRun && ($delete || (! $force && $this->components->confirm('Remove these local-only variables from .env instead of pushing to Infisical?', false)))) {
                $deleteKeys = $result->pushed;
            }
        }

        $this->displayChanges($result, $showValues);

        if ($dryRun) {
            $this->newLine();
            $this->components->warn('Dry run complete. No changes were applied.');

            return self::SUCCESS;
        }

        $totalWithoutLocalOnly = count($result->pulled) + count($result->conflictsResolved);
        $totalPush = count($deleteKeys) > 0 ? 0 : count($result->pushed);

        if (($totalWithoutLocalOnly + $totalPush) === 0 && count($deleteKeys) === 0 && count($result->conflictsSkipped) === 0) {
            return self::SUCCESS;
        }

        if (count($deleteKeys) === 0 && ($totalWithoutLocalOnly + $totalPush) > 0) {
            if (! $force && ! $this->components->confirm('Apply these changes?')) {
                $this->components->info('Operation cancelled.');

                return self::SUCCESS;
            }
        }

        try {
            $total = $totalPush + $totalWithoutLocalOnly + count($deleteKeys);

            if ($total === 0) {
                $this->components->info(sprintf(
                    'Done! 0 pushed, 0 pulled, 0 deleted, 0 conflicts resolved, %d skipped, %d unchanged.',
                    count($result->conflictsSkipped),
                    count($result->unchanged),
                ));

                return self::SUCCESS;
            }

            $bar = $this->output->createProgressBar($total);
            $bar->start();

            $result = $manager->sync(
                $envFile,
                $environment,
                $secretPath,
                $conflictStrategy,
                dryRun: false,
                backup: $backup,
                onProgress: function () use ($bar) {
                    $bar->advance();
                },
                deleteKeys: $deleteKeys,
            );

            $bar->finish();
            $this->newLine(2);
        } catch (\Throwable $e) {
            $this->newLine();
            $this->components->error("Failed to sync secrets: {$e->getMessage()}");

            return self::FAILURE;
        }

        if ($backup) {
            $this->components->info('Backup created at '.$envFile.'.backup');
        }

        $this->components->info(sprintf(
            'Done! %d pushed, %d pulled, %d deleted, %d conflicts resolved, %d skipped, %d unchanged.',
            count($result->pushed),
            count($result->pulled),
            count($result->deleted),
            count($result->conflictsResolved),
            count($result->conflictsSkipped),
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

    private function displayLocalOnly(SyncResult $result, bool $showValues): void
    {
        $this->newLine();
        $this->components->warn('Variables in local .env but not in Infisical:');
        $this->table(
            ['Key', 'Value'],
            collect($result->pushed)->map(fn (string $key) => [
                $key,
                $showValues ? ($result->localValues[$key] ?? '') : '***',
            ])->all(),
        );
    }

    private function displayChanges(SyncResult $result, bool $showValues): void
    {
        if (count($result->pulled) > 0) {
            $this->newLine();
            $this->components->warn('Remote-only variables (will be pulled to .env):');
            $this->table(
                ['Key', 'Value'],
                collect($result->pulled)->map(fn (string $key) => [
                    $key,
                    $showValues ? ($result->remoteValues[$key] ?? '') : '***',
                ])->all(),
            );
        }

        if (count($result->conflictsResolved) > 0) {
            $this->newLine();
            $this->components->warn('Conflicts (will be resolved with strategy):');
            $this->table(
                ['Key', 'Local', 'Remote'],
                collect($result->conflictsResolved)->map(fn (string $key) => [
                    $key,
                    $showValues ? ($result->localValues[$key] ?? '') : '***',
                    $showValues ? ($result->remoteValues[$key] ?? '') : '***',
                ])->all(),
            );
        }

        if (count($result->conflictsSkipped) > 0) {
            $this->newLine();
            $this->components->warn('Conflicts (skipped — different values on both sides):');
            $this->table(
                ['Key', 'Local', 'Remote'],
                collect($result->conflictsSkipped)->map(fn (string $key) => [
                    $key,
                    $showValues ? ($result->localValues[$key] ?? '') : '***',
                    $showValues ? ($result->remoteValues[$key] ?? '') : '***',
                ])->all(),
            );
        }
    }
}
