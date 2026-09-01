<?php

declare(strict_types=1);

namespace Northrook\Http\Response;

use Symfony\Component\HttpFoundation\ResponseHeaderBag;

/**
 * HTTP response headers abstraction.
 *
 * @implements \IteratorAggregate<string, list<string|null>>
 */
final readonly class Headers implements \IteratorAggregate, \Countable, \Stringable
{
    private function __construct(
        public ResponseHeaderBag $headerBag,
    ) {}

    public function __toString(): string
    {
        return $this->headerBag->__toString();
    }

    public static function bind(
        ResponseHeaderBag $headers,
    ): self {
        return new self($headers);
    }

    /**
     * Returns true if the HTTP header is defined.
     */
    public function has(string $key): bool
    {
        return $this->headerBag->has($key);
    }

    /**
     * Returns true if the given HTTP header contains the given value.
     *
     * @param string  $key
     * @param string            $value
     *
     * @return bool
     */
    public function contains(
        string $key,
        string $value,
    ): bool {
        return $this->headerBag->contains($key, $value);
    }

    /**
     * Returns the headers.
     *
     * @param string|null $key The name of the headers to return or null to get them all
     *
     * @return ($key is null ? array<string, list<string|null>> : list<string|null>)
     */
    public function all(
        null|string $key = null,
    ): array {
        return $this->headerBag->all($key);
    }

    /**
     * Returns the first header by name or the default one.
     *
     * @param string      $key
     * @param string|null           $default
     *
     * @return string|null
     */
    public function get(
        string      $key,
        null|string $default = null,
    ): null|string {
        return $this->headerBag->get($key, $default);
    }

    /**
     * Adds new headers the current HTTP headers set.
     *
     * @param array<string, list<string|null>> $headers
     *
     * @return $this
     */
    public function add(
        array $headers,
    ): self {
        $this->headerBag->add($headers);
        return $this;
    }

    /**
     * Sets a header by name.
     *
     * @param string      $key     The header name
     * @param string|string[]|null  $values  The value or an array of values
     * @param bool                  $replace Whether to replace the actual value or not (true by default)
     */
    public function set(
        string            $key,
        string|array|null $values,
        bool              $replace = true,
    ): self {
        $this->headerBag->set($key, $values, $replace);
        return $this;
    }

    /**
     * Replace the current headers with a new set.
     *
     * @param array<string, list<string|null>> $headers
     *
     * @return $this
     */
    public function replace(
        array $headers,
    ): self {
        $this->headerBag->replace($headers);
        return $this;
    }

    /**
     * Removes a header.
     *
     * @param string  $key
     *
     * @return \Northrook\Http\Response\Headers
     */
    public function remove(string $key): self
    {
        $this->headerBag->remove($key);
        return $this;
    }

    /**
     * @return string[]
     */
    public function keys(): array
    {
        return \array_keys($this->headerBag->all());
    }

    /**
     * Returns an iterator for headers.
     *
     * @return \ArrayIterator<string, list<string|null>>
     */
    public function getIterator(): \ArrayIterator
    {
        return $this->headerBag->getIterator();
    }

    public function count(): int
    {
        return $this->headerBag->count();
    }
}
