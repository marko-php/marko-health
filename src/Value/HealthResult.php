<?php

declare(strict_types=1);

namespace Marko\Health\Value;

use Throwable;

readonly class HealthResult
{
    /**
     * @param string $message Public, generic description. The /health endpoint
     *     serves it, so it must never contain exception details.
     * @param Throwable|null $exception The failure behind an unhealthy result,
     *     kept for logging and debugging. It is never serialized into the response.
     */
    public function __construct(
        public string $name,
        public HealthStatus $status,
        public string $message,
        public array $metadata,
        public float $duration,
        public ?Throwable $exception = null,
    ) {}

    public function isHealthy(): bool
    {
        return $this->status === HealthStatus::Healthy;
    }

    public function isDegraded(): bool
    {
        return $this->status === HealthStatus::Degraded;
    }

    public function isUnhealthy(): bool
    {
        return $this->status === HealthStatus::Unhealthy;
    }
}
