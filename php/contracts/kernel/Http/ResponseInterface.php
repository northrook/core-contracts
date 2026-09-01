<?php

declare(strict_types=1);

namespace Northrook\Http;

use Northrook\Http\Response\StatusCode;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

/**
 * HTTP response abstraction with cache and content helpers.
 */
interface ResponseInterface
{
    /**
     * Gets the HTTP headers of the response.
     */
    public ResponseHeaderBag $headers { get; }

    /**
     * Gets the HTTP status code of the response.
     */
    public StatusCode $statusCode { get; }

    /**
     * Gets the underlying Symfony response.
     *
     * @var \Symfony\Component\HttpFoundation\Response
     */
    public Response $response { get; }
}
