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
            $classification = InterpolationConfig::classifyBearingCapacity($value);
            $feature['properties']['classification'] = $classification['key'];
            $feature['properties']['support_distance_km'] = round($nearestKm, 2);
            $feature['properties']['observation_count'] = count($observations);
            $feature['properties']['prediction_coordinates'] = [round($longitude, 7), round($latitude, 7)];
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
                'classes' => InterpolationConfig::bearingCapacityClasses(),
            ],
            'surface' => ['type' => 'FeatureCollection', 'features' => $features],
            'validation' => $this->validation($observations),
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

    /**
     * Confirm exact-point behavior and calculate leave-one-out errors using
     * the same IDW implementation and all remaining eligible observations.
     */
    private function validation(array $observations): array
    {
        $exactMaximumError = 0.0;
        $errors = [];
        $population = count($observations);
        $selected = [];
        $sampleSize = min($population, InterpolationConfig::MAX_VALIDATION_POINTS);
        for ($sample = 0; $sample < $sampleSize; $sample++) {
            $index = $sampleSize === 1 ? 0 : (int) round($sample * ($population - 1) / ($sampleSize - 1));
            $selected[$index] = true;
        }
        foreach ($observations as $index => $observation) {
            if (!isset($selected[$index])) continue;
            [$longitude, $latitude] = $observation['coordinates'];
            [$atObservation] = $this->estimate((float) $longitude, (float) $latitude, $observations);
            $exactMaximumError = max($exactMaximumError, abs($atObservation - (float) $observation['value']));

            $neighbors = $observations;
            array_splice($neighbors, $index, 1);
            if (!$neighbors) continue;
            [$predicted] = $this->estimate((float) $longitude, (float) $latitude, $neighbors);
            $errors[] = $predicted - (float) $observation['value'];
        }
        $count = count($errors);
        $absolute = array_map('abs', $errors);
        $squared = array_map(static fn(float $error): float => $error ** 2, $errors);
        $latitudes = array_map(static fn(array $point): float => (float) $point['coordinates'][1], $observations);
        $longitudes = array_map(static fn(array $point): float => (float) $point['coordinates'][0], $observations);
        $spatialSpan = $this->distanceKm(min($latitudes), min($longitudes), max($latitudes), max($longitudes));
        return [
            'sample_count' => $count,
            'population_count' => $population,
            'sampled' => $count < $population,
            'mae' => $count ? round(array_sum($absolute) / $count, 2) : null,
            'rmse' => $count ? round(sqrt(array_sum($squared) / $count), 2) : null,
            'bias' => $count ? round(array_sum($errors) / $count, 2) : null,
            'exact_location_max_error' => round($exactMaximumError, 10),
            'spatial_span_km' => round($spatialSpan, 2),
            'coordinate_extent' => [
                'min_latitude' => min($latitudes), 'max_latitude' => max($latitudes),
                'min_longitude' => min($longitudes), 'max_longitude' => max($longitudes),
            ],
        ];
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
