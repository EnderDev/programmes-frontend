<?php
declare(strict_types = 1);

namespace App\Metrics\Backend;

class NullMetricBackend implements MetricBackendInterface
{
    /**
     * @param ProgrammesMetricInterface[] $metrics
     */
    public function sendMetrics(array $metrics): void
    {
        return;
    }
}