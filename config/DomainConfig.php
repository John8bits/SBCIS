<?php

declare(strict_types=1);

namespace Config;

final class DomainConfig
{
    public static function suspiciousValues(array $record): array
    {
        $warnings = [];
        self::above($warnings, 'Borehole depth', $record['borehole_depth_m'] ?? null, 'SBCIS_REVIEW_BOREHOLE_DEPTH_M', 'm');
        self::above($warnings, 'SPT N-value', $record['spt_n_value'] ?? null, 'SBCIS_REVIEW_SPT_N', '');
        self::above($warnings, 'Bearing capacity', $record['bearing_capacity_kpa'] ?? null, 'SBCIS_REVIEW_BEARING_CAPACITY_KPA', 'kPa');
        return $warnings;
    }

    private static function above(array &$warnings, string $label, mixed $value, string $environment, string $unit): void
    {
        $threshold = getenv($environment);
        if ($value === null || $value === '' || !is_numeric($value) || !is_string($threshold) ||
            !is_numeric($threshold) || (float) $threshold <= 0) return;
        if ((float) $value > (float) $threshold) {
            $warnings[] = $label . ' exceeds the configured review threshold of ' . $threshold . ($unit === '' ? '' : ' ' . $unit) . '.';
        }
    }
}
