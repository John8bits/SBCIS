<?php
declare(strict_types=1);

namespace App\Services;

use Config\InterpolationConfig;
use RuntimeException;

final class InterpolationResultStore
{
    private string $directory;

    public function __construct(?string $directory = null)
    {
        $this->directory = $directory ?? InterpolationConfig::cacheDirectory();
    }

    public function read(): array
    {
        $file = $this->directory . '/state.json';
        if (!is_file($file)) return ['published' => null, 'attempt' => null];
        $json = @file_get_contents($file);
        if ($json === false) throw new RuntimeException('Cannot read interpolation cache.');
        $state = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($state) || !array_key_exists('published', $state) || !array_key_exists('attempt', $state)) {
            throw new RuntimeException('Invalid interpolation cache.');
        }
        return $state;
    }

    public function update(callable $change): array
    {
        if (!is_dir($this->directory) && !@mkdir($this->directory, 0700, true) && !is_dir($this->directory)) {
            throw new RuntimeException('Cannot create interpolation cache directory.');
        }
        $lock = @fopen($this->directory . '/generation.lock', 'c');
        if (!$lock) throw new RuntimeException('Cannot open interpolation lock.');
        $temporary = null;
        try {
            if (!flock($lock, LOCK_EX | LOCK_NB)) throw new RuntimeException('Another interpolation update is running.');
            $state = $change($this->read());
            $json = json_encode($state, JSON_THROW_ON_ERROR);
            $temporary = tempnam($this->directory, 'pending-');
            if ($temporary === false || file_put_contents($temporary, $json) !== strlen($json)) {
                throw new RuntimeException('Cannot write interpolation result.');
            }
            // Readers see the previous complete state or the new complete state, never a partial write.
            if (!@rename($temporary, $this->directory . '/state.json')) throw new RuntimeException('Cannot publish interpolation cache.');
            $temporary = null;
            return $state;
        } finally {
            if (is_string($temporary) && is_file($temporary)) unlink($temporary);
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    public function markOutdated(): void
    {
        $this->update(static function (array $state): array {
            $state['outdated'] = true;
            return $state;
        });
    }
}
