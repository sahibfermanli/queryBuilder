<?php

namespace App\Core;

class Config
{
    public static function get(): array
    {
        return [
            'database' => [
                'host' => getenv('DB_HOST'),
                'port' => getenv('DB_PORT'),
                'database' => getenv('DB_DATABASE'),
                'username' => getenv('DB_USERNAME'),
                'password' => getenv('DB_PASSWORD'),
            ],
        ];
    }
}