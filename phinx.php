<?php

return
[
    'paths' => [
        'migrations' => '%%PHINX_CONFIG_DIR%%/db/migrations',
        'seeds' => '%%PHINX_CONFIG_DIR%%/db/seeds'
    ],
    'environments' => [
        'default_migration_table' => 'phinxlog',
        'default_database' => 'ddev',
        'ddev' => [
            'adapter' => 'mysql',
            'host' => 'db',
            'name' => 'db',
            'user' => 'db',
            'pass' => 'db',
            'port' => '3306',
            'charset' => 'utf8',
        ],
        'local' => [
            'adapter' => 'mysql',
            'unix_socket' => '/var/run/mysqld/mysqld.sock',
            'name' => 'poa_savings',
            'user' => 'poa_user',
            'pass' => 'poa_password',
            'charset' => 'utf8',
        ]
    ],
    'version_order' => 'creation'
];
