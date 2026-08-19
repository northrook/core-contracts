<?php

declare(strict_types=1);

namespace Northrook\Container;

final class ServiceNotFoundException extends ContainerException implements \Psr\Container\NotFoundExceptionInterface
{
    public function __construct(
        string          $id,
        null|string     $reference,
        null|string     $message = null,
        null|array      $context = null,
        null|\Throwable $previous = null,
        int             $code = 0,
    ) {
        $context = ['id' => $id, 'reference' => $reference, ...( $context ?? [] )];
        $message ??= $reference === null
            ? "Service `{$id}` not found."
            : "Service `{$id}` for reference `{$reference}` not found.";
        parent::__construct($message, $context, $previous, $code);
    }
}
