<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests\Support;

use Northrook\Context;
use Northrook\Context\AppEnv;
use Northrook\Kernel\KernelContext;

trait RegistersTestContext
{
    private Context $context;

    protected function setUpTestContext(): void
    {
        ResetsContext::reset();
        $this->context = Context::register(appEnv: AppEnv::Testing);
        $this->context->update(KernelContext::Runtime);
    }

    protected function tearDownTestContext(): void
    {
        ResetsContext::reset();
    }

    protected function becomeOutbound(): void
    {
        $this->context->update(KernelContext::Request);
    }
}
