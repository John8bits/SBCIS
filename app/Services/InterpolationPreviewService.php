<?php

declare(strict_types=1);

namespace App\Services;

use App\Database\Connection;
use App\Models\GeotechnicalRepository;
use Config\InterpolationConfig;
use RuntimeException;

final class InterpolationPreviewService
{
    public function build(): array
    {
        $allBoreholes = (new GeotechnicalRepository(Connection::get()))->mapBoreholes();
        $boreholes = array_values(array_filter(
            $allBoreholes,
            static fn(array $borehole): bool => InterpolationConfig::isNonFieldRecord($borehole)
        ));

        $observations = [];
        foreach ($boreholes as $borehole) {
            foreach ($borehole['layers'] as $layer) {
                $value = $layer['bearing_capacity_kpa'] ?? null;
                if (is_numeric($value)) {
                    $observations[] = [
                        'longitude' => (float) $borehole['longitude'],
                        'latitude' => (float) $borehole['latitude'],
                        'value' => (float) $value,
                    ];
                    break;
                }
            }
        }
        if (count($observations) < 3) {
            throw new RuntimeException('The synthetic UI-preview fixture is not available.');
        }

        $path = dirname(__DIR__, 2) . '/src/qgis/southern_leyte_barangays.geojson';
        $json = @file_get_contents($path);
        if ($json === false) throw new RuntimeException('Preview boundary data is unavailable.');
        $surface = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        foreach ($surface['features'] as &$feature) {
            [$longitude, $latitude] = $this->representativePoint($feature['geometry'] ?? []);
            $feature['properties'] = ($feature['properties'] ?? []) + [];
            $feature['properties']['value'] = $this->estimate($longitude, $latitude, $observations);
        }
        unset($feature);

        $values = array_column($observations, 'value');
        return [
            'boreholes' => $boreholes,
            'response' => [
                'status' => 'current',
                'result' => [
                    'variable' => 'bearing_capacity_kpa',
                    'method' => 'Synthetic IDW-style UI fixture',
                    'legend' => ['min' => min($values), 'max' => max($values), 'unit' => 'kPa'],
                    'surface' => $surface,
                ],
            ],
        ];
    }

    private function representativePoint(array $geometry): array
    {
        $polygons = ($geometry['type'] ?? '') === 'Polygon'
            ? [$geometry['coordinates'] ?? []]
            : ($geometry['coordinates'] ?? []);
        $largestRing = [];
        foreach ($polygons as $polygon) {
            $ring = $polygon[0] ?? [];
            if (count($ring) > count($largestRing)) $largestRing = $ring;
        }
        $longitude = 0.0;
        $latitude = 0.0;
        $count = max(1, count($largestRing));
        foreach ($largestRing as $position) {
            $longitude += (float) ($position[0] ?? 0);
            $latitude += (float) ($position[1] ?? 0);
        }
        return [$longitude / $count, $latitude / $count];
    }

    private function estimate(float $longitude, float $latitude, array $observations): float
    {
        $weighted = 0.0;
        $weights = 0.0;
        foreach ($observations as $observation) {
            $dx = $longitude - $observation['longitude'];
            $dy = $latitude - $observation['latitude'];
            $distanceSquared = $dx * $dx + $dy * $dy;
            if ($distanceSquared < 1.0e-12) return $observation['value'];
            $weight = 1 / $distanceSquared;
            $weighted += $weight * $observation['value'];
            $weights += $weight;
        }
        return round($weighted / $weights, 2);
    }
}
