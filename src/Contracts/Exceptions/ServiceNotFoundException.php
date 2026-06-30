<?php

namespace Northrook\Contracts\Exceptions;

use Northrook\Contracts\Exceptions\RuntimeException;
use Psr\Container\NotFoundExceptionInterface;

class ServiceNotFoundException extends RuntimeException implements NotFoundExceptionInterface
{
    public string $serviceId {
        get => $this->reference !== null ? "{$this->id}.{$this->reference}" : "{$this->id}";
    }

    /**
     * @param class-string $id
     * @param null|string $reference
     * @param list<string> $alternatives
     * @param null|string $message
     * @param int $code
     * @param null|\Throwable $previous
     */
    public function __construct(
        public readonly string $id,
        public readonly null|string $reference = null,
        public readonly array $alternatives = [],
        null|string $message = null,
        int $code = 0,
        null|\Throwable $previous = null,
    ) {
        $message ??= "Service {$this->serviceId} could not be found.";

        if (! empty($alternatives)) {
            if (count($alternatives) === 1) {
                $message .= " Did you mean `{$alternatives[0]}`?";
            } else {
                $message .= ' Did you mean one of these: `' . \implode('`, `', $alternatives) . '`?';
            }
        }

        parent::__construct(
            $message,
            $code,
            $previous,
        );
    }
}
