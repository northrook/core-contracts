<?php

declare(strict_types=1);

namespace Northrook\Contracts;

use Northrook\Serializer;

/**
 * Lightweight eval-able PHP source for a {@see Serializable} instance.
 *
 * Implementers `use Serializer` for the channels. {@see _export()} is implementer-owned —
 * call {@see Serializer::guardExport()} first. The trait does not provide `_export`.
 *
 * Use it when you need a PHP literal without going through {@see Exporter}. Credentials
 * are refused under {@see \Northrook\Kernel\KernelContext::Request} unless an {@see Exporter} override is active.
 */
interface Exportable extends Serializable
{
    /**
     * Eval-able PHP source that reconstructs this value.
     *
     * @return string
     */
    public function _export(): string;
}
