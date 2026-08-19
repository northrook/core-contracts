<?php

declare(strict_types=1);

namespace Northrook;

/**
 * @phpstan-require-extends \Exception
 */
interface ExceptionInterface extends \Throwable
{
    /** @return array<array-key, mixed>  */
    public function getContext(): array;

    /**
     * @param \Throwable                 $throwable
     * @param null|array<string, mixed>  $context
     *
     * @return \Northrook\ExceptionInterface
     */
    public static function from(
        \Throwable $throwable,
        null|array $context = null,
    ): ExceptionInterface;
}
