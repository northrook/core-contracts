<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests;

use Northrook\Container\ContainerException;
use Northrook\Container\ServiceNotFoundException;
use Northrook\CurlException;
use Northrook\DependencyException;
use Northrook\ExceptionInterface;
use Northrook\Filesystem\FileNotFoundException;
use Northrook\Filesystem\FilesystemException;
use Northrook\InvalidArgumentException;
use Northrook\RegexpException;
use Northrook\RuntimeException;
use Northrook\UndefinedEntryException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

final class ExceptionsTest extends TestCase
{
    #[DataProvider('provideExceptionSubclasses')]
    public function testSubclassExtendsRuntimeException(
        \Closure $factory,
    ): void {
        $exception = $factory();

        self::assertInstanceOf(RuntimeException::class, $exception);
        self::assertInstanceOf(\RuntimeException::class, $exception);
    }

    public static function provideExceptionSubclasses(): \Generator
    {
        yield 'ContainerException' => [static fn() => new ContainerException('container failure')];
        yield 'ServiceNotFoundException' => [static fn() => new ServiceNotFoundException('service.id', null, 'not found')];
        yield 'CurlException' => [static fn() => new CurlException('https://example.com')];
        yield 'DependencyException' => [static fn() => new DependencyException('missing dependency')];
        yield 'FileNotFoundException' => [static fn() => new FileNotFoundException(path: '/missing')];
        yield 'FilesystemException' => [static fn() => new FilesystemException('fs failure')];
        yield 'UndefinedEntryException' => [static fn() => new UndefinedEntryException('missing')];
        yield 'RegexpException' => [static fn() => new RegexpException('regexp failure')];
    }

    public function testInvalidArgumentExceptionExtendsLogicException(): void
    {
        $exception = new InvalidArgumentException('invalid');

        self::assertInstanceOf(\LogicException::class, $exception);
        self::assertInstanceOf(ExceptionInterface::class, $exception);
    }

    public function testContainerExceptionImplementsPsrInterface(): void
    {
        self::assertInstanceOf(ContainerExceptionInterface::class, new ContainerException('failure'));
    }

    public function testServiceNotFoundExceptionIsContainerException(): void
    {
        $exception = new ServiceNotFoundException('service.id', null, 'not found');

        self::assertInstanceOf(ContainerException::class, $exception);
        self::assertInstanceOf(NotFoundExceptionInterface::class, $exception);
    }

    public function testServiceNotFoundExceptionBuildsDefaultMessage(): void
    {
        self::assertSame(
            'Service `service.id` not found.',
            new ServiceNotFoundException('service.id', null)->getMessage(),
        );
        self::assertSame(
            'Service `service.id` for reference `primary` not found.',
            new ServiceNotFoundException('service.id', 'primary')->getMessage(),
        );
    }

    public function testContextIsAccessibleViaGetter(): void
    {
        $exception = new RuntimeException('boom', ['key' => 'value']);

        self::assertSame(['key' => 'value', 'errors' => []], $exception->getContext());
    }

    public function testFileNotFoundExceptionCarriesPath(): void
    {
        $exception = new FileNotFoundException(path: '/missing/file.php');

        self::assertSame('/missing/file.php', $exception->getPath());
        self::assertSame(
            'File `/missing/file.php` could not be found.',
            $exception->getMessage(),
        );
    }

    public function testUndefinedEntryExceptionCoercesNumericStringKey(): void
    {
        $exception = new UndefinedEntryException('42');

        self::assertSame(42, $exception->getKey());
        self::assertSame(42, $exception->getContext()['key']);
    }

    public function testUndefinedEntryExceptionKeepsNonNumericStringKey(): void
    {
        $exception = new UndefinedEntryException('404abc');

        self::assertSame('404abc', $exception->getKey());
    }

    #[DataProvider('provideFileNotFoundEmptyPaths')]
    public function testFileNotFoundExceptionGenericMessageWithoutPath(
        null|string $path,
    ): void {
        self::assertSame(
            'File could not be found.',
            new FileNotFoundException(path: $path)->getMessage(),
        );
    }

    public static function provideFileNotFoundEmptyPaths(): \Generator
    {
        yield 'null path' => [null];
        yield 'empty path' => [''];
    }

    public function testFromOnRuntimeSubclassReturnsRuntimeException(): void
    {
        $foreign = new \RuntimeException('boom', 7);
        $wrapped = CurlException::from($foreign);

        self::assertInstanceOf(RuntimeException::class, $wrapped);
        self::assertNotInstanceOf(CurlException::class, $wrapped);
        self::assertSame('boom', $wrapped->getMessage());
        self::assertSame(7, $wrapped->getCode());
        self::assertSame($foreign, $wrapped->getPrevious());
    }

    public function testInvalidArgumentFromReturnsInvalidArgumentException(): void
    {
        $foreign = new \InvalidArgumentException('bad');
        $wrapped = InvalidArgumentException::from($foreign);

        self::assertInstanceOf(InvalidArgumentException::class, $wrapped);
        self::assertNotInstanceOf(\Northrook\LogicException::class, $wrapped);
        self::assertSame('bad', $wrapped->getMessage());
        self::assertSame($foreign, $wrapped->getPrevious());
    }
}
