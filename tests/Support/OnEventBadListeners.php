<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests\Support;

final class OnEventBadListeners
{
    public function noParams(): void {}

    protected function protectedListener(
        OnEventSampleEvent $event,
    ): void {}

    private function privateListener(
        OnEventSampleEvent $event,
    ): void {}

    public static function staticListener(
        OnEventSampleEvent $event,
    ): void {}

    public function untyped(
        $event,
    ): void {}

    public function stringParam(
        string $event,
    ): void {}

    public function wrongClass(
        \stdClass $event,
    ): void {}
}
