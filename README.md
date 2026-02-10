
# Laravel Infisical Sync

Sync your Laravel `.env` secrets with [Infisical](https://infisical.com). Push, pull, diff and sync environment variables between your `.env` file and your Infisical vault using the official PHP SDK.

[![Latest Version on Packagist](https://img.shields.io/packagist/v/qveronq/laravel-infisical-sync.svg?style=flat-square)](https://packagist.org/packages/qveronq/laravel-infisical-sync)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/qveronq/laravel-infisical-sync/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/qveronq/laravel-infisical-sync/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/qveronq/laravel-infisical-sync/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/qveronq/laravel-infisical-sync/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/qveronq/laravel-infisical-sync.svg?style=flat-square)](https://packagist.org/packages/qveronq/laravel-infisical-sync)

## Installation

```bash
composer require qveronq/laravel-infisical-sync
```

Publish the config file:

```bash
php artisan vendor:publish --tag="laravel-infisical-sync-config"
```

Then add the following to your `.env`:

```env
INFISICAL_URL=https://app.infisical.com          # Optional, for self-hosted instances
INFISICAL_CLIENT_ID=your-machine-identity-client-id
INFISICAL_CLIENT_SECRET=your-machine-identity-client-secret
INFISICAL_PROJECT_ID=your-project-id
INFISICAL_ENVIRONMENT=dev
```

## Usage

### Pull secrets from Infisical into your `.env`

```bash
php artisan infisical:pull
```

Options: `--env=`, `--path=`, `--force`, `--backup`, `--dry-run`, `--show-values`

### Push your `.env` variables to Infisical

```bash
php artisan infisical:push
```

Options: `--env=`, `--path=`, `--force`, `--dry-run`, `--show-values`

### Compare local `.env` with Infisical

```bash
php artisan infisical:diff
```

Options: `--env=`, `--path=`, `--show-values`

### Bidirectional sync

Sync both ways in a single command: pushes local-only variables to Infisical and pulls remote-only variables to your `.env`.

```bash
php artisan infisical:sync
```

Options: `--env=`, `--path=`, `--conflict=`, `--force`, `--backup`, `--dry-run`, `--show-values`

#### Conflict resolution

When a variable exists on both sides with different values, the `--conflict` option (or config `sync_conflict_strategy`) controls the behavior:

| Strategy | Behavior |
|----------|----------|
| `skip` (default) | Conflicts are ignored and shown as warnings |
| `remote` | Infisical value overwrites local |
| `local` | Local value overwrites Infisical |

```bash
# Use Infisical as source of truth for conflicts
php artisan infisical:sync --conflict=remote

# Force sync without confirmation (useful for CI/CD)
php artisan infisical:sync --force --conflict=remote
```

## Configuration

```php
// config/infisical-sync.php
return [
    'url' => env('INFISICAL_URL', 'https://app.infisical.com'),
    'client_id' => env('INFISICAL_CLIENT_ID'),
    'client_secret' => env('INFISICAL_CLIENT_SECRET'),
    'project_id' => env('INFISICAL_PROJECT_ID'),
    'environment' => env('INFISICAL_ENVIRONMENT', 'dev'),
    'secret_path' => env('INFISICAL_SECRET_PATH', '/'),
    'exclude_keys' => [
        'INFISICAL_*', // Supports wildcards
    ],
    'sync_conflict_strategy' => env('INFISICAL_SYNC_CONFLICT_STRATEGY', 'skip'),
    'env_file' => base_path('.env'),
];
```

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Quentin](https://github.com/QveronQ)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
