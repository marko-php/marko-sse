<?php

declare(strict_types=1);

namespace Marko\Sse;

use JsonException;
use Marko\Cache\Exceptions\InvalidKeyException;
use Marko\Routing\Http\Response;
use Override;
use Random\RandomException;

class StreamingResponse extends Response
{
    /**
     * Extra seconds a connection slot outlives the stream timeout, covering the final poll interval
     * and shutdown. If the worker dies without releasing its slot, the slot frees itself after
     * stream timeout + this grace period.
     */
    public const int SLOT_TTL_GRACE_SECONDS = 60;

    public function __construct(
        private SseStream $stream,
        int $statusCode = 200,
        private ?SseConnectionLimiter $connectionLimiter = null,
    ) {
        parent::__construct(
            body: '',
            statusCode: $statusCode,
            headers: [
                'Content-Type' => 'text/event-stream',
                'Cache-Control' => 'no-cache',
                'Connection' => 'keep-alive',
                'X-Accel-Buffering' => 'no',
            ],
        );
    }

    /**
     * @throws JsonException|InvalidKeyException|RandomException
     */
    #[Override]
    public function send(): void
    {
        if ($this->isBodyOmitted()) {
            // HEAD request: send the stream's headers, never open the stream.
            $this->stream->close();
            parent::send();

            return;
        }

        $slot = null;

        if ($this->connectionLimiter?->isLimited()) {
            $slot = $this->connectionLimiter->acquire($this->stream->timeout() + self::SLOT_TTL_GRACE_SECONDS);

            if ($slot === null) {
                $this->stream->close();
                $this->serviceUnavailableResponse()->send();

                return;
            }
        }

        try {
            if (!headers_sent()) {
                http_response_code($this->statusCode());

                foreach ($this->headerLines() as $line) {
                    header($line);
                }
            }

            $this->prepareOutput();

            foreach ($this->stream as $chunk) {
                if ($this->connectionAborted()) {
                    break;
                }

                echo $chunk;
                flush();
            }
        } finally {
            $this->stream->close();

            if ($slot !== null) {
                $this->connectionLimiter->release($slot);
            }
        }
    }

    /**
     * The response sent instead of the stream when every connection slot is taken.
     *
     * EventSource clients do not reconnect after a 503, so the page should retry
     * after the Retry-After delay (see the marko/sse docs).
     */
    public function serviceUnavailableResponse(): Response
    {
        return new Response(
            body: '',
            statusCode: 503,
            headers: [
                'Retry-After' => (string) ($this->connectionLimiter?->retryAfter() ?? 0),
                'Cache-Control' => 'no-cache',
            ],
        );
    }

    /**
     * Disable output buffering and the execution time limit so chunks reach the client as they are produced.
     */
    protected function prepareOutput(): void
    {
        while (ob_get_level() > 0) {
            ob_end_flush();
        }

        ob_implicit_flush(true);
        set_time_limit(0);
    }

    protected function connectionAborted(): bool
    {
        return connection_aborted() === 1;
    }
}
