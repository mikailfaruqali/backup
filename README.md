# Laravel Backup Package

[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)](https://opensource.org/licenses/MIT)

A Laravel package to generate database backups with optional ZIP encryption and password protection. Designed for simplicity, performance, and route-based access.

## Installation

Require the package via Composer:

```bash
composer require mikailfaruqali/backup
```

## Configuration

Publish the config file to customize the package settings:

```bash
php artisan vendor:publish --provider="Snawbar\Backup\BackupServiceProvider" --tag="config"
```

## Requirements

- PHP >= 7.4
- Laravel (or illuminate/contracts) >= 5.0
- [spatie/db-dumper](https://github.com/spatie/db-dumper)

## License

This package is open-sourced software licensed under the [MIT license](LICENSE).

## Author

Snawbar — [alanfaruq85@gmail.com](mailto:alanfaruq85@gmail.com)

## Links

- [GitHub Repository](https://github.com/mikailfaruqali/backup)
