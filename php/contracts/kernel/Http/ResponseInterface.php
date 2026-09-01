<?php

declare(strict_types=1);

namespace Northrook\Http;

use Northrook\Http\Response\StatusCode;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

/**
 * HTTP response abstraction with cache and content helpers.
 */
interface ResponseInterface
{
    public ResponseHeaderBag $headers { get; }

    /**
     * Gets the HTTP status code of the response.
     */
    public StatusCode $statusCode { get; }
}
