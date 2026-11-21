<?php

/*
 * Configuration of Phinx (https://phinx.org): migrations of the autoweb database.
 * Usage (from this directory):
 *     vendor/bin/phinx status              pending migrations
 *     vendor/bin/phinx migrate             apply them (done by Ansible on each install.yml)
 *     vendor/bin/phinx create MyChange     new migration in db/migrations
 * Same database settings as lib/connect.php; the password comes from config.php (created by Ansible).
 * AUTOWEB_DB_HOST and AUTOWEB_DB_PASSWORD override them (used by the CI).
 */

$dbPassword = getenv('AUTOWEB_DB_PASSWORD');
if ($dbPassword === false) {
    require __DIR__ . '/config.php';
}

return [
    'paths' => [
        'migrations' => __DIR__ . '/db/migrations',
    ],
    'environments' => [
        'default_migration_table' => 'phinxlog',
        'default_environment' => 'production',
        'production' => [
            'adapter' => 'mysql',
            'host' => getenv('AUTOWEB_DB_HOST') ?: 'localhost',
            'name' => 'autoweb',
            'user' => 'autoweb',
            'pass' => $dbPassword,
            'charset' => 'utf8',
        ],
    ],
    'version_order' => 'creation',
];
