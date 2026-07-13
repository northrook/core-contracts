<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests;

use Northrook\Contracts\ContextSnapshot;
use Northrook\Contracts\ErrorHandler\ErrorBuffer;
use Northrook\Contracts\ErrorHandler\RuntimeError;
use Northrook\Contracts\Exceptions\CurlException;
use Northrook\Contracts\Exceptions\ErrorException;
use Northrook\Contracts\Exceptions\FileNotFoundException;
use Northrook\Contracts\Exceptions\FilesystemException;
use Northrook\Contracts\Exceptions\RecursionException;
use Northrook\Contracts\Exceptions\RegexpException;
use Northrook\Contracts\Exceptions\RuntimeException;
use Northrook\Contracts\Exceptions\ServiceNotFoundException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException as PhpRuntimeException;

use function Northrook\Contracts\is_valid_path_length;

use const Northrook\Logger\LOG_LEVEL, PREG_INTERNAL_ERROR;

final class ExceptionsTest extends TestCase
{
    protected function setUp(): void
    {
        error_clear_last();
        ErrorBuffer::shared()->reset();
    }

    protected function tearDown(): void
    {
        error_clear_last();
        ErrorBuffer::shared()->reset();
    }

    #[DataProvider('provideSubclassExtendsContractsRuntimeException')]
    public function testSubclassExtendsContractsRuntimeException(
        string $class,
    ): void {
        self::assertTrue(\is_subclass_of($class, RuntimeException::class));
        self::assertInstanceOf(RuntimeException::class, new $class(...self::subclassConstructorArgs($class)));
    }

    /**
     * @return iterable<string, array{class-string}>
     */
    public static function provideSubclassExtendsContractsRuntimeException(): iterable
    {
        yield CurlException::class => [CurlException::class];
        yield RegexpException::class => [RegexpException::class];
        yield ErrorException::class => [ErrorException::class];
        yield FilesystemException::class => [FilesystemException::class];
        yield FileNotFoundException::class => [FileNotFoundException::class];
        yield ServiceNotFoundException::class => [ServiceNotFoundException::class];
        yield RecursionException::class => [RecursionException::class];
    }

    public function testCurlExceptionStoresUrlInContextAndUsesErrorSeverity(): void
    {
        $exception = new CurlException('https://example.test');

        self::assertSame('https://example.test', $exception->url);
        self::assertSame('https://example.test', $exception->context['url']);
        self::assertSame(LOG_LEVEL['error'], $exception->getCode());
        self::assertSame("HTTP request to 'https://example.test' failed", $exception->getMessage());
    }

    public function testCurlExceptionPreservesExplicitPrevious(): void
    {
        $previous  = new PhpRuntimeException('upstream');
        $exception = new CurlException('https://example.test', previous: $previous);

        self::assertSame($previous, $exception->getPrevious());
    }

    public function testRegexpExceptionMapsPregErrorCodeToMessageAndCode(): void
    {
        $exception = new RegexpException(PREG_INTERNAL_ERROR);

        self::assertSame(RegexpException::MESSAGES[PREG_INTERNAL_ERROR], $exception->getMessage());
        self::assertSame(PREG_INTERNAL_ERROR, $exception->getCode());
    }

    public function testRegexpExceptionCheckThrowsOnPregFailure(): void
    {
        @\preg_match( /** @lang mock-to-silence-unclosed-error */
            '/(?P<unclosed/',
            'subject',
        );

        $this->expectException(RegexpException::class);

        RegexpException::check();
    }

    public function testFilesystemExceptionExposesPathFromContext(): void
    {
        $exception = new FilesystemException(
            message: 'Denied',
            path: '/tmp/example.txt',
        );

        self::assertSame('/tmp/example.txt', $exception->getPath());
        self::assertSame('/tmp/example.txt', $exception->context['path']);
        self::assertSame(LOG_LEVEL['error'], $exception->getCode());
    }

    public function testFileNotFoundExceptionBuildsDefaultMessageFromPath(): void
    {
        $exception = new FileNotFoundException(path: '/missing.txt');

        self::assertSame("File '/missing.txt' could not be found.", $exception->getMessage());
        self::assertSame('/missing.txt', $exception->getPath());
    }

    public function testFileNotFoundExceptionUsesGenericMessageWithoutPath(): void
    {
        $exception = new FileNotFoundException();

        self::assertSame('File could not be found.', $exception->getMessage());
        self::assertNull($exception->getPath());
    }

    public function testServiceNotFoundExceptionBuildsServiceIdAndAlternativesMessage(): void
    {
        $exception = new ServiceNotFoundException(
            id: 'App\\Service',
            reference: 'default',
            alternatives: ['App\\Other', 'App\\Backup'],
        );

        self::assertSame('App\\Service.default', $exception->serviceId);
        self::assertStringContainsString('Did you mean one of these:', $exception->getMessage());
        self::assertSame('App\\Service', $exception->context['id']);
        self::assertSame('default', $exception->context['reference']);
        self::assertInstanceOf(ContextSnapshot::class, $exception->context['alternatives']);
        self::assertSame(['App\\Other', 'App\\Backup'], $exception->context['alternatives']->value);
    }

