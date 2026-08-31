<?php

declare(strict_types=1);

namespace Northrook\Http\Request;

use Northrook\Http\RequestInterface;
use Northrook\InvalidArgumentException;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

enum Scheme
{
    case HTTP;
    case HTTPS;

    public static function from(
        mixed $value,
    ): self {
        $scheme = match (true) {
            \is_string($value) => match (\strtolower($value)) {
                'http'  => self::HTTP,
                'https' => self::HTTPS,
                default => null,
            },
            $value instanceof SymfonyRequest => $value->isSecure()
                ? self::HTTPS
                : self::HTTP,
            $value instanceof RequestInterface => $value->scheme,
            $value instanceof self => $value,
            default => null,
        };

        if ($scheme) {
            return $scheme;
        }
        throw new InvalidArgumentException(
            message: 'Unable to determine scheme from value ' . \debug_value_type($value),
        );
    }

    public function isSecure(): bool
    {
        return $this === self::HTTPS;
    }
}
