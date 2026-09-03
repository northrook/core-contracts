<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests;

use Northrook\Http\Response\StatusCode;
use Northrook\InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(StatusCode::class)]
final class StatusCodeTest extends TestCase
{
    public function testResolveAcceptsEnumCase(): void
    {
        self::assertSame(StatusCode::NotFound, StatusCode::resolve(StatusCode::NotFound));
    }

    public function testResolveAcceptsNumericString(): void
    {
        self::assertSame(StatusCode::Ok, StatusCode::resolve('200'));
    }

    public function testResolveRejectsOutOfRange(): void
    {
        $this->expectException(InvalidArgumentException::class);

        StatusCode::resolve(99);
    }

    public function testResolveRejectsUnknownCodeInRange(): void
    {
        $this->expectException(InvalidArgumentException::class);

        StatusCode::resolve(599);
    }
}
