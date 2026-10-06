<?php

declare(strict_types=1);

namespace Marko\Health\Exceptions;

use Marko\Core\Exceptions\MarkoException;

class HealthException extends MarkoException
{
    public static function invalidSecret(
        string $type,
    ): self {
        return new self(
            message: "Config key 'health.secret' must be a string or null, $type given.",
            context: 'While reading the secret that protects the /health endpoint.',
            suggestion: "Set 'health.secret' to a string (e.g. from an environment variable) or null to leave the endpoint public.",
        );
    }
}
