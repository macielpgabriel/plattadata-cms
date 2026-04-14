<?php

declare(strict_types=1);

use App\Services\ObservabilityService;

function observability_test_cases(): array
{
    $service = new ObservabilityService();
    $cases = [];

    $snapshot = $service->healthSnapshot();
    $cases['health_snapshot_has_status'] = isset($snapshot['status']) && in_array($snapshot['status'], ['ok', 'degraded'], true);
    $cases['health_snapshot_has_database_node'] = isset($snapshot['database']) && is_array($snapshot['database']);
    $cases['health_snapshot_has_checked_at'] = !empty($snapshot['checked_at']) && is_string($snapshot['checked_at']);

    return normalize_cases($cases);
}
