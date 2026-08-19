<?php

declare(strict_types=1);

namespace Northrook\Contracts;

/**
 * Consumers must take secrets and sensitive values into account when parsing.
 *
 * Extends {@see \JsonSerializable} so `json_encode` honours the redaction policy.
 */
interface Serializable extends \JsonSerializable
{
    /**
     * @return array<string, mixed>
     */
    public function __serialize(): array;

    /**
     * @param array<string, mixed>  $data
     */
    public function __unserialize(
        array $data,
    ): void;

    /**
     * @return array<string, mixed>
     */
    public function __debugInfo(): array;
}
