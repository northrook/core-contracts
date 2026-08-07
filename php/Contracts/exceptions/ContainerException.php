<?php

declare(strict_types=1);

namespace Northrook\Contracts;

use Psr\Container\ContainerExceptionInterface;

class ContainerException extends RuntimeException implements ContainerExceptionInterface {}
