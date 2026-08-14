<?php

use Illuminate\Support\Str;
use PDO;

$redis_host = env('REDIS_HOST', '127.0.0.1');
$redis_database = env('REDIS_DATABASE', '0');
$redis_password = env('REDIS_PASSWORD', null);
$database_url = env('DATABASE_URL');
$database_connection = env('DB_CONNECTION', 'mysql');
$database_host = env('DB_HOST', '127.0.0.1');
$database_port = env('DB_PORT', 3306);
$database_database = env('DB_DATABASE', 'laravel');
$database_username = env('DB_USERNAME', 'root');
$database_password = env('DB_PASSWORD', '');
$database_socket = env('DB_SOCKET');

if ($cacheUrl = getenv('CACHE_URL')) {
    $url = parse_url($cacheUrl);

    $redis_host = $url['host'];
    if (isset($url['pass'])) {
        $redis_password = $url['pass'];
    }
    $redis_database = isset($url['path']) ? substr($url['path'], 1) : 'cache';
}

if ($database_url) {
    $url = parse_url($database_url);

    if (isset($url['scheme'])) {
        $database_connection = $url['scheme'];
    }

    if (isset($url['host'])) {
        $database_host = $url['host'];
    }

    if (isset($url['port'])) {
        $database_port = $url['port'];
    }

    if (isset($url['path'])) {
        $database_database = ltrim($url['path'], '/');
    }

    if (isset($url['user'])) {
        $database_username = $url['user'];
    }

    if (isset($url['pass'])) {
        $database_password = $url['pass'];
    }
}

return [

    /*
    |--------------------------------------------------------------------------
    | Default Database Connection Name
    |--------------------------------------------------------------------------
    |
    | Here you may specify which of the database connections below you wish
    | to use as your default connection for all database work. Of course
    | you may use many connections at once using the Database library.
    |
    */

    'default' => $database_connection,

    /*
    |--------------------------------------------------------------------------
    | Database Connections
    |--------------------------------------------------------------------------
    |
    | Here are each of the database connections setup for your application.
    | Of course, examples of configuring each database platform that is
    | supported by Laravel is shown below to make development simple.
    |
    |
    | All database work in Laravel is done through the PHP PDO facilities
    | so make sure you have the driver for your particular database of
    | choice installed on your machine before you begin development.
    |
    */

    'connections' => [
        'sqlite' => [
            'driver' => 'sqlite',
            'url' => env('DATABASE_URL'),
            'database' => env('DB_DATABASE', database_path('database.sqlite')),
            'prefix' => '',
            'foreign_key_constraints' => env('DB_FOREIGN_KEYS', true),
        ],

        'mysql' => [
            'driver' => 'mysql',
            'url' => env('DATABASE_URL'),
            'host' => $database_host,
            'port' => $database_port,
            'database' => $database_database,
            'username' => $database_username,
            'password' => $database_password,
            'unix_socket' => $database_socket,
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],

        'pgsql' => [
            'driver' => 'pgsql',
            'url' => env('DATABASE_URL'),
            'host' => $database_host,
            'port' => $database_port,
            'database' => $database_database,
            'username' => $database_username,
            'password' => $database_password,
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => 'prefer',
        ],

        'sqlsrv' => [
            'driver' => 'sqlsrv',
            'url' => env('DATABASE_URL'),
            'host' => $database_host,
            'port' => $database_port,
            'database' => $database_database,
            'username' => $database_username,
            'password' => $database_password,
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Migration Repository Table
    |--------------------------------------------------------------------------
    |
    | This table keeps track of all the migrations that have already run for
    | your application. Using this information, we can determine which of
    | the migrations on disk haven't actually been run in the database.
    |
    */

    'migrations' => 'migrations',

    /*
    |--------------------------------------------------------------------------
    | Redis Databases
    |--------------------------------------------------------------------------
    |
    | Redis is an open source, fast, and advanced key-value store that also
    | provides a richer body of commands than a typical key-value system
    | such as APC or Memcached. Laravel makes it easy to dig right in.
    |
    */

    'redis' => [
        'client' => env('REDIS_CLIENT', 'phpredis'),

        'options' => [
            'cluster' => env('REDIS_CLUSTER', 'redis'),
            'prefix' => env('REDIS_PREFIX', Str::slug(env('APP_NAME', 'fleetbase'), '_') . '_database_'),
        ],

        'default' => [
            'host' => $redis_host,
            'password' => $redis_password,
            'port' => env('REDIS_PORT', 6379),
            'database' => $redis_database,
        ],

        'sql' => [
            'host' => $redis_host,
            'password' => $redis_password,
            'port' => env('REDIS_PORT', 6379),
            'database' => $redis_database . '_sql_cache',
        ],

        'cache' => [
            'host' => $redis_host,
            'password' => $redis_password,
            'port' => env('REDIS_PORT', 6379),
            'database' => $redis_database . '_cache',
        ],

        'geocode-cache' => [
            'host' => $redis_host,
            'password' => $redis_password,
            'port' => env('REDIS_PORT', 6379),
            'database' => $redis_database . '_geocode_cache',
        ],
    ],

];
