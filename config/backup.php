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
];
