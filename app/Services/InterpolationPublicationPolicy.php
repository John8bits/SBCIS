<?php

declare(strict_types=1);

namespace App\Services;

final class InterpolationPublicationPolicy
{
    public function evaluate(array $input, array $output): array
    {
        $validation = $output['validation'] ?? [];
        $configured = [
            'max_mae_kpa' => $this->positiveEnvironment('SBCIS_INTERPOLATION_MAX_MAE_KPA'),
            'max_rmse_kpa' => $this->positiveEnvironment('SBCIS_INTERPOLATION_MAX_RMSE_KPA'),
            'min_span_km' => $this->positiveEnvironment('SBCIS_INTERPOLATION_MIN_SPAN_KM'),
        ];
        $reasons = [];
        if (getenv('SBCIS_INTERPOLATION_REVIEWED') !== '1') {
            $reasons[] = 'A qualified reviewer has not approved the publication policy.';
        }
        foreach ($configured as $name => $value) {
            if ($value === null) $reasons[] = 'The reviewed ' . str_replace('_', ' ', $name) . ' threshold is not configured.';
        }
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
        if ($status === 'approved') {
            if ((float) $validation['mae'] > $configured['max_mae_kpa']) $reasons[] = 'MAE exceeds the reviewed threshold.';
            if ((float) $validation['rmse'] > $configured['max_rmse_kpa']) $reasons[] = 'RMSE exceeds the reviewed threshold.';
            if ((float) ($validation['spatial_span_km'] ?? 0) < $configured['min_span_km']) $reasons[] = 'Observation distribution is below the reviewed spatial span.';
            if ($reasons) $status = 'validation_failed';
        }

        return [
            'status' => $status,
            'reasons' => $reasons,
            'thresholds' => $configured,
            'policy_version' => \Config\InterpolationConfig::PUBLICATION_POLICY_VERSION,
        ];
    }

    private function positiveEnvironment(string $name): ?float
    {
        $value = getenv($name);
        if (!is_string($value) || $value === '' || !is_numeric($value) || !is_finite((float) $value) || (float) $value <= 0) return null;
        return (float) $value;
    }
}
