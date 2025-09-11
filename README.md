# Laravel Backup Package

[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)](https://opensource.org/licenses/MIT)

Generate on-demand MySQL database backups and download them as password-protected, AES‑256 encrypted ZIP files via a simple HTTP route.

Built for simplicity, performance, and easy integration into existing Laravel apps.

## Features

- One-click/download database backup via HTTP route
- ZIP encryption using AES‑256 (ZipArchive::EM_AES_256)
- Password protection for the ZIP file
- Configurable route prefix and middleware
- Customizable download filename
- Support for custom mysqldump binary path (Windows/Linux/macOS)
- Streams response and cleans up temporary files automatically

## Requirements

- PHP >= 7.4
- Laravel framework (or illuminate/contracts) >= 5.0
- PHP ZipArchive extension enabled
- MySQL database connection configured in `config/database.php`
- [spatie/db-dumper](https://github.com/spatie/db-dumper) (installed as a dependency)

## Installation

Install via Composer:

```powershell
composer require mikailfaruqali/backup
```

Laravel will auto-discover the service provider.

## Configuration

Publish the configuration file to your app (it will create `config/snawbar-backup.php`):

```powershell
php artisan vendor:publish --provider="Snawbar\Backup\BackupServiceProvider" --tag="snawbar-backup-config"
```

Available options (defaults shown):

```php
return [
		// Middleware applied to the backup route(s)
		'middleware' => ['web'],

		// Route prefix used by the package
		'route' => 'backup',

		// The downloadable ZIP file name (string). Avoid closures when using config cache.
		'file_name' => 'backup.zip',

		// The password required to open the ZIP
		'zip_password' => 'snawbar',

		// Absolute path to mysqldump if it's not in PATH
		// e.g. Windows: 'C:/xampp/mysql/bin/mysqldump.exe'
		//      Linux/macOS: '/usr/bin/mysqldump'
		'mysql_dump_path' => '',
];
```

Notes:

- The package code will also accept callables for `file_name` and `zip_password`. However, Laravel's config cache cannot serialize closures. Prefer static strings in your config file. If you need dynamic values, set them at runtime (e.g., in middleware) using `config([...])` before the request hits the controller, and avoid caching config or provide a non-closure value when cached.

## Routes and Usage

This package registers the following route by default:

- GET `/{prefix}/download` → streams a password-protected ZIP containing a MySQL dump

Where `{prefix}` is the value of `snawbar-backup.route` (default: `backup`).

Examples:

- Default URL: `/backup/download`
- Custom prefix `admin/backup`: `/admin/backup/download`

The route name is `snawbar.backup.download`.

### Downloading a backup

- Visit the route in your browser, e.g. `https://your-app.test/backup/download`
- Or using curl:

```powershell
curl -L -o backup.zip https://your-app.test/backup/download
```

The response is a streamed download of an encrypted ZIP whose filename is taken from `snawbar-backup.file_name`. Use the password from `snawbar-backup.zip_password` to open the archive.

## Security and Access Control

The download route is as secure as the middleware you attach to it. By default it uses the `web` middleware group only. You should restrict access in production.

Recommended options:

- Require authentication: set `'middleware' => ['web', 'auth']`
- Restrict to authorized roles/abilities: `'middleware' => ['web', 'auth', 'can:download-backup']`
- Use IP allow-lists or custom middleware for extra checks
- Consider signed routes or additional secrets when exposing via the internet

Update `config/snawbar-backup.php` accordingly and clear caches:

```powershell
php artisan config:clear; php artisan route:clear
```

## Database Connection

Backups use your default `mysql` connection from `config/database.php`:

- Host: `database.connections.mysql.host`
- Database: `database.connections.mysql.database`
- Username: `database.connections.mysql.username`
- Password: `database.connections.mysql.password`

Ensure these are set (typically via your `.env`).

## mysqldump Path (especially on Windows)

If `mysqldump` isn't available in the system PATH, set `mysql_dump_path` in `config/snawbar-backup.php`:

- Windows (XAMPP example): `C:/xampp/mysql/bin/mysqldump.exe`
- WSL/Linux: `/usr/bin/mysqldump`
- macOS (Homebrew): `/opt/homebrew/bin/mysqldump` or `/usr/local/bin/mysqldump`

## How it Works

Under the hood (simplified):

1. Create a temporary `.sql` dump using spatie/db-dumper and `mysqldump`.
2. Create a temporary `.zip` with ZipArchive, set password and AES‑256 encryption, add the SQL file.
3. Stream the ZIP to the client as the response filename from `file_name`.
4. Delete the temporary files after streaming.

## Troubleshooting

- ZIP won't open / wrong password
	- Verify `zip_password` in `config/snawbar-backup.php`
	- Ensure no whitespace or unexpected characters

- 500 error: Failed to create ZIP archive
	- Confirm PHP ZipArchive extension is installed and enabled
	- Ensure the PHP process can write to the temp directory (`sys_get_temp_dir()`)

- mysqldump not found or permission denied
	- Set `mysql_dump_path` to the absolute path of `mysqldump`
	- Ensure the PHP user can execute `mysqldump`

- Empty or partial dump
	- Check DB credentials in `config/database.php`
	- Large databases may require increasing PHP `max_execution_time` and memory limits

- Route not found
	- Clear caches: `php artisan route:clear; php artisan config:clear`
	- Confirm your app is loading package routes and provider (auto-discovery)

## Limitations and Notes

- Currently supports the default `mysql` connection. For other drivers, extend the controller as needed.
- Configuration closures are not compatible with `php artisan config:cache`.
- The backup is generated on-demand per request; there's no scheduled/queued command included by default.

## API Surface (Package Internals)

- Service Provider: `Snawbar\Backup\BackupServiceProvider`
- Controller: `Snawbar\Backup\Http\Controllers\BackupController`
- Route file: `src/routes/web.php`
- Config file: `config/backup.php` (published as `config/snawbar-backup.php`)

## Contributing

Issues and PRs are welcome. Please run code style and static analysis before submitting.

```powershell
vendor\bin\pint
vendor\bin\phpstan analyse
```

## License

This package is open-sourced software licensed under the [MIT license](LICENSE).

## Author

Snawbar — [alanfaruq85@gmail.com](mailto:alanfaruq85@gmail.com)

## Links

- GitHub: https://github.com/mikailfaruqali/backup
