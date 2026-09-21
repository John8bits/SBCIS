<?php

declare(strict_types=1);

namespace App\Services;

use InvalidArgumentException;
use PDO;

final class AdminDataService
{
    private PDO $database;

    public function __construct(PDO $database)
    {
        $this->database = $database;
    }

    public function page(string $dataset, array $query): array
    {
        $page = max(1, filter_var($query['page'] ?? 1, FILTER_VALIDATE_INT) ?: 1);
        $pageSize = filter_var($query['page_size'] ?? 50, FILTER_VALIDATE_INT) ?: 50;
        $pageSize = max(10, min(100, $pageSize));
        $search = trim((string) ($query['search'] ?? ''));
        if (mb_strlen($search) > 100) throw new InvalidArgumentException('Search text must be 100 characters or fewer.');
        $direction = strtolower((string) ($query['dir'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';
        $lifecycle = ($dataset === 'boreholes' && ($query['status'] ?? '') === 'archived') ? 'archived' : 'active';

        $definition = $this->definition($dataset);
        $sortKey = (string) ($query['sort'] ?? $definition['default_sort']);
        $sort = $definition['sorts'][$sortKey] ?? $definition['sorts'][$definition['default_sort']];
        $conditions = $definition['conditions'] ?? [];
        if ($dataset === 'boreholes') {
            $conditions[] = $lifecycle === 'archived' ? 'b.archived_at IS NOT NULL' : 'b.archived_at IS NULL';
        }
        $parameters = [];
        if ($search !== '') {
            $searchConditions = [];
            foreach ($definition['search'] as $index => $column) {
                $parameter = ':search_' . $index;
                $searchConditions[] = $column . " LIKE {$parameter} ESCAPE '\\\\'";
                $parameters[$parameter] = '%' . addcslashes($search, '\\%_') . '%';
            }
            $conditions[] = '(' . implode(' OR ', $searchConditions) . ')';
        }
        $where = $conditions ? ' WHERE ' . implode(' AND ', $conditions) : '';

        $count = $this->database->prepare('SELECT COUNT(*) FROM (' . $definition['count_source'] . $where . ') counted');
        $count->execute($parameters);
        $total = (int) $count->fetchColumn();
        $pages = max(1, (int) ceil($total / $pageSize));
        $page = min($page, $pages);
        $offset = ($page - 1) * $pageSize;

        $statement = $this->database->prepare($definition['select'] . $where .
            ' ORDER BY ' . $sort . ' ' . $direction . ', ' . $definition['tie_breaker'] .
            ' LIMIT :limit OFFSET :offset');
        foreach ($parameters as $key => $value) $statement->bindValue($key, $value, PDO::PARAM_STR);
        $statement->bindValue(':limit', $pageSize, PDO::PARAM_INT);
        $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
        $statement->execute();

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        if (in_array($dataset, ['boreholes', 'soil_reports'], true)) {
            $boundary = new BoundaryService();
            foreach ($rows as &$row) {
                $row['boundary_status'] = $boundary->contains($row['latitude'], $row['longitude']) ? 'inside' : 'outside';
                if ($dataset === 'boreholes') $row['domain_warnings'] = \Config\DomainConfig::suspiciousValues($row);
            }
            unset($row);
        }

        return [
            'rows' => $rows,
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'page_size' => $pageSize,
            'search' => $search,
            'sort' => $sortKey,
            'dir' => strtolower($direction),
            'status' => $lifecycle,
        ];
    }

    private function definition(string $dataset): array
    {
        $definitions = [
            'boreholes' => [
                'select' => 'SELECT b.borehole_id, b.borehole_code, b.borehole_depth_m, b.latitude, b.longitude,
                    b.elevation_m, b.created_at, b.updated_at, b.archived_at, b.archived_by,
                    m.municipality_name, br.barangay_name,
                    (SELECT COUNT(*) FROM soil_layers sl WHERE sl.borehole_id=b.borehole_id) AS layer_count
                    FROM boreholes b LEFT JOIN municipalities m ON m.municipality_id=b.municipality_id
                    LEFT JOIN barangays br ON br.barangay_id=b.barangay_id',
                'count_source' => 'SELECT b.borehole_id FROM boreholes b
                    LEFT JOIN municipalities m ON m.municipality_id=b.municipality_id
                    LEFT JOIN barangays br ON br.barangay_id=b.barangay_id',
                'search' => ['b.borehole_code', 'm.municipality_name', 'br.barangay_name'],
                'sorts' => ['created' => 'b.created_at', 'code' => 'b.borehole_code', 'depth' => 'b.borehole_depth_m'],
                'default_sort' => 'created', 'tie_breaker' => 'b.borehole_id DESC',
            ],
            'soil_layers' => [
                'select' => 'SELECT sl.layer_number, b.borehole_code, sl.soil_type, sl.soil_classification,
                    sl.soil_description, sl.depth_from_m, sl.depth_to_m, sl.spt_n_value, sl.bearing_capacity_kpa
                    FROM soil_layers sl INNER JOIN boreholes b ON b.borehole_id=sl.borehole_id',
                'count_source' => 'SELECT sl.soil_layer_id FROM soil_layers sl INNER JOIN boreholes b ON b.borehole_id=sl.borehole_id',
                'search' => ['b.borehole_code', 'sl.soil_type', 'sl.soil_classification'],
                'conditions' => ['b.archived_at IS NULL'],
                'sorts' => ['borehole' => 'b.borehole_code', 'depth' => 'sl.depth_from_m', 'created' => 'sl.created_at'],
                'default_sort' => 'borehole', 'tie_breaker' => 'sl.layer_number ASC',
            ],
            'municipalities' => [
                'select' => 'SELECT m.municipality_name,
                    (SELECT COUNT(*) FROM boreholes b WHERE b.municipality_id=m.municipality_id AND b.archived_at IS NULL) AS borehole_count,
                    (SELECT COUNT(*) FROM soil_layers sl JOIN boreholes b ON b.borehole_id=sl.borehole_id WHERE b.municipality_id=m.municipality_id AND b.archived_at IS NULL) AS layer_count
                    FROM municipalities m',
                'count_source' => 'SELECT m.municipality_id FROM municipalities m',
                'search' => ['m.municipality_name'],
                'sorts' => ['name' => 'm.municipality_name'],
                'default_sort' => 'name', 'tie_breaker' => 'm.municipality_id ASC',
            ],
            'barangays' => [
                'select' => 'SELECT br.barangay_name, m.municipality_name,
                    (SELECT COUNT(*) FROM boreholes b WHERE b.barangay_id=br.barangay_id AND b.archived_at IS NULL) AS borehole_count,
                    (SELECT COUNT(*) FROM soil_layers sl JOIN boreholes b ON b.borehole_id=sl.borehole_id WHERE b.barangay_id=br.barangay_id AND b.archived_at IS NULL) AS layer_count
                    FROM barangays br INNER JOIN municipalities m ON m.municipality_id=br.municipality_id',
                'count_source' => 'SELECT br.barangay_id FROM barangays br INNER JOIN municipalities m ON m.municipality_id=br.municipality_id',
                'search' => ['br.barangay_name', 'm.municipality_name'],
                'sorts' => ['name' => 'br.barangay_name', 'municipality' => 'm.municipality_name'],
                'default_sort' => 'municipality', 'tie_breaker' => 'br.barangay_name ASC',
            ],
            'soil_reports' => [
                'select' => 'SELECT b.borehole_code, m.municipality_name, br.barangay_name, b.borehole_depth_m,
                    b.latitude, b.longitude,
                    (SELECT COUNT(*) FROM soil_layers sl WHERE sl.borehole_id=b.borehole_id) AS layer_count,
                    (SELECT MIN(depth_from_m) FROM soil_layers sl WHERE sl.borehole_id=b.borehole_id) AS shallowest_layer_m,
                    (SELECT MAX(depth_to_m) FROM soil_layers sl WHERE sl.borehole_id=b.borehole_id) AS deepest_layer_m,
                    (SELECT MAX(bearing_capacity_kpa) FROM soil_layers sl WHERE sl.borehole_id=b.borehole_id) AS highest_capacity_kpa
                    FROM boreholes b LEFT JOIN municipalities m ON m.municipality_id=b.municipality_id
                    LEFT JOIN barangays br ON br.barangay_id=b.barangay_id',
                'count_source' => 'SELECT b.borehole_id FROM boreholes b LEFT JOIN municipalities m ON m.municipality_id=b.municipality_id
                    LEFT JOIN barangays br ON br.barangay_id=b.barangay_id',
                'search' => ['b.borehole_code', 'm.municipality_name', 'br.barangay_name'],
                'conditions' => ['b.archived_at IS NULL'],
                'sorts' => ['created' => 'b.created_at', 'code' => 'b.borehole_code', 'capacity' => 'highest_capacity_kpa'],
                'default_sort' => 'created', 'tie_breaker' => 'b.borehole_id DESC',
            ],
            'bearing_capacity' => [
                'select' => 'SELECT b.borehole_code, sl.layer_number, sl.soil_type, sl.depth_from_m, sl.depth_to_m,
                    sl.spt_n_value, sl.bearing_capacity_kpa FROM soil_layers sl
                    INNER JOIN boreholes b ON b.borehole_id=sl.borehole_id',
                'count_source' => 'SELECT sl.soil_layer_id FROM soil_layers sl INNER JOIN boreholes b ON b.borehole_id=sl.borehole_id',
                'search' => ['b.borehole_code', 'sl.soil_type'],
                'conditions' => ['b.archived_at IS NULL'],
                'sorts' => ['capacity' => 'sl.bearing_capacity_kpa', 'borehole' => 'b.borehole_code', 'created' => 'sl.created_at'],
                'default_sort' => 'capacity', 'tie_breaker' => 'sl.soil_layer_id DESC',
            ],
        ];
        if (!isset($definitions[$dataset])) throw new InvalidArgumentException('Unsupported admin dataset.');
        return $definitions[$dataset];
    }
}
