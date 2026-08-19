<?php

declare(strict_types=1);

namespace Northrook\Context;

use Northrook\Contracts\ContextEnum;
use Northrook\Timestamp;

/**
 * The context of a single process.
 *
 * Created and handled by the {@see ContextManager}.
 */
final readonly class ContextEntry
{
    public string $key;
    public ContextEnum $context;
    public Timestamp $timestamp;

    /**
     * Created by {@see ContextManager}.
     *
     * @internal
     *
     * @param \Northrook\Contracts\ContextEnum  $context
     */
    public function __construct(
        ContextEnum $context,
    ) {
        $this->key       = $context::class;
        $this->context   = $context;
        $this->timestamp = Timestamp::now();
    }
}
