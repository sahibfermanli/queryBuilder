<?php

$envFile = __DIR__ . '/.env';

$env = [];

if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {
        [$key, $value] = explode('=', $line, 2);
        $env[trim($key)] = trim($value);
    }

    foreach ($env as $key => $value) {
        putenv("$key=$value");
    }
}


