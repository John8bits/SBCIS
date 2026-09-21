<?php
declare(strict_types=1);

namespace App\Services;

use Config\InterpolationConfig;
use RuntimeException;
use Throwable;

final class InterpolationPublicationService
{
    private $prepare;
    private $generate;
    private $revision;
    private $policy;
    private InterpolationResultStore $store;

    public function __construct(callable $prepare, InterpolationResultStore $store, ?callable $generate = null,
        ?callable $revision = null, ?callable $policy = null)
    {
        $this->prepare = $prepare;
        $this->store = $store;
        $this->generate = $generate;
        $this->revision = $revision;
        $this->policy = $policy ?? static fn(): array => [
            'status'=>'review_required', 'reasons'=>['No reviewed publication policy is configured.'],
            'thresholds'=>[], 'policy_version'=>InterpolationConfig::PUBLICATION_POLICY_VERSION,
        ];
    }

    public function status(): array
    {
        $input = ($this->prepare)();
        $state = $this->store->read();
        $published = $state['published'];
        $status = $input['status'];
        if ($status === 'ready') {
            $status = $this->isCurrent($published, $input) ? 'current' : 'needs_regeneration';
            if (!$input['generation_enabled'] || !$this->generate) $status = 'pending_configuration';
        }
        if (($state['attempt']['source_hash'] ?? null) === $input['version'] &&
            in_array(($state['attempt']['status'] ?? ''), ['generation_failed','review_required','validation_failed'], true)) {
            $status = $state['attempt']['status'];
        }
        $candidate = ($state['candidate']['source_hash'] ?? null) === $input['version'] ? $state['candidate'] : null;
        unset($input['points'], $input['available_variables'], $input['available_scopes']);
        return array_merge($input, [
            'status' => $status, 'source_hash' => $input['version'],
            'last_generated_at' => $published['generated_at'] ?? null,
            'interpolation_version' => $published['interpolation_version'] ?? null,
            'method' => $published['method'] ?? null,
            'validation' => $this->isCurrent($published, $input) ? ($published['validation'] ?? null) : ($candidate['validation'] ?? null),
            'interpolated_range' => $this->isCurrent($published, $input) ? ($published['legend'] ?? null) : ($candidate['legend'] ?? null),
            'publication_review' => $candidate['review'] ?? null,
            'published_outdated' => $published !== null && !$this->isCurrent($published, $input),
            'last_attempt_at' => $state['attempt']['checked_at'] ?? null,
            'job' => $state['job'] ?? null,
        ]);
    }

    public function result(): array
    {
        $state = $this->store->read();
        $published = $state['published'];
        if ($this->revision !== null) {
            $currentRevision = ($this->revision)();
            if ($published === null || !empty($state['outdated']) ||
                ($published['source_revision'] ?? null) !== $currentRevision ||
                ($published['policy_version'] ?? null) !== InterpolationConfig::PUBLICATION_POLICY_VERSION) {
                return ['status' => $published ? 'outdated' : 'unavailable', 'result' => null];
            }
            return ['status'=>'current', 'result'=>$this->publicResult($published)];
        }
        $input = ($this->prepare)();
        if (!$this->isCurrent($published, $input)) {
            return ['status' => $published ? 'outdated' : 'unavailable', 'result' => null];
        }
        return ['status' => 'current', 'result' => $this->publicResult($published)];
    }

    private function publicResult(array $published): array
    {
        $public = $published;
        $values = [];
        foreach ($public['surface']['features'] ?? [] as $feature) {
            $properties = $feature['properties'] ?? [];
            $identifier = $properties['GID_3'] ?? null;
            if (!is_string($identifier) || $identifier === '') continue;
            $values[] = [
                'boundary_id'=>$identifier,
                'value'=>$properties['value'] ?? null,
                'classification'=>$properties['classification'] ?? null,
                'support_distance_km'=>$properties['support_distance_km'] ?? null,
                'observation_count'=>$properties['observation_count'] ?? null,
                'prediction_coordinates'=>$properties['prediction_coordinates'] ?? null,
            ];
        }
        $public['surface_values'] = $values;
        unset($public['surface'], $public['validation']);
        return $public;
    }

    private function isCurrent(?array $published, array $input): bool
    {
        return $published !== null && $input['generation_enabled'] === true &&
            ($published['source_hash'] ?? '') === $input['version'] &&
            ($published['policy_version'] ?? null) === InterpolationConfig::PUBLICATION_POLICY_VERSION;
    }

