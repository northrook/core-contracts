<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests;

use JsonSerializable;
use Northrook\Contracts\ContextSnapshot;
use Northrook\Contracts\ErrorHandler\RuntimeError;
use Northrook\Contracts\Exceptions\RuntimeException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Stringable;

use const Northrook\Logger\LOG_LEVEL;

final class RuntimeErrorTest extends TestCase
{
    protected function setUp(): void
    {
        error_clear_last();
    }

    protected function tearDown(): void
    {
        error_clear_last();
    }

    public function testFromMapsAllFields(): void
    {
        $array = self::sampleArray();

        $error = RuntimeError::from($array);

        self::assertSame($array['type'], $error->type);
        self::assertSame($array['message'], $error->message);
        self::assertSame($array['file'], $error->file);
        self::assertSame($array['line'], $error->line);
    }

    public function testToArrayReturnsErrorArray(): void
    {
        $array = self::sampleArray();

        self::assertSame($array, RuntimeError::from($array)->toArray());
    }

    public function testFromIgnoresExtraKeys(): void
    {
        $error = RuntimeError::from([
            ...self::sampleArray(),
            'trace' => ['ignored'],
            'severity' => 'warning',
        ]);

        self::assertSame(self::sampleArray(), $error->__serialize());
    }

    #[DataProvider('provideValidEdgeCases')]
    public function testFromAcceptsValidEdgeCases(
        array $array,
    ): void {
        $error = RuntimeError::from($array);

        self::assertSame($array, $error->__serialize());
    }

    /**
     * @return iterable<string, array{array<string, int|string>}>
     */
    public static function provideValidEdgeCases(): iterable
    {
        yield 'empty message' => [[
            'type'    => E_USER_NOTICE,
            'message' => '',
            'file'    => '/tmp/file.php',
            'line'    => 1,
        ]];

        yield 'empty file' => [[
            'type'    => E_WARNING,
            'message' => 'warning',
            'file'    => '',
            'line'    => 10,
        ]];

        yield 'line zero' => [[
            'type'    => E_ERROR,
            'message' => 'fatal',
            'file'    => 'eval()\'d code',
            'line'    => 0,
        ]];

        yield 'whitespace message' => [[
            'type'    => E_USER_WARNING,
            'message' => "  padded message  \n",
            'file'    => '/app/bootstrap.php',
            'line'    => 99,
        ]];
    }

    #[DataProvider('provideInvalidArrays')]
    public function testFromRejectsInvalidArray(
        array $array,
    ): void {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid error array format.');

        RuntimeError::from($array);
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function provideInvalidArrays(): iterable
    {
        yield 'empty array' => [[]];

        yield 'missing type' => [[
            'message' => 'm',
            'file'    => 'f.php',
            'line'    => 1,
        ]];

        yield 'missing message' => [[
            'type' => E_NOTICE,
            'file' => 'f.php',
            'line' => 1,
        ]];

        yield 'missing file' => [[
            'type'    => E_NOTICE,
            'message' => 'm',
            'line'    => 1,
        ]];

        yield 'missing line' => [[
            'type'    => E_NOTICE,
            'message' => 'm',
            'file'    => 'f.php',
        ]];

        yield 'null type' => [[
            'type'    => null,
            'message' => 'm',
            'file'    => 'f.php',
            'line'    => 1,
        ]];

        yield 'string type' => [[
            'type'    => (string) E_NOTICE,
            'message' => 'm',
            'file'    => 'f.php',
            'line'    => 1,
        ]];
    }

    public function testFromValidationFailureUsesStructuredRuntimeException(): void
    {
        $array = ['type' => 'invalid'];

        try {
            RuntimeError::from($array);
            self::fail('Expected RuntimeException was not thrown.');
        } catch (RuntimeException $exception) {
            self::assertSame('Invalid error array format.', $exception->getMessage());
            self::assertSame(LOG_LEVEL['critical'], $exception->getCode());
            self::assertNull($exception->getPrevious());
            self::assertArrayHasKey('$array', $exception->context);
            self::assertInstanceOf(ContextSnapshot::class, $exception->context['$array']);
        }
    }

    public function testFromLastReturnsNullWhenNoLastError(): void
    {
        self::assertNull(RuntimeError::fromLast());
    }

    public function testFromLastReturnsTypedRuntimeErrorAfterTriggeringError(): void
    {
        @\trigger_error('notice message', E_USER_NOTICE);

        $error = RuntimeError::fromLast();

        self::assertInstanceOf(RuntimeError::class, $error);
        self::assertSame(E_USER_NOTICE, $error->type);
        self::assertSame('notice message', $error->message);
        self::assertNotSame('', $error->file);
        self::assertGreaterThan(0, $error->line);
    }

    public function testFromLastMatchesErrorGetLastPayload(): void
    {
        @\trigger_error('parity check', E_USER_WARNING);

        $phpLastError = error_get_last();
        self::assertIsArray($phpLastError);

        $error = RuntimeError::fromLast();
        self::assertNotNull($error);

        self::assertSame($phpLastError['type'], $error->type);
        self::assertSame($phpLastError['message'], $error->message);
        self::assertSame($phpLastError['file'], $error->file);
        self::assertSame($phpLastError['line'], $error->line);
    }

    public function testToStringFormatsFileLineAndMessage(): void
    {
        $error = RuntimeError::from([
            'type'    => E_USER_NOTICE,
            'message' => 'Something went wrong',
            'file'    => '/path/to/file.php',
            'line'    => 42,
        ]);

        self::assertSame('/path/to/file.php:42: Something went wrong', (string) $error);
    }

    public function testJsonSerializeReturnsErrorArray(): void
    {
        $array = self::sampleArray();
        $error = RuntimeError::from($array);

        self::assertSame($array, $error->jsonSerialize());
        self::assertSame($array, $error->__serialize());
    }

    public function testSerializeRoundTripPreservesValues(): void
    {
        $original = RuntimeError::from(self::sampleArray());

        $restored = \unserialize(\serialize($original));

        self::assertNotSame($original, $restored);
        self::assertSame($original->__serialize(), $restored->__serialize());
        self::assertSame((string) $original, (string) $restored);
    }

    public function testImplementsStringableAndJsonSerializable(): void
    {
        $error = RuntimeError::from(self::sampleArray());

        self::assertInstanceOf(Stringable::class, $error);
        self::assertInstanceOf(JsonSerializable::class, $error);
    }

    /**
     * @return array{
     *     type: int,
     *     message: string,
     *     file: string,
     *     line: int,
     * }
     */
    private static function sampleArray(): array
    {
        return [
            'type'    => E_USER_NOTICE,
            'message' => 'Something went wrong',
            'file'    => '/path/to/file.php',
            'line'    => 42,
        ];
    }
}
