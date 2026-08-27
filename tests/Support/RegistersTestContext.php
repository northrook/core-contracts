<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests\Support;

use Northrook\Context;
use Northrook\Context\AppEnv;
use Northrook\Context\ContextManager;
use Northrook\Kernel\KernelContext;

trait RegistersTestContext
{
    private ContextManager $contextManager;

    protected function setUpTestContext(): void
    {
        ResetsContext::reset();
        $this->contextManager = new ContextManager;
        Context::register(
            appEnv        : AppEnv::Testing,
            contextManager: $this->contextManager,
        );
        $this->contextManager->update(KernelContext::Runtime);
    }

    protected function tearDownTestContext(): void
    {
        ResetsContext::reset();
    }

    protected function becomeOutbound(): void
    {
        $this->contextManager->update(KernelContext::Request);
    }
}