    public function regenerate(): array
    {
        $claim = ($this->prepare)();
        if (($claim['status'] ?? null) === 'ready' && !empty($claim['generation_enabled']) && $this->generate) {
            $this->store->beginGeneration((string) $claim['version'], isset($claim['source_revision']) ? (int) $claim['source_revision'] : null);
        }
        $this->store->update(function (array $state): array {
            $input = null;
            try {
                $input = ($this->prepare)();
                $status = $input['status'];
                if ($status === 'ready' && (!$input['generation_enabled'] || !$this->generate)) $status = 'pending_configuration';
                if ($status === 'ready') {
                    $output = ($this->generate)($input);
                    $this->validateOutput($output, $input);
                    $output['legend']['min'] = (float) $output['legend']['min'];
                    $output['legend']['max'] = (float) $output['legend']['max'];
                    $review = ($this->policy)($input, $output);
                    if (!is_array($review) || !in_array($review['status'] ?? '', ['approved','review_required','validation_failed'], true)) {
                        throw new RuntimeException('Invalid interpolation publication review.');
                    }
                    $state['candidate'] = [
                        'source_hash'=>$input['version'], 'generated_at'=>gmdate('c'),
                        'legend'=>$output['legend'], 'validation'=>$output['validation'], 'review'=>$review,
                    ];
                    $latest = ($this->prepare)();
                    if ($latest['version'] !== $input['version']) {
                        $status = 'needs_regeneration';
                    } elseif ($review['status'] === 'approved') {
                        $state['published'] = [
                            'source_hash' => $input['version'], 'generated_at' => gmdate('c'),
                            'source_revision' => $input['source_revision'] ?? null,
                            'interpolation_version' => hash('sha256', json_encode([$input['version'], $output], JSON_THROW_ON_ERROR)),
                            'variable' => $input['variable'], 'method' => $output['method'],
                            'legend' => $output['legend'], 'surface' => $output['surface'],
                            'validation' => $output['validation'] ?? null,
                            'policy_version' => InterpolationConfig::PUBLICATION_POLICY_VERSION,
                        ];
                        $status = 'current';
                    } else {
                        $status = $review['status'];
                    }
                }
                $state['outdated'] = $status !== 'current';
                $state['attempt'] = ['status' => $status, 'source_hash' => $input['version'], 'checked_at' => gmdate('c')];
                $state['job'] = [
                    'status'=>$status === 'current' ? 'succeeded' : $status,
                    'source_hash'=>$input['version'], 'queued_revision'=>$input['source_revision'] ?? null,
                    'finished_at'=>gmdate('c'), 'updated_at'=>gmdate('c'),
                ];
            } catch (Throwable $error) {
                error_log('Interpolation generation: ' . $error->getMessage());
                // Never replace the previous publication with failed or incomplete output.
                $state['attempt'] = ['status' => 'generation_failed', 'source_hash' => $input['version'] ?? null, 'checked_at' => gmdate('c')];
                $state['outdated'] = true;
                $state['job'] = [
                    'status'=>'failed', 'source_hash'=>$input['version'] ?? null,
                    'queued_revision'=>$input['source_revision'] ?? null,
                    'finished_at'=>gmdate('c'), 'updated_at'=>gmdate('c'),
                ];
            }
            return $state;
        });
        return $this->status();
    }

    private function validateOutput(array $output, array $input): void
    {
        $legend = $output['legend'] ?? [];
        if (!is_string($output['method'] ?? null) || $output['method'] === '' ||
            !is_numeric($legend['min'] ?? null) || !is_numeric($legend['max'] ?? null) ||
            !is_finite((float) $legend['min']) || !is_finite((float) $legend['max']) ||
            $legend['min'] > $legend['max'] || ($legend['unit'] ?? null) !== $input['unit'] ||
            ($legend['classes'] ?? null) !== InterpolationConfig::bearingCapacityClasses() ||
            ($output['surface']['type'] ?? '') !== 'FeatureCollection' || empty($output['surface']['features'])) {
            throw new RuntimeException('Invalid generated surface metadata.');
        }
        foreach ($output['surface']['features'] as $feature) {
            if (!in_array($feature['geometry']['type'] ?? '', ['Polygon', 'MultiPolygon'], true) ||
                !is_numeric($feature['properties']['value'] ?? null) || !is_finite((float) $feature['properties']['value']) ||
                $feature['properties']['value'] < $legend['min'] || $feature['properties']['value'] > $legend['max']) {
                throw new RuntimeException('Invalid generated surface cell.');
            }
        }
        $validation = $output['validation'] ?? null;
        if (!is_array($validation) || !is_int($validation['sample_count'] ?? null) ||
            $validation['sample_count'] < 1 || $validation['sample_count'] > count($input['points'] ?? []) ||
            ($validation['population_count'] ?? null) !== count($input['points'] ?? []) ||
            !is_bool($validation['sampled'] ?? null) ||
            !is_numeric($validation['mae'] ?? null) || !is_numeric($validation['rmse'] ?? null) ||
            !is_numeric($validation['bias'] ?? null) || !is_numeric($validation['exact_location_max_error'] ?? null) ||
            !is_numeric($validation['spatial_span_km'] ?? null) ||
            !is_finite((float) $validation['mae']) || !is_finite((float) $validation['rmse']) ||
            !is_finite((float) $validation['bias']) || !is_finite((float) $validation['exact_location_max_error']) ||
            !is_finite((float) $validation['spatial_span_km']) || $validation['spatial_span_km'] < 0 ||
            $validation['mae'] < 0 || $validation['rmse'] < 0 || $validation['exact_location_max_error'] < 0) {
            throw new RuntimeException('Invalid interpolation validation metrics.');
        }
        // Validate ring structure, then fail closed unless every generated cell
        // stays inside the real province geometry. The generator must clip;
        // this guard prevents an unclipped artifact from ever being published.
        new BoundaryService($output['surface']);
        if (!$this->isTrustedBarangaySubset($output) && !(new BoundaryService())->coversFeatureCollection($output['surface'])) {
            throw new RuntimeException('Generated surface extends outside the study boundary.');
        }
    }

    private function isTrustedBarangaySubset(array $output): bool
    {
        $path = dirname(__DIR__, 2) . '/src/qgis/southern_leyte_barangays.geojson';
        $json = @file_get_contents($path);
        if ($json === false || !is_string($output['geometry_source_hash'] ?? null) ||
            !hash_equals(hash('sha256', $json), $output['geometry_source_hash'])) return false;

        $source = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        $approved = [];
        foreach ($source['features'] ?? [] as $feature) {
            $approved[hash('sha256', json_encode($feature['geometry'] ?? null, JSON_THROW_ON_ERROR))] = true;
        }
        foreach ($output['surface']['features'] ?? [] as $feature) {
            $hash = hash('sha256', json_encode($feature['geometry'] ?? null, JSON_THROW_ON_ERROR));
            if (!isset($approved[$hash])) return false;
        }
        return true;
    }
}
