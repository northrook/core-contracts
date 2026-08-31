<?php

declare(strict_types=1);

namespace Northrook\Http\Request;

use Symfony\Component\HttpFoundation\Exception\SuspiciousOperationException;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

enum Method
{
    case HEAD;
    case GET;
    case POST;
    case PUT;
    case PATCH;
    case DELETE;
    case PURGE;
    case OPTIONS;
    case TRACE;
    case CONNECT;
    case QUERY;

    public const array SUPPORTED_METHODS = [
        'HEAD'    => Method::HEAD,
        'GET'     => Method::GET,
        'POST'    => Method::POST,
        'PUT'     => Method::PUT,
        'PATCH'   => Method::PATCH,
        'DELETE'  => Method::DELETE,
        'PURGE'   => Method::PURGE,
        'OPTIONS' => Method::OPTIONS,
        'TRACE'   => Method::TRACE,
        'CONNECT' => Method::CONNECT,
        'QUERY'   => Method::QUERY,
    ];

    /**
     * List of methods that are considered safe.
     *
     * @see \Symfony\Component\HttpFoundation\Request::isSafeMethod()
     */
    public const array SAFE_METHODS = [
        'GET'     => Method::GET,
        'HEAD'    => Method::HEAD,
        'OPTIONS' => Method::OPTIONS,
        'TRACE'   => Method::TRACE,
        'QUERY'   => Method::QUERY,
    ];

    /**
     * List of methods that are considered idempotent.
     *
     * @see \Symfony\Component\HttpFoundation\Request::isIdempotent()
     */
    public const array IDEMPOTENT_METHODS = [
        'HEAD'    => Method::HEAD,
        'GET'     => Method::GET,
        'PUT'     => Method::PUT,
        'DELETE'  => Method::DELETE,
        'TRACE'   => Method::TRACE,
        'OPTIONS' => Method::OPTIONS,
        'PURGE'   => Method::PURGE,
        'QUERY'   => Method::QUERY,
    ];

    /**
     * List of methods that are considered "volatile", e.g. non-idempotent.
     */
    public const array VOLATILE_METHODS = [
        Method::POST,
        Method::PATCH,
        Method::CONNECT,
    ];

    /**
     * List of methods that are considered cacheable.
     *
     * @see \Symfony\Component\HttpFoundation\Request::isCacheable()
     */
    public const array CACHEABLE_METHODS = [
        'GET'   => Method::GET,
        'HEAD'  => Method::HEAD,
        'QUERY' => Method::QUERY,
    ];

    public static function from(
        mixed $value,
    ): self {
        if (\is_string($value)) {
            $string = \strtoupper($value);
            if (\array_key_exists($string, self::SUPPORTED_METHODS)) {
                return self::SUPPORTED_METHODS[$string];
            }
        }

        if ($value instanceof SymfonyRequest) {
            return self::from($value->getMethod());
        }

        if ($value instanceof self) {
            return $value;
        }

        throw new SuspiciousOperationException(
            'Unable to determine HTTP method from value ' . \debug_value_type($value) . '.',
        );
    }
    /**
     * Checks whether or not the method is safe.
     *
     * @see https://tools.ietf.org/html/rfc7231#section-4.2.1
     */
    public function isSafe(): bool
    {
        return \array_key_exists($this->name, self::SAFE_METHODS);
    }

    /**
     * Checks whether the method is cacheable or not.
     *
     * @see https://tools.ietf.org/html/rfc7231#section-4.2.3
     */
    public function isCacheable(): bool
    {
        return \array_key_exists($this->name, self::CACHEABLE_METHODS);
    }

    /**
     * Checks whether or not the method is idempotent.
     */
    public function isIdempotent(): bool
    {
        return \array_key_exists($this->name, self::IDEMPOTENT_METHODS);
    }
}
