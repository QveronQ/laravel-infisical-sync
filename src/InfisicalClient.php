<?php

namespace Quentin\InfisicalSync;

use Infisical\SDK\InfisicalSDK;
use Infisical\SDK\Models\CreateSecretParameters;
use Infisical\SDK\Models\DeleteSecretParameters;
use Infisical\SDK\Models\GetSecretParameters;
use Infisical\SDK\Models\ListSecretsParameters;
use Infisical\SDK\Models\UpdateSecretParameters;

class InfisicalClient
{
    private InfisicalSDK $sdk;

    private bool $authenticated = false;

    public function __construct(
        private readonly string $url,
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly string $projectId,
        private readonly string $environment,
        private readonly string $secretPath = '/',
        ?InfisicalSDK $sdk = null,
    ) {
        $this->sdk = $sdk ?? new InfisicalSDK($this->url);
    }

    private function authenticate(): void
    {
        if ($this->authenticated) {
            return;
        }

        $this->sdk->auth()->universalAuth()->login(
            $this->clientId,
            $this->clientSecret,
        );

        $this->authenticated = true;
    }

    /**
     * @return array<int, object>
     */
    public function listSecrets(?string $environment = null, ?string $secretPath = null): array
    {
        $this->authenticate();

        return $this->sdk->secrets()->list(new ListSecretsParameters(
            environment: $environment ?? $this->environment,
            secretPath: $secretPath ?? $this->secretPath,
            projectId: $this->projectId,
        ));
    }

    public function getSecret(string $key, ?string $environment = null, ?string $secretPath = null): object
    {
        $this->authenticate();

        return $this->sdk->secrets()->get(new GetSecretParameters(
            secretKey: $key,
            environment: $environment ?? $this->environment,
            projectId: $this->projectId,
            secretPath: $secretPath ?? $this->secretPath,
        ));
    }

    public function createSecret(string $key, string $value, ?string $environment = null, ?string $secretPath = null): object
    {
        $this->authenticate();

        return $this->sdk->secrets()->create(new CreateSecretParameters(
            secretKey: $key,
            secretValue: $value,
            environment: $environment ?? $this->environment,
            projectId: $this->projectId,
            secretPath: $secretPath ?? $this->secretPath,
        ));
    }

    public function updateSecret(string $key, string $newValue, ?string $environment = null, ?string $secretPath = null): object
    {
        $this->authenticate();

        return $this->sdk->secrets()->update(new UpdateSecretParameters(
            secretKey: $key,
            newSecretValue: $newValue,
            environment: $environment ?? $this->environment,
            projectId: $this->projectId,
            secretPath: $secretPath ?? $this->secretPath,
        ));
    }

    public function deleteSecret(string $key, ?string $environment = null, ?string $secretPath = null): object
    {
        $this->authenticate();

        return $this->sdk->secrets()->delete(new DeleteSecretParameters(
            secretKey: $key,
            environment: $environment ?? $this->environment,
            projectId: $this->projectId,
            secretPath: $secretPath ?? $this->secretPath,
        ));
    }
}
