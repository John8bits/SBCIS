<?php

declare(strict_types=1);

namespace App\Services;

use Config\InterpolationConfig;

final class InterpolationDataService
{
    private BoundaryService $boundary;
    private InterpolationConfig $config;

    public function __construct(BoundaryService $boundary, InterpolationConfig $config)
    {
        $this->boundary = $boundary;
        $this->config = $config;
    }

    public function build(array $rows, array $query): array
    {
        $selection = $this->config->selection($query);
        $variable = $selection['variable'];
        $canonical = [];
        $boreholeCandidates = [];
        $reasons = [];
        $nonFieldBoreholes = [];

        // Provenance belongs to the borehole, not an individual layer. If any
        // row identifies a borehole as synthetic/demo data, reject every layer
        // from that borehole so a renamed code or a clean-looking layer cannot
        // accidentally enter the production model.
        foreach ($rows as $row) {
            if (!InterpolationConfig::allowSyntheticTestData() && InterpolationConfig::isNonFieldRecord($row)) {
                $nonFieldBoreholes[(string) ($row['borehole_id'] ?? '')] = true;
            }
        }
        foreach ($rows as $row) {
            $record = [];
            foreach (['borehole_id', 'borehole_code', 'soil_layer_id', 'soil_type', 'soil_description', 'latitude', 'longitude', 'depth_from_m', 'depth_to_m',
                'bearing_capacity_kpa', 'spt_n_value'] as $field) {
                $value = $row[$field] ?? null;
                $record[$field] = $value === null ? null : (is_numeric($value) ? (float) $value : (string) $value);
            }
            $canonical[] = $record;
            $reason = null;
            $boreholeKey = (string) $record['borehole_id'];
            if (isset($nonFieldBoreholes[$boreholeKey])) {
                $reason = 'non_field_record';
            } elseif (!BoundaryService::validCoordinates($record['latitude'], $record['longitude'])) {
                $reason = 'invalid_coordinates';
            } elseif (!$this->boundary->contains($record['latitude'], $record['longitude'])) {
                $reason = 'outside_study_boundary';
            } elseif ($record['soil_layer_id'] === null) {
                $reason = 'no_observation';
            } elseif ($variable === null) {
                $reason = 'variable_not_selected';
            } elseif ($record[$variable] === null || $record[$variable] === '') {
                $reason = 'null_measurement';
            } elseif (!is_numeric($record[$variable]) || !is_finite((float) $record[$variable]) || $record[$variable] < 0) {
                $reason = 'invalid_measurement';
            } elseif (!is_numeric($record['depth_from_m']) || !is_numeric($record['depth_to_m']) ||
                !is_finite((float) $record['depth_from_m']) || !is_finite((float) $record['depth_to_m']) ||
                $record['depth_from_m'] < 0 || $record['depth_to_m'] <= $record['depth_from_m']) {
                $reason = 'invalid_depth_interval';
            }
            if ($reason !== null) {
                $reasons[$reason] = ($reasons[$reason] ?? 0) + 1;
                continue;
            }
            $boreholeCandidates[$boreholeKey][] = [
                'borehole_id' => $record['borehole_id'],
                'borehole_code' => $record['borehole_code'],
                'soil_layer_id' => $record['soil_layer_id'],
                'coordinates' => [$record['longitude'], $record['latitude']],
                'value' => (float) $record[$variable],
                'depth_from_m' => (float) $record['depth_from_m'],
                'depth_to_m' => (float) $record['depth_to_m'],
            ];
        }
        $candidates = [];
        foreach ($boreholeCandidates as $layers) {
            usort($layers, static fn(array $a, array $b): int =>
                [$a['depth_from_m'], $a['depth_to_m'], $a['soil_layer_id']]
                <=> [$b['depth_from_m'], $b['depth_to_m'], $b['soil_layer_id']]
            );
            if (count($layers) > 1) {
                $reasons['deeper_layer_not_selected'] = ($reasons['deeper_layer_not_selected'] ?? 0) + count($layers) - 1;
            }
            $selected = $layers[0];
            $coordinateKey = sprintf('%.7F,%.7F', $selected['coordinates'][0], $selected['coordinates'][1]);
            $candidates[$coordinateKey][] = $selected;
        }
        $points = [];
        foreach ($candidates as $group) {
            // Separate boreholes at the same coordinate are ambiguous. Do not
            // average them silently or let one record win by insertion order.
            if (count($group) > 1) {
                $reasons['duplicate_coordinates'] = ($reasons['duplicate_coordinates'] ?? 0) + count($group);
            } else {
                $points[] = $group[0];
            }
        }
        usort($canonical, static fn($a, $b) => [$a['borehole_id'], $a['soil_layer_id']] <=> [$b['borehole_id'], $b['soil_layer_id']]);
        usort($points, static fn($a, $b) => $a['coordinates'] <=> $b['coordinates']);
        ksort($reasons);
        $values = array_column($points, 'value');
        $configuration = [
            'schema' => InterpolationConfig::PUBLICATION_POLICY_VERSION, 'boundary' => $this->boundary->version(), 'selection' => $selection,
            'public_variables' => $this->config->publicVariables(),
            'technical_minimum_points' => $this->config->minimumPoints(),
            'record_policy' => 'exclude_non_field_then_include_records_with_valid_world_coordinates',
            'duplicate_policy' => 'exclude_all',
            'depth_policy' => 'shallowest_valid_layer_per_borehole',
            'method' => InterpolationConfig::INTERPOLATION_METHOD,
            'idw_power' => InterpolationConfig::IDW_POWER,
            'maximum_support_distance_km' => InterpolationConfig::MAX_SUPPORT_DISTANCE_KM,
            'maximum_validation_points' => InterpolationConfig::MAX_VALIDATION_POINTS,
        ];
        $version = hash('sha256', json_encode([$configuration, $canonical], JSON_THROW_ON_ERROR));
        $status = !$points ? 'no_data' : (count($points) < $this->config->minimumPoints() ? 'insufficient_data' : 'ready');
        if ($variable === null && isset($reasons['variable_not_selected'])) $status = 'pending_configuration';
        return [
            'status' => $status, 'variable' => $variable,
            'unit' => $variable === null ? null : InterpolationConfig::VARIABLES[$variable]['unit'],
            'scope' => $selection['scope'], 'version' => $version,
            'eligible_count' => count($points), 'excluded_count' => array_sum($reasons),
            'exclusion_reasons' => (object) $reasons,
            'outside_borehole_count' => $reasons['outside_study_boundary'] ?? 0,
            'non_field_borehole_count' => count($nonFieldBoreholes),
            'observed_range' => $values ? ['min' => min($values), 'max' => max($values)] : null,
            'points' => $points, 'available_variables' => $this->config->publicVariables(),
            'available_scopes' => ['province'],
            'technical_minimum_points' => $this->config->minimumPoints(),
            'generation_enabled' => $variable === InterpolationConfig::APPROVED_VARIABLE,
            'pending_decisions' => [],
        ];
    }
}
