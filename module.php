<?php

declare(strict_types=1);

use Marko\Cache\Contracts\CacheInterface;
use Marko\Config\ConfigRepositoryInterface;
use Marko\Core\Container\ContainerInterface;
use Marko\Sse\SseConnectionLimiter;

return [
    'bindings' => [
        SseConnectionLimiter::class => static function (ContainerInterface $container): SseConnectionLimiter {
            $config = $container->get(ConfigRepositoryInterface::class);
            $maxConnections = $config->get(key: 'sse.max_connections');

            return new SseConnectionLimiter(
                cache: $container->get(CacheInterface::class),
                maxConnections: $maxConnections === null ? null : $config->getInt(key: 'sse.max_connections'),
                retryAfter: $config->getInt(key: 'sse.retry_after'),
            );
        },
    ],
];
