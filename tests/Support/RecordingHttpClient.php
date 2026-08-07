<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests\Support;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;

/**
 * Records {@see withOptions()} and {@see request()} arguments across clones.
 */
final class RecordingHttpClient implements HttpClientInterface
{
    /**
     * @var \stdClass&object{
     *     withOptionsCalls: list<array<string, mixed>>,
     *     requestCalls: list<array{method: string, url: string, options: array<string, mixed>}>
     * }
     */
    private readonly \stdClass $journal;

    public function __construct()
    {
        $this->journal = (object) [
            'withOptionsCalls' => [],
            'requestCalls'     => [],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function withOptionsCalls(): array
    {
        return $this->journal->withOptionsCalls;
    }

    /**
     * @return list<array{method: string, url: string, options: array<string, mixed>}>
     */
    public function requestCalls(): array
    {
        return $this->journal->requestCalls;
    }

    /**
     * @param array<string, mixed> $options
     */
    public function request(
        string $method,
        string $url,
        array  $options = [],
    ): ResponseInterface {
        $this->journal->requestCalls[] = [
            'method'  => $method,
            'url'     => $url,
            'options' => $options,
        ];

        return new class implements ResponseInterface {
            public function getStatusCode(): int
            {
                return 200;
            }

            /**
             * @return array<string, list<string>>
             */
            public function getHeaders(
                bool $throw = true,
            ): array {
                return [];
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
                    return 'https://example.test/';
                }

                if ($type === null) {
                    return ['url' => 'https://example.test/'];
                }

                return null;
            }
        };
    }

    public function stream(
        ResponseInterface|iterable $responses,
        null|float                 $timeout = null,
    ): ResponseStreamInterface {
        throw new \LogicException('Not used by CURL client tests.');
    }

    /**
     * @param array<string, mixed> $options
     */
    public function withOptions(
        array $options,
    ): static {
        $this->journal->withOptionsCalls[] = $options;

        return clone $this;
    }
}
