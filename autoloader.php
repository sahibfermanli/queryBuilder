<?php

spl_autoload_register(static function ($class) {
    $baseDirectory = __DIR__;

    $baseNamespace = '';

    $len = strlen($baseNamespace);
    if (strncmp($baseNamespace, $class, $len) !== 0) {
        return;
    }

    $relativeClass = substr($class, $len);

    $file = $baseDirectory . '/' . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

