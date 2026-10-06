<?php

declare(strict_types=1);

use Marko\Config\Env;

return [
    /*
     * Shared secret that protects GET /health. When set, callers must send it
     * in the X-Health-Secret header or the ?secret= query parameter; requests
     * without a matching secret get a 404. Null (or an empty string) leaves
     * the endpoint public.
     */
    'secret' => Env::nullableString('HEALTH_SECRET'),
];
