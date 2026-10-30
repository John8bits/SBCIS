<?php

declare(strict_types=1);

namespace App\Services;

final class InterpolationPublicationPolicy
{
    public function evaluate(array $input, array $output): array
    {
        $validation = $output['validation'] ?? [];
        $reasons = [];
        if (($input['outside_borehole_count'] ?? 0) > 0) {
            $reasons[] = 'One or more source rows are outside the study boundary and were quarantined.';
        }
        $exclusions = $input['exclusion_reasons'] ?? null;
        $duplicates = is_object($exclusions) ? ($exclusions->duplicate_coordinates ?? 0) :
            (is_array($exclusions) ? ($exclusions['duplicate_coordinates'] ?? 0) : 0);
        if ($duplicates > 0) {
            $reasons[] = 'Duplicate observation coordinates require data review.';
        }

        $status = $reasons ? 'review_required' : 'approved';
        if ((float) ($validation['exact_location_max_error'] ?? INF) > 1.0e-8) {
            $reasons[] = 'Exact-location reproduction failed.';
            $status = 'validation_failed';
        }

        return [
            'status' => $status,
            'mode' => 'screening',
            'reasons' => $reasons,
            'thresholds' => [],
            'policy_version' => \Config\InterpolationConfig::PUBLICATION_POLICY_VERSION,
        ];
    }

}
