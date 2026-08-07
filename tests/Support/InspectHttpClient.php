<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests\Support;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;

/**
 * Stub client that returns scripted HEAD responses and counts requests.
 *
 * @phpstan-type Meta array{
 *     status: int,
 *     headers: array<string, list<string>>,
 *     url: string
 * }
 */
final class InspectHttpClient implements HttpClientInterface
{
    /** @var list<Meta> */
    private array $queue;

    public int $requestCount = 0;

    /**
     * @param list<Meta> $responses
     */
    public function __construct(
        array $responses,
    ) {
        $this->queue = $responses;
    }

    /**
     * @param array<string, mixed> $options
     */
    public function request(
        string $method,
        string $url,
        array  $options = [],
    ): ResponseInterface {
        $this->requestCount++;

        $meta = \array_shift($this->queue);

        if ($meta === null) {
            throw new \LogicException('InspectHttpClient queue exhausted.');
        }

        return new class($meta) implements ResponseInterface {
            /**
             * @param array{status: int, headers: array<string, list<string>>, url: string} $meta
             */
            public function __construct(
                /** @var array{status: int, headers: array<string, list<string>>, url: string} */
                private readonly array $meta,
            ) {}

            public function getStatusCode(): int
            {
                return $this->meta['status'];
            }

            /**
             * @return array<string, list<string>>
             */
            public function getHeaders(
                bool $throw = true,
            ): array {
                return $this->meta['headers'];
            }

            public function getContent(
                bool $throw = true,
            ): string {
                return '';
            }

            /**
             * @return array<string, mixed>
             */
            public function toArray(
                bool $throw = true,
            ): array {
                return [];
            }

            public function cancel(): void {}

            public function getInfo(
                null|string $type = null,
            ): mixed {
                if ($type === 'url') {
                    return $this->meta['url'];
                }

                if ($type === null) {
                    return ['url' => $this->meta['url']];
                }

                return null;
            }
        };
    }

    public function stream(
        ResponseInterface|iterable $responses,
        null|float                 $timeout = null,
    ): ResponseStreamInterface {
        throw new \LogicException('Not used by inspect() tests.');
    }

    /**
     * @param array<string, mixed> $options
     */
    public function withOptions(
        array $options,
    ): static {
        return $this;
    }
}
