<?php

declare(strict_types=1);

namespace Northrook\Container;

use Northrook\RuntimeException;

class ContainerException extends RuntimeException implements \Psr\Container\ContainerExceptionInterface {}
