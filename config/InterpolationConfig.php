<?php

declare(strict_types=1);

namespace Config;

use InvalidArgumentException;

final class InterpolationConfig
{
    // Codes reserved by database/sample_interpolation_50.sql. These rows are
    // useful for isolated demos, but they are never field observations.
    public const NON_FIELD_CODE_PREFIXES = ['SYNTH-DEMO-'];

    public const VARIABLES = [
        'bearing_capacity_kpa' => ['label' => 'Bearing capacity', 'unit' => 'kPa'],
        'spt_n_value' => ['label' => 'SPT N-value', 'unit' => 'N-value'],
    ];

    // Bearing capacity is the first reviewed publication variable. SPT remains
    // visible in record details but is not mixed into this surface.
    public const PUBLIC_VARIABLES = ['bearing_capacity_kpa'];
    public const TECHNICAL_MINIMUM_POINTS = 5;
    public const MAX_OBSERVATIONS = 10000;
    public const APPROVED_VARIABLE = 'bearing_capacity_kpa';
    public const INTERPOLATION_METHOD = 'idw';
    public const IDW_POWER = 2.0;
    public const MAX_SUPPORT_DISTANCE_KM = 35.0;

    public static function cacheDirectory(): string
    {
        // Keep generated data outside the web root; deployments may choose persistent storage.
        return getenv('SBCIS_INTERPOLATION_CACHE_DIR') ?: sys_get_temp_dir() . '/sbcis-interpolation-' .
            substr(hash('sha256', dirname(__DIR__)), 0, 16);
    }

    public function systemQuery(): array
    {
        $query = ['scope' => 'province'];
        if (self::APPROVED_VARIABLE !== null) $query['variable'] = self::APPROVED_VARIABLE;
        $this->selection($query);
        return $query;
    }

    private array $publicVariables;
    private int $minimumPoints;

    public function __construct(array $publicVariables = self::PUBLIC_VARIABLES, int $minimumPoints = self::TECHNICAL_MINIMUM_POINTS)
    {
        foreach ($publicVariables as $variable) {
            if (!is_string($variable) || !isset(self::VARIABLES[$variable])) {
                throw new InvalidArgumentException('Unsupported configured variable.');
            }
        }
        if ($minimumPoints < 1) {
            throw new InvalidArgumentException('The technical safeguard must be positive.');
        }
        $this->publicVariables = array_values(array_unique($publicVariables));
        $this->minimumPoints = $minimumPoints;
    }

    public function publicVariables(): array
    {
        $variables = [];
        foreach ($this->publicVariables as $key) {
            $variables[] = ['key' => $key] + self::VARIABLES[$key];
        }
        return $variables;
    }

    public function minimumPoints(): int
    {
        return $this->minimumPoints;
    }

    public static function isNonFieldRecordCode($code): bool
    {
        if (!is_string($code)) return false;
        foreach (self::NON_FIELD_CODE_PREFIXES as $prefix) {
            if (str_starts_with(strtoupper(trim($code)), $prefix)) return true;
        }
        return false;
    }

    public static function isNonFieldRecord(array $record): bool
    {
        if (self::isNonFieldRecordCode($record['borehole_code'] ?? null)) return true;
        $layers = is_array($record['layers'] ?? null) ? $record['layers'] : [$record];
        foreach ($layers as $layer) {
            $provenance = strtoupper(trim((string) ($layer['soil_type'] ?? '') . ' ' . (string) ($layer['soil_description'] ?? '')));
            foreach (['SYNTHETIC SAMPLE', 'ARTIFICIAL DEMO', 'NOT MEASURED', 'NOT FOR ENGINEERING USE'] as $marker) {
                if (str_contains($provenance, $marker)) return true;
            }
        }
        return false;
    }

    public function selection(array $query): array
    {
        foreach ($query as $key => $value) {
            if (!in_array($key, ['variable', 'scope'], true) || !is_string($value)) {
                throw new InvalidArgumentException('Unsupported interpolation parameter.');
            }
        }
        $scope = $query['scope'] ?? 'province';
        if ($scope !== 'province') {
            throw new InvalidArgumentException('Only the Southern Leyte scope is currently supported.');
        }
        $variable = $query['variable'] ?? null;
        if ($variable !== null && !in_array($variable, $this->publicVariables, true)) {
            throw new InvalidArgumentException('This measurement variable is not enabled for publication.');
        }
        return ['variable' => $variable, 'scope' => $scope];
    }
}
