<?php

declare(strict_types=1);

namespace App\Services;

use Config\InterpolationConfig;
use RuntimeException;

/**
 * Deterministic server-side IDW generator.
 *
 * Each barangay receives one estimate at its polygon centroid. The complete
 * barangay polygon is published only when that centroid is within the approved
 * support distance of a measured borehole. This avoids implying province-wide
 * certainty from a sparse cluster of observations.
 */
final class InterpolationGeneratorService
{
    public function generate(array $input): array
    {
        if (($input['variable'] ?? null) !== InterpolationConfig::APPROVED_VARIABLE ||
            ($input['status'] ?? null) !== 'ready') {
            throw new RuntimeException('Interpolation input is not approved for generation.');
        }
        $observations = $input['points'] ?? [];
        if (count($observations) < InterpolationConfig::TECHNICAL_MINIMUM_POINTS) {
            throw new RuntimeException('Insufficient observations for interpolation.');
        }

        $path = dirname(__DIR__, 2) . '/src/qgis/southern_leyte_barangays.geojson';
        $json = @file_get_contents($path);
        if ($json === false) throw new RuntimeException('Barangay geometry is unavailable.');
        $barangays = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        if (($barangays['type'] ?? '') !== 'FeatureCollection' || empty($barangays['features'])) {
            throw new RuntimeException('Barangay geometry is invalid.');
        }

        $features = [];
        $values = [];
        foreach ($barangays['features'] as $feature) {
            [$longitude, $latitude] = $this->centroid($feature['geometry'] ?? []);
            [$value, $nearestKm] = $this->estimate($longitude, $latitude, $observations);
            if ($nearestKm > InterpolationConfig::MAX_SUPPORT_DISTANCE_KM) continue;

            $feature['properties'] = is_array($feature['properties'] ?? null) ? $feature['properties'] : [];
            $feature['properties']['value'] = round($value, 2);
            $feature['properties']['support_distance_km'] = round($nearestKm, 2);
            $feature['properties']['observation_count'] = count($observations);
            $features[] = $feature;
            $values[] = $value;
        }
        if (!$features) throw new RuntimeException('No barangay is within the approved observation support distance.');

        return [
            'method' => sprintf(
                'IDW (p=%g), shallowest valid layer per borehole, %.0f km support limit',
                InterpolationConfig::IDW_POWER,
                InterpolationConfig::MAX_SUPPORT_DISTANCE_KM
            ),
            'legend' => [
                'min' => round(min($values), 2),
                'max' => round(max($values), 2),
                'unit' => (string) $input['unit'],
            ],
            'surface' => ['type' => 'FeatureCollection', 'features' => $features],
            // Lets the publication guard verify this as an exact subset of the
            // repository's reviewed barangay geometries without an expensive
            // all-edge intersection scan on every request.
            'geometry_source_hash' => hash('sha256', $json),
        ];
    }

    private function estimate(float $longitude, float $latitude, array $observations): array
    {
        $weighted = 0.0;
        $weightTotal = 0.0;
        $nearestKm = INF;
        foreach ($observations as $observation) {
            [$obsLongitude, $obsLatitude] = $observation['coordinates'];
            $distanceKm = $this->distanceKm($latitude, $longitude, (float) $obsLatitude, (float) $obsLongitude);
            $nearestKm = min($nearestKm, $distanceKm);
            if ($distanceKm < 0.001) return [(float) $observation['value'], 0.0];
            $weight = 1 / ($distanceKm ** InterpolationConfig::IDW_POWER);
            $weighted += $weight * (float) $observation['value'];
            $weightTotal += $weight;
        }
        if ($weightTotal <= 0) throw new RuntimeException('Unable to calculate interpolation weights.');
        return [$weighted / $weightTotal, $nearestKm];
    }

    private function distanceKm(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $latDelta = deg2rad($lat2 - $lat1);
        $lonDelta = deg2rad($lon2 - $lon1);
        $a = sin($latDelta / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($lonDelta / 2) ** 2;
        return 6371.0088 * 2 * atan2(sqrt($a), sqrt(max(0.0, 1 - $a)));
    }

    private function centroid(array $geometry): array
    {
        $polygons = ($geometry['type'] ?? '') === 'Polygon'
            ? [$geometry['coordinates'] ?? []]
            : (($geometry['type'] ?? '') === 'MultiPolygon' ? ($geometry['coordinates'] ?? []) : []);
        $largestRing = [];
        foreach ($polygons as $polygon) {
            $ring = $polygon[0] ?? [];
            if (count($ring) > count($largestRing)) $largestRing = $ring;
        }
        if (count($largestRing) < 4) throw new RuntimeException('Invalid barangay polygon.');

        $twiceArea = 0.0;
        $xTotal = 0.0;
        $yTotal = 0.0;
        for ($i = 1; $i < count($largestRing); $i++) {
            $a = $largestRing[$i - 1];
            $b = $largestRing[$i];
            $cross = (float) $a[0] * (float) $b[1] - (float) $b[0] * (float) $a[1];
            $twiceArea += $cross;
            $xTotal += ((float) $a[0] + (float) $b[0]) * $cross;
            $yTotal += ((float) $a[1] + (float) $b[1]) * $cross;
        }
        if (abs($twiceArea) < 1.0e-12) {
            return [(float) $largestRing[0][0], (float) $largestRing[0][1]];
        }
        return [$xTotal / (3 * $twiceArea), $yTotal / (3 * $twiceArea)];
    }
}
