<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests\Support;

use Northrook\Autowire\Logger;
use Psr\Log\LoggerInterface;

final class LoggerFixture
{
    use Logger;

    public function loggerIsExplicitlySet(): bool
    {
        $property = new \ReflectionProperty($this, 'logger');

        return $property->isInitialized($this);
    }

    public function loggerInstance(): LoggerInterface
    {
        return $this->logger;
    }

    /**
     * @param array<string, mixed> $context
     */
    public function captureLogException(
        \Throwable  $exception,
        null|string $message = null,
        array       $context = [],
        bool        $continue = true,
    ): void {
        $this->logException($exception, $message, $context, $continue);
    }
}
