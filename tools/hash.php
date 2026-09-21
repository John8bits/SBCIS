<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$password = $argv[1] ?? '';
if (!is_string($password) || strlen($password) < 12) {
    fwrite(STDERR, "Usage: php tools/hash.php <unique-password-of-at-least-12-characters>\n");
    exit(2);
}

echo password_hash($password, PASSWORD_DEFAULT) . PHP_EOL;
