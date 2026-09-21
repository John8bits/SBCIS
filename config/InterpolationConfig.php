<?php

declare(strict_types=1);

namespace Config;

use InvalidArgumentException;

final class InterpolationConfig
{
    public const PUBLICATION_POLICY_VERSION = 3;
    // Non-field records remain detectable so they can never enter a published
    // engineering-reference surface, even if legacy data is imported.
    public const NON_FIELD_CODE_PREFIXES = ['SYNTH-DEMO-'];

    public const VARIABLES = [
        'bearing_capacity_kpa' => ['label' => 'Bearing capacity', 'unit' => 'kPa'],
        'spt_n_value' => ['label' => 'SPT N-value', 'unit' => 'N-value'],
    ];

    public const BEARING_CAPACITY_CLASSES = [
        ['key'=>'very_low', 'label'=>'Very low', 'range'=>'< 100', 'min'=>null, 'max'=>100.0, 'color'=>'#d73027'],
        ['key'=>'low', 'label'=>'Low', 'range'=>'100–150', 'min'=>100.0, 'max'=>150.0, 'color'=>'#f46d43'],
        ['key'=>'moderate', 'label'=>'Moderate', 'range'=>'> 150–200', 'min'=>150.0, 'max'=>200.0, 'color'=>'#fee08b'],
        ['key'=>'high', 'label'=>'High', 'range'=>'> 200–250', 'min'=>200.0, 'max'=>250.0, 'color'=>'#91cf60'],
        ['key'=>'very_high', 'label'=>'Very high', 'range'=>'> 250', 'min'=>250.0, 'max'=>null, 'color'=>'#1a9850'],
    ];

    // Bearing capacity is the first reviewed publication variable. SPT remains
    // visible in record details but is not mixed into this surface.
    public const PUBLIC_VARIABLES = ['bearing_capacity_kpa'];
    public const TECHNICAL_MINIMUM_POINTS = 5;
    // Joined layer rows, not selected boreholes. The 128 MB production-profile
    // benchmark completed at 10,000 rows and exhausted memory at 50,000, so the
    // worker rejects larger snapshots instead of risking a process crash.
    public const MAX_OBSERVATIONS = 10000;
    public const APPROVED_VARIABLE = 'bearing_capacity_kpa';
    public const INTERPOLATION_METHOD = 'idw';
    public const IDW_POWER = 2.0;
    public const MAX_SUPPORT_DISTANCE_KM = 35.0;
    public const MAX_VALIDATION_POINTS = 500;
    public const SYNCHRONOUS_REGENERATION_MAX_POINTS = 1000;
    public static function cacheDirectory(): string
    {
        $configured = getenv('SBCIS_INTERPOLATION_CACHE_DIR');
        if (is_string($configured) && trim($configured) !== '') return rtrim($configured, '/\\');
        if (ProductionConfig::environment() === 'production') {
            throw new \RuntimeException('Persistent interpolation storage is required in production.');
        }
        return sys_get_temp_dir() . '/sbcis-interpolation-' . substr(hash('sha256', dirname(__DIR__)), 0, 16);
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

    public static function allowSyntheticTestData(): bool
    {
        return ProductionConfig::environment() === 'test' &&
            getenv('SBCIS_ALLOW_SYNTHETIC_INTERPOLATION') === '1';
    }

    public static function bearingCapacityClasses(): array
    {
        return self::BEARING_CAPACITY_CLASSES;
    }

    public static function classifyBearingCapacity(float $value): array
    {
        foreach (self::BEARING_CAPACITY_CLASSES as $class) {
            $lowerMatches = $class['min'] === null || $value >= $class['min'];
            $upperMatches = $class['max'] === null || ($class['key'] === 'very_low' ? $value < $class['max'] : $value <= $class['max']);
            if ($lowerMatches && $upperMatches) return $class;
        }
        return self::BEARING_CAPACITY_CLASSES[count(self::BEARING_CAPACITY_CLASSES) - 1];
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
