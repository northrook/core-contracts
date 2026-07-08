<?php

declare(strict_types=1);

namespace Northrook\Contracts\ErrorHandler;

use Northrook\AppEnv;
use Northrook\AppEnvironment;
use Northrook\Contracts\DataObject;

final readonly class ErrorReport extends DataObject
{
    public AppEnvironment $environment;

    /**
     * @param StackFrame[]          $stackFrames
     * @param ErrorReport[]         $previous
     * @param array<string, mixed>  $context
     * @param array<string, mixed>  $dumps
     * @param list<RuntimeError>    $phpErrors
     */
    public function __construct(
        public string $reference,
        public float $timestamp,
        public string $severity,
        public ErrorSnapshot $error,
        public array $stackFrames,
        public array $previous = [],
        public array $context = [],
        public array $dumps = [],
        public null|RuntimeError $phpError = null,
        public array $phpErrors = [],
    ) {
        $this->environment = AppEnv::getEnvironment();

        parent::__construct();
    }
}
