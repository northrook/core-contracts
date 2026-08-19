<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests\Support;

use Northrook\Context;
use Northrook\Context\AppEnv;
use Northrook\Context\ContextManager;
use Northrook\Kernel\KernelContext;
use Northrook\Singleton;

trait RegistersTestContext
{
    private ContextManager $contextManager;

    protected function setUpTestContext(): void
    {
        $this->resetSingletonRegistry();
        $this->contextManager = new ContextManager;
        Context::register(
            appEnv        : AppEnv::Testing,
            contextManager: $this->contextManager,
        );
        $this->contextManager->update(KernelContext::Runtime);
    }

    protected function tearDownTestContext(): void
    {
        $this->resetSingletonRegistry();
    }

    protected function becomeOutbound(): void
    {
        $this->contextManager->update(KernelContext::Request);
    }

    private function resetSingletonRegistry(): void
    {
        $property = new \ReflectionProperty(Singleton::class, '_instance');
        $property->setValue(null, []);
    }
}
