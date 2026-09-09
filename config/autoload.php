<?php

declare(strict_types=1);

/**
 * Loads classes under the App namespace from the app directory.
 *
 * App\Database\Connection maps to app/Database/Connection.php.
 */
spl_autoload_register(static function (string $class): void {
    $namespacePrefix = 'App\\';
    $baseDirectory = __DIR__ . '/../app/';

    if (strncmp($class, $namespacePrefix, strlen($namespacePrefix)) !== 0) {
        return;
    }

    $relativeClass = substr($class, strlen($namespacePrefix));
    $relativePath = str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass);
    $classFile = $baseDirectory . $relativePath . '.php';

    if (is_file($classFile)) {
        require_once $classFile;
    }
});
