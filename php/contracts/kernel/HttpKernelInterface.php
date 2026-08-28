<?php

declare(strict_types=1);

namespace Northrook;

use Northrook\Http\RequestInterface;
use Northrook\Http\ResponseInterface;

interface HttpKernelInterface extends KernelInterface
{
    public function handle(
        RequestInterface $request,
    ): ResponseInterface;

    public function terminate(
        RequestInterface  $request,
        ResponseInterface $response,
    ): void;
}
