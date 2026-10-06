<?php

declare(strict_types=1);

use Marko\Config\Exceptions\ConfigException;

const SSE_CONFIG_FILE = __DIR__ . '/../config/sse.php';

beforeEach(function (): void {
    $this->originalMaxConnections = $_ENV['SSE_MAX_CONNECTIONS'] ?? null;
    unset($_ENV['SSE_MAX_CONNECTIONS']);
});

afterEach(function (): void {
    if ($this->originalMaxConnections === null) {
        unset($_ENV['SSE_MAX_CONNECTIONS']);
    } else {
        $_ENV['SSE_MAX_CONNECTIONS'] = $this->originalMaxConnections;
    }
});

it('reads SSE_MAX_CONNECTIONS as null when unset or empty', function (): void {
    expect((require SSE_CONFIG_FILE)['max_connections'])->toBeNull();

    $_ENV['SSE_MAX_CONNECTIONS'] = '';

    expect((require SSE_CONFIG_FILE)['max_connections'])->toBeNull();
});

it('reads SSE_MAX_CONNECTIONS as an integer when set', function (): void {
    $_ENV['SSE_MAX_CONNECTIONS'] = '200';

    expect((require SSE_CONFIG_FILE)['max_connections'])->toBe(200);
});

it('rejects an SSE_MAX_CONNECTIONS that is not a positive integer', function (string $value): void {
    $_ENV['SSE_MAX_CONNECTIONS'] = $value;

    expect(fn (): array => require SSE_CONFIG_FILE)->toThrow(ConfigException::class, 'SSE_MAX_CONNECTIONS');
})->with(['abc', '0', '1.5']);