    public function testRecursionExceptionUsesDefaultMessageAndCriticalSeverity(): void
    {
        $exception = new RecursionException();

        self::assertSame('Recursion limit exceeded.', $exception->getMessage());
        self::assertSame(LOG_LEVEL['critical'], $exception->getCode());
    }

    public function testRuntimeExceptionSnapshotsContextValues(): void
    {
        $exception = new RuntimeException(
            message: 'Invalid payload.',
            context: ['payload' => ['id' => 1]],
            previous: false,
        );

        self::assertInstanceOf(ContextSnapshot::class, $exception->context['payload']);
        self::assertSame(['id' => 1], $exception->context['payload']->value);
    }

    public function testRuntimeExceptionSnapshotsBufferIntoContext(): void
    {
        ErrorBuffer::shared()->recordFrom(E_USER_NOTICE, 'first notice', '/tmp/a.php', 1);
        ErrorBuffer::shared()->recordFrom(E_USER_WARNING, 'second warning', '/tmp/b.php', 2);

        $exception = new RuntimeException(message: 'wrapper');

        self::assertNull($exception->getPrevious());
        self::assertArrayHasKey('phpErrors', $exception->context);
        self::assertInstanceOf(ContextSnapshot::class, $exception->context['phpErrors']);

        $errors = $exception->context['phpErrors']->value;
        self::assertCount(2, $errors);
        self::assertSame('first notice', $errors[0]->message);
        self::assertSame('second warning', $errors[1]->message);
    }

    public function testRuntimeExceptionDoesNotSnapshotEmptyBuffer(): void
    {
        $exception = new RuntimeException(message: 'wrapper');

        self::assertArrayNotHasKey('phpErrors', $exception->context);
    }

    public function testRuntimeExceptionDoesNotAttachStalePhpErrorWhenPreviousIsFalse(): void
    {
        @\trigger_error('stale notice', E_USER_NOTICE);

        $exception = new RuntimeException(
            message: 'wrapper',
            previous: false,
        );

        self::assertNull($exception->getPrevious());
    }

    public function testErrorExceptionWrapsRuntimeErrorWithoutDuplicatingPreviousChain(): void
    {
        @\trigger_error('notice message', E_USER_NOTICE);

        $runtimeError = RuntimeError::fromLast();
        self::assertNotNull($runtimeError);

        $exception = new ErrorException();

        self::assertSame('notice message', $exception->getMessage());
        self::assertSame($runtimeError->type, $exception->getCode());
        self::assertSame($runtimeError->file, $exception->getFile());
        self::assertSame($runtimeError->line, $exception->getLine());
        self::assertSame($runtimeError->toArray(), $exception->error);
        self::assertSame($runtimeError->toArray(), $exception->getError()->toArray());
        self::assertNull($exception->getPrevious());
        self::assertArrayHasKey('phpError', $exception->context);
    }

    public function testErrorExceptionAcceptsExplicitRuntimeError(): void
    {
        $runtimeError = RuntimeError::from([
            'type'    => E_USER_WARNING,
            'message' => 'explicit runtime',
            'file'    => '/tmp/runtime.php',
            'line'    => 15,
        ]);

        $exception = new ErrorException(error: $runtimeError);

        self::assertSame('explicit runtime', $exception->getMessage());
        self::assertSame('/tmp/runtime.php', $exception->getFile());
        self::assertSame(15, $exception->getLine());
    }

    public function testErrorExceptionWithoutRuntimeErrorThrowsRuntimeException(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No PHP error to wrap.');

        new ErrorException();
    }

    public function testErrorExceptionCheckThrowsWhenRuntimeErrorExists(): void
    {
        @\trigger_error('check me', E_USER_NOTICE);

        $this->expectException(ErrorException::class);
        $this->expectExceptionMessage('check me');

        ErrorException::check();
    }

    public function testErrorExceptionGetLastReturnsNullWhenNoError(): void
    {
        self::assertNull(ErrorException::getLast());
    }

    public function testIsValidPathLengthThrowsFilesystemExceptionWithPathContext(): void
    {
        $path = \str_repeat('a', MAX_PATH_LENGTH + 1);

        try {
            is_valid_path_length($path);
            self::fail('Expected FilesystemException was not thrown.');
        } catch (FilesystemException $exception) {
            self::assertSame($path, $exception->getPath());
            self::assertStringContainsString((string) MAX_PATH_LENGTH, $exception->getMessage());
            self::assertSame(LOG_LEVEL['error'], $exception->getCode());
        }
    }

    /**
     * @param class-string $class
     *
     * @return list<mixed>
     */
    private static function subclassConstructorArgs(
        string $class,
    ): array {
        return match ($class) {
            CurlException::class            => ['https://example.test'],
            RegexpException::class          => ['pattern failed'],
            ErrorException::class => [
                null,
                RuntimeError::from([
                    'type'    => E_USER_NOTICE,
                    'message' => 'fixture',
                    'file'    => '/tmp/fixture.php',
                    'line'    => 1,
                ]),
            ],
            FilesystemException::class      => ['filesystem failure'],
            FileNotFoundException::class    => [],
            ServiceNotFoundException::class => ['App\\Service'],
            RecursionException::class       => [],
            default                         => [],
        };
    }
}
