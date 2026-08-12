<?php

declare(strict_types=1);

namespace Northrook\Contracts;

abstract readonly class DataObject implements Serializable, \Stringable
{
    use Serializer;

    protected function __construct()
    {
        // TODO: Validation when settled on the contract
    }

    final public function __toString(): string
    {
        return $this->jsonString();
    }

    final public function jsonString(
        bool          $pretty = false,
        bool          $escapeUnicode = false,
        bool          $escapeSlashes = false,
        bool          $preserveZeroFraction = true,
        null|callable $formatter = null,
    ): string {
        return JSON::encode(
            $this,
            pretty: $pretty,
            escapeUnicode: $escapeUnicode,
            escapeSlashes: $escapeSlashes,
            preserveZeroFraction: $preserveZeroFraction,
            formatter: $formatter,
        );
    }
}
