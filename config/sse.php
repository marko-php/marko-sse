<?php

declare(strict_types=1);

use Marko\Config\Env;

return [
    // Maximum concurrent SSE streams across all PHP workers; null (unset or empty) disables the guard.
    // When the limit is reached, new streams get 503 + Retry-After instead of tying up another worker.
    // Requires a shared cache driver (marko/cache-redis or marko/cache-file).
    'max_connections' => Env::nullableInt('SSE_MAX_CONNECTIONS', min: 1),
    // Seconds a client rejected by the guard should wait before reconnecting.
    'retry_after' => Env::int('SSE_RETRY_AFTER', 5, min: 0),
];
