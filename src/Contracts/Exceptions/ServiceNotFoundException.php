<?php

declare(strict_types=1);

namespace Northrook\Contracts\Exceptions;

use Psr\Container\NotFoundExceptionInterface;
use Throwable;

use const Northrook\Logger\LOG_LEVEL;

class ServiceNotFoundException extends RuntimeException implements NotFoundExceptionInterface
{
    public string $serviceId {
        get => $this->reference !== null ? "{$this->id}.{$this->reference}" : "{$this->id}";
    }

    /**
     * @param string       $id service type or binding id
     * @param list<string> $alternatives
     */
    public function __construct(
        public readonly string $id,
        public readonly null|string $reference = null,
        public readonly array $alternatives = [],
        null|string $message = null,
        null|array $context = null,
        null|false|Throwable $previous = null,
        int $code = LOG_LEVEL['error'],
    ) {
        $message ??= "Service {$this->serviceId} could not be found.";

        if ($alternatives !== []) {
            if (\count($alternatives) === 1) {
                $message .= " Did you mean `{$alternatives[0]}`?";
            } else {
                $message .= ' Did you mean one of these: `' . \implode('`, `', $alternatives) . '`?';
            }
        }

        parent::__construct(
            message: $message,
            context: [
                'id'           => $id,
                'reference'    => $reference,
                'alternatives' => $alternatives,
                ...( $context ?? [] ),
            ],
            previous: $previous,
            code: $code,
        );
    }
}
