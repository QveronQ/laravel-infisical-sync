
# Laravel Infisical Sync

> **Warning** — This package is under active development and is not yet ready for production use.

Sync your Laravel `.env` secrets with [Infisical](https://infisical.com). Push, pull and diff environment variables between your `.env` file and your Infisical vault using the official PHP SDK.

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
