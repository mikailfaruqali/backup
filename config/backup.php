<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Middleware Configuration
    |--------------------------------------------------------------------------
    |
    | These middleware will be applied to all backup package routes.
    | By default, the "web" middleware group is applied for session,
    | CSRF protection, and other standard web middleware.
    |
    | You may add authentication or custom middleware here as needed.
    |
    */

    'middleware' => ['web'],

    /*
    |--------------------------------------------------------------------------
    | Route Configuration
    |--------------------------------------------------------------------------
    |
    | This prefix will be used for all backup-related routes. For example,
    | if set to 'backup', the download route would be available at:
    |
    |   /backup
    |
    | Customize the prefix to fit your routing structure (e.g. 'admin/backup').
    |
    */

    'route' => 'backup',

    /*
    |--------------------------------------------------------------------------
    | Temporary Storage Path
    |--------------------------------------------------------------------------
    |
    | Directory for temporary SQL dump and ZIP files during backup creation.
    | Uses storage_path() by default to avoid issues with system temp directories
    | being cleaned by OS cron jobs or having space limitations.
    |
    | The directory will be created automatically if it doesn't exist.
    | Files are deleted immediately after download completes.
    |
    | Default: storage_path('app/temp-backups')
    |
    */

    'temp_path' => storage_path('app/temp-backups'),

    /*
    |--------------------------------------------------------------------------
    | File Name Generator
    |--------------------------------------------------------------------------
    |
    | A callback that returns the file name for the downloadable backup ZIP.
    | The closure receives no arguments and must return a string like:
    |   "backup-2025-06-01.zip"
    |
    | You can customize the naming logic here as needed.
    |
    */

    'file_name' => 'backup.zip',

    /*
    |--------------------------------------------------------------------------
    | ZIP Password Generator
    |--------------------------------------------------------------------------
    |
    | A callback that returns the password used to encrypt the ZIP archive.
    | This closure receives the current request and must return a string.
    |
    | For example, you may pull the password from the request query or
    | environment variables, or generate it dynamically.
    |
    */

    'zip_password' => 'snawbar',

    /*
    |--------------------------------------------------------------------------
    | MySQL Dump Binary Path
    |--------------------------------------------------------------------------
    |
    | If "mysqldump" is not in the system's PATH, you can specify its
    | absolute path here. Example for Windows:
    |   'C:/xampp/mysql/bin/mysqldump.exe'
    |
    | Example for Linux or MacOS:
    |   '/usr/bin/mysqldump'
    |
    | Leave it null to use the default binary in system PATH.
    |
    */

    'mysql_dump_path' => '',

    /*
    |--------------------------------------------------------------------------
    | Extra Dump Options
    |--------------------------------------------------------------------------
    |
    | Additional command-line options to pass to mysqldump. These are useful
    | for handling permission restrictions or customizing backup behavior.
    |
    | Common options:
    |   --no-tablespaces       Skip tablespace information (fixes PROCESS privilege error)
    |   --single-transaction   Use consistent snapshot for InnoDB tables
    |   --quick                Retrieve rows one at a time (faster for large tables)
    |   --skip-triggers        Don't include triggers in the dump
    |   --skip-routines        Don't include stored procedures and functions
    |   --skip-events          Don't include events
    |
    | Note: The --no-tablespaces option is included by default to prevent
    | "Access denied; you need PROCESS privilege" errors in shared hosting
    | or restricted database environments. This is safe to use and doesn't
    | affect the ability to restore your database.
    |
    */

    'extra_dump_options' => [
        '--no-tablespaces',
    ],
];
