<?php

declare(strict_types=1);

use Marko\PubSub\Subscription;
use Marko\Sse\SseEvent;
use Marko\Sse\SseStream;
use Marko\Testing\Fake\FakeClock;

describe('SseStream clock', function (): void {
    it('ends a data provider stream once the injected clock reaches the timeout', function (): void {
        $clock = new FakeClock('2026-01-01 12:00:00 UTC');
        $calls = 0;

        $stream = new SseStream(
            dataProvider: function () use ($clock, &$calls): array {
                $calls++;
                $clock->travel('+10 seconds');

                return [new SseEvent(data: "tick-$calls")];
            },
            heartbeatInterval: 9999,
            timeout: 30,
            pollInterval: 0,
            clock: $clock,
        );

        $chunks = iterator_to_array($stream, preserve_keys: false);

        expect($calls)->toBe(3)
            ->and($chunks)->toHaveCount(3);
    });

    it('emits a heartbeat once the injected clock reaches the heartbeat interval', function (): void {
        $clock = new FakeClock('2026-01-01 12:00:00 UTC');

        $stream = new SseStream(
            dataProvider: function () use ($clock): array {
                $clock->travel('+5 seconds');

                return [];
            },
            heartbeatInterval: 15,
            timeout: 20,
            pollInterval: 0,
            clock: $clock,
        );

        // Ticks at +5, +10, +15 (heartbeat due), +20 (timeout).
        expect(iterator_to_array($stream, preserve_keys: false))->toBe([": keepalive\n\n"]);
    });

    it('ends a subscription stream once the injected clock reaches the timeout', function (): void {
        $clock = new FakeClock('2026-01-01 12:00:00 UTC');

        $subscription = new class ($clock) implements Subscription
        {
            public int $ticks = 0;

            public function __construct(
                private readonly FakeClock $clock,
            ) {}

            public function getIterator(): Generator
            {
                while (true) {
                    $this->ticks++;
                    $this->clock->travel('+1 minute');

                    yield null;
                }
            }

            public function cancel(): void {}
        };

        $stream = new SseStream(
            subscription: $subscription,
            heartbeatInterval: 120,
            timeout: 300,
            pollInterval: 0,
            clock: $clock,
        );

        $chunks = iterator_to_array($stream, preserve_keys: false);

        // Idle minutes 1-4 pass, minute 2 and 4 emit heartbeats, minute 5 hits the timeout.
        expect($subscription->ticks)->toBe(5)
            ->and($chunks)->toBe([": keepalive\n\n", ": keepalive\n\n"]);
    });

    it('reads the system clock when no clock is passed', function (): void {
        $stream = new SseStream(
            dataProvider: fn (): array => [new SseEvent(data: 'once')],
            timeout: 0,
            pollInterval: 0,
        );

        expect(iterator_to_array($stream, preserve_keys: false))->toHaveCount(1);
    });
});
