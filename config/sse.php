<?php

declare(strict_types=1);

return [
    // Maximum concurrent SSE streams across all PHP workers; null disables the guard.
    // When the limit is reached, new streams get 503 + Retry-After instead of tying up another worker.
    // Requires a shared cache driver (marko/cache-redis or marko/cache-file).
    'max_connections' => isset($_ENV['SSE_MAX_CONNECTIONS']) && $_ENV['SSE_MAX_CONNECTIONS'] !== ''
        ? (int) $_ENV['SSE_MAX_CONNECTIONS']
        : null,
    // Seconds a client rejected by the guard should wait before reconnecting.
    'retry_after' => (int) ($_ENV['SSE_RETRY_AFTER'] ?? 5),
];
