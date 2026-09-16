<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

final class BoundaryService
{
    private array $polygons = [];
    private string $version;

    public function __construct(?array $collection = null)
    {
        if ($collection === null) {
            $file = dirname(__DIR__, 2) . '/src/qgis/southern_leyte_boundary.geojson';
            $json = @file_get_contents($file);
            if ($json === false) {
                throw new RuntimeException('Study boundary unavailable.');
            }
            $collection = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        }
        if (($collection['type'] ?? '') !== 'FeatureCollection' || empty($collection['features'])) {
            throw new RuntimeException('Invalid study boundary.');
        }
        foreach ($collection['features'] as $feature) {
            $geometry = $feature['geometry'] ?? [];
            $type = $geometry['type'] ?? '';
            if (!in_array($type, ['Polygon', 'MultiPolygon'], true) || empty($geometry['coordinates'])) {
                throw new RuntimeException('Unsupported study geometry.');
            }
            $polygons = $type === 'Polygon' ? [$geometry['coordinates']] : $geometry['coordinates'];
            foreach ($polygons as $polygon) {
                if (!$polygon) throw new RuntimeException('Empty polygon.');
                foreach ($polygon as $ring) {
                    if (count($ring) < 4 || $ring[0] !== $ring[count($ring) - 1]) {
                        throw new RuntimeException('Invalid boundary ring.');
                    }
                    foreach ($ring as $position) {
                        if (!is_array($position) || count($position) < 2 ||
                            !self::validCoordinates($position[1], $position[0])) {
                            throw new RuntimeException('Invalid boundary position.');
                        }
                    }
                }
                $this->polygons[] = $polygon;
            }
        }
        $this->version = hash('sha256', json_encode($this->polygons, JSON_THROW_ON_ERROR));
    }

    public function version(): string
    {
        return $this->version;
    }

    public static function validCoordinates($latitude, $longitude): bool
    {
        foreach ([$latitude, $longitude] as $value) {
            if (!is_scalar($value) || is_bool($value) || !is_numeric($value) || !is_finite((float) $value)) return false;
        }
        return (float) $latitude >= -90 && (float) $latitude <= 90 &&
            (float) $longitude >= -180 && (float) $longitude <= 180;
    }

    public function contains($latitude, $longitude): bool
    {
        if (!self::validCoordinates($latitude, $longitude)) return false;
        foreach ($this->polygons as $polygon) {
            if ($this->ringLocation((float) $longitude, (float) $latitude, $polygon[0]) === 0) continue;
            $insideHole = false;
            foreach (array_slice($polygon, 1) as $hole) {
                // Exterior edges are included; hole interiors and edges are excluded.
                if ($this->ringLocation((float) $longitude, (float) $latitude, $hole) !== 0) {
                    $insideHole = true;
                    break;
                }
            }
            if (!$insideHole) return true;
        }
        return false;
    }

    /**
     * Reject generated polygons that extend beyond the configured study area.
     * Output vertices and every edge midpoint must remain in/on the province;
     * proper crossings of any province ring are also rejected. Approved
     * generators are still expected to perform the actual polygon clipping.
     */
    public function coversFeatureCollection(array $collection): bool
    {
        if (($collection['type'] ?? '') !== 'FeatureCollection' || empty($collection['features'])) return false;
        foreach ($collection['features'] as $feature) {
            $geometry = $feature['geometry'] ?? [];
            $type = $geometry['type'] ?? '';
            if (!in_array($type, ['Polygon', 'MultiPolygon'], true)) return false;
            $polygons = $type === 'Polygon' ? [$geometry['coordinates'] ?? []] : ($geometry['coordinates'] ?? []);
            foreach ($polygons as $polygon) {
                foreach ($polygon as $ring) {
                    for ($i = 1; $i < count($ring); $i++) {
                        $a = $ring[$i - 1];
                        $b = $ring[$i];
                        if (!$this->contains($a[1] ?? null, $a[0] ?? null) ||
                            !$this->contains($b[1] ?? null, $b[0] ?? null) ||
                            !$this->contains((($a[1] ?? 0) + ($b[1] ?? 0)) / 2, (($a[0] ?? 0) + ($b[0] ?? 0)) / 2) ||
                            $this->crossesBoundary($a, $b)) return false;
                    }
                }
            }
        }
        return true;
    }

    private function crossesBoundary(array $a, array $b): bool
    {
        foreach ($this->polygons as $polygon) {
            foreach ($polygon as $ring) {
                for ($i = 1; $i < count($ring); $i++) {
                    if ($this->properSegmentsIntersect($a, $b, $ring[$i - 1], $ring[$i])) return true;
                }
            }
        }
        return false;
    }

    private function properSegmentsIntersect(array $a, array $b, array $c, array $d): bool
    {
        $abC = $this->orientation($a, $b, $c);
        $abD = $this->orientation($a, $b, $d);
        $cdA = $this->orientation($c, $d, $a);
        $cdB = $this->orientation($c, $d, $b);
        return $abC * $abD < 0 && $cdA * $cdB < 0;
    }

    private function orientation(array $a, array $b, array $c): int
    {
        $cross = (($b[0] ?? 0) - ($a[0] ?? 0)) * (($c[1] ?? 0) - ($a[1] ?? 0)) -
            (($b[1] ?? 0) - ($a[1] ?? 0)) * (($c[0] ?? 0) - ($a[0] ?? 0));
        if (abs($cross) <= 1e-12) return 0;
        return $cross > 0 ? 1 : -1;
    }

    private function ringLocation(float $x, float $y, array $ring): int
    {
        $inside = false;
        for ($i = 1; $i < count($ring); $i++) {
            [$ax, $ay] = $ring[$i - 1];
            [$bx, $by] = $ring[$i];
            $cross = ($x - $ax) * ($by - $ay) - ($y - $ay) * ($bx - $ax);
            if (abs($cross) <= 1e-12 && $x >= min($ax, $bx) && $x <= max($ax, $bx) &&
                $y >= min($ay, $by) && $y <= max($ay, $by)) return 2;
            if (($ay > $y) !== ($by > $y) && $x < ($bx - $ax) * ($y - $ay) / ($by - $ay) + $ax) {
                $inside = !$inside;
            }
        }
        return $inside ? 1 : 0;
    }
}
