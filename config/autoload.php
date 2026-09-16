<?php

declare(strict_types=1);

/**
 * Loads application and configuration classes without manual includes.
 */
spl_autoload_register(static function (string $class): void {
    $namespaces = [
        'App\\' => __DIR__ . '/../app/',
        'Config\\' => __DIR__ . '/',
    ];

    foreach ($namespaces as $namespacePrefix => $baseDirectory) {
        if (strncmp($class, $namespacePrefix, strlen($namespacePrefix)) !== 0) {
            continue;
        }

        $relativeClass = substr($class, strlen($namespacePrefix));
        $relativePath = str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass);
        $classFile = $baseDirectory . $relativePath . '.php';

        if (is_file($classFile)) {
            require_once $classFile;
        }

        return;
    }
});
