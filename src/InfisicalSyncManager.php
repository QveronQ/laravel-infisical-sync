<?php

namespace Quentin\InfisicalSync;

use Closure;

class InfisicalSyncManager
{
    /**
     * @param  string[]  $excludeKeys
     */
    public function __construct(
        private readonly InfisicalClient $client,
        private readonly array $excludeKeys = [],
    ) {}

    public function isExcluded(string $key): bool
    {
        foreach ($this->excludeKeys as $pattern) {
            if (fnmatch($pattern, $key)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, string>  $variables
     * @return array<string, string>
     */
    public function filterExcluded(array $variables): array
    {
        return array_filter(
            $variables,
            fn (string $key) => ! $this->isExcluded($key),
            ARRAY_FILTER_USE_KEY,
        );
    }

    public function pull(
        string $envFilePath,
        ?string $environment = null,
        ?string $secretPath = null,
        bool $dryRun = false,
        bool $backup = false,
    ): PullResult {
        $envFile = (new EnvFile($envFilePath))->parse();

        $remoteSecrets = $this->client->listSecrets($environment, $secretPath);
        $remoteMap = $this->buildRemoteMap($remoteSecrets);
        $remoteMap = $this->filterExcluded($remoteMap);

        $created = [];
        $updated = [];
        $unchanged = [];

        foreach ($remoteMap as $key => $value) {
            if (! $envFile->has($key)) {
                $created[] = $key;
            } elseif ($envFile->get($key) !== $value) {
                $updated[] = $key;
            } else {
                $unchanged[] = $key;
            }
        }

        if (! $dryRun) {
            if ($backup) {
                $envFile->backup();
            }

            foreach ($remoteMap as $key => $value) {
                if (in_array($key, $created, true) || in_array($key, $updated, true)) {
                    $envFile->set($key, $value);
                }
            }

            $envFile->write();
        }

        return new PullResult(
            created: $created,
            updated: $updated,
            unchanged: $unchanged,
            remoteValues: $remoteMap,
            localValues: $envFile->variables(),
        );
    }

    public function push(
        string $envFilePath,
        ?string $environment = null,
        ?string $secretPath = null,
        bool $dryRun = false,
        ?Closure $onProgress = null,
    ): PushResult {
        $envFile = (new EnvFile($envFilePath))->parse();
        $localVars = $this->filterExcluded($envFile->variables());

        $remoteSecrets = $this->client->listSecrets($environment, $secretPath);
        $remoteMap = $this->buildRemoteMap($remoteSecrets);

        $created = [];
        $updated = [];
        $unchanged = [];

        foreach ($localVars as $key => $value) {
            if (! isset($remoteMap[$key])) {
                $created[] = $key;

                if (! $dryRun) {
                    $this->client->createSecret($key, $value, $environment, $secretPath);
                    if ($onProgress) {
                        $onProgress($key);
                    }
                }
            } elseif ($remoteMap[$key] !== $value) {
                $updated[] = $key;

                if (! $dryRun) {
                    $this->client->updateSecret($key, $value, $environment, $secretPath);
                    if ($onProgress) {
                        $onProgress($key);
                    }
                }
            } else {
                $unchanged[] = $key;
            }
        }

        return new PushResult(
            created: $created,
            updated: $updated,
            unchanged: $unchanged,
        );
    }

    public function diff(
        string $envFilePath,
        ?string $environment = null,
        ?string $secretPath = null,
    ): DiffResult {
        $envFile = (new EnvFile($envFilePath))->parse();
        $localVars = $this->filterExcluded($envFile->variables());

        $remoteSecrets = $this->client->listSecrets($environment, $secretPath);
        $remoteMap = $this->filterExcluded($this->buildRemoteMap($remoteSecrets));

        $localOnly = array_diff_key($localVars, $remoteMap);
        $remoteOnly = array_diff_key($remoteMap, $localVars);
        $different = [];
        $same = [];

        foreach (array_intersect_key($localVars, $remoteMap) as $key => $localValue) {
            if ($localValue !== $remoteMap[$key]) {
                $different[$key] = [
                    'local' => $localValue,
                    'remote' => $remoteMap[$key],
                ];
            } else {
                $same[] = $key;
            }
        }

        return new DiffResult(
            localOnly: $localOnly,
            remoteOnly: $remoteOnly,
            different: $different,
            same: $same,
        );
    }

    public function client(): InfisicalClient
    {
        return $this->client;
    }

    /**
     * @param  array<int, object>  $secrets
     * @return array<string, string>
     */
    private function buildRemoteMap(array $secrets): array
    {
        $map = [];
        foreach ($secrets as $secret) {
            $map[$secret->secretKey] = $secret->secretValue;
        }

        return $map;
    }
}
