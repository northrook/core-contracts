<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests;

use Northrook\ErrorHandler\RuntimeError;
use Northrook\RuntimeException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RuntimeErrorTest extends TestCase
{
    public function testFromCreatesTypedError(): void
    {
        $error = RuntimeError::from([
            'type'    => \E_USER_WARNING,
            'message' => 'something happened',
            'file'    => '/srv/app/index.php',
            'line'    => 123,
        ]);

        self::assertSame(\E_USER_WARNING, $error->type);
        self::assertSame('something happened', $error->message);
        self::assertSame('/srv/app/index.php', $error->file);
        self::assertSame(123, $error->line);
    }

    public function testFromIgnoresSurplusKeys(): void
    {
        $error = RuntimeError::from([
            'type'    => \E_NOTICE,
            'message' => 'extra keys',
            'file'    => 'a.php',
            'line'    => 1,
            'surplus' => 'ignored',
        ]);

        self::assertSame(
            [
                'type'    => \E_NOTICE,
                'message' => 'extra keys',
                'file'    => 'a.php',
                'line'    => 1,
            ],
            $error->toArray(),
        );
    }

    /**
     * @param array<string, mixed> $input
     */
    #[DataProvider('provideInvalidErrorArrays')]
    public function testFromRejectsInvalidArrays(
        array $input,
    ): void {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid error array format.');

        RuntimeError::from($input);
    }

    public static function provideInvalidErrorArrays(): \Generator
    {
        $valid = [
            'type'    => \E_WARNING,
            'message' => 'valid',
            'file'    => 'a.php',
            'line'    => 1,
        ];

        yield 'missing type' => [\array_diff_key($valid, ['type' => true])];
        yield 'missing message' => [\array_diff_key($valid, ['message' => true])];
        yield 'missing file' => [\array_diff_key($valid, ['file' => true])];
        yield 'missing line' => [\array_diff_key($valid, ['line' => true])];
        yield 'type as string' => [[...$valid, 'type' => '2']];
        yield 'message as int' => [[...$valid, 'message' => 42]];
        yield 'file as null' => [[...$valid, 'file' => null]];
        yield 'line as string' => [[...$valid, 'line' => '12']];
        yield 'empty array' => [[]];
    }

    public function testFromValidationFailureSkipsPreviousAndCarriesContext(): void
    {
        try {
            RuntimeError::from(['bogus' => true]);
            self::fail('Expected RuntimeException for invalid error array');
        } catch (RuntimeException $exception) {
            self::assertSame('Invalid error array format.', $exception->getMessage());
            self::assertNull($exception->getPrevious());
            self::assertSame(['bogus' => true], $exception->getContext()['$array']);
        }
    }

    public function testFromLastMatchesErrorGetLastPayload(): void
    {
        @\trigger_error('runtime error probe', \E_USER_WARNING);

        $expected = \error_get_last();
        $error    = RuntimeError::fromLast();

        self::assertNotNull($expected);
        self::assertNotNull($error);
        self::assertSame($expected['type'], $error->type);
        self::assertSame($expected['message'], $error->message);
        self::assertSame($expected['file'], $error->file);
        self::assertSame($expected['line'], $error->line);
    }

    public function testFromLastMirrorsErrorGetLastState(): void
    {
        $last = \error_get_last();

        if ($last === null) {
            self::assertNull(RuntimeError::fromLast());

            return;
        }

        $error = RuntimeError::fromLast();

        self::assertNotNull($error);
        self::assertSame($last, $error->toArray());
    }

    public function testToArrayMatchesSerializedShape(): void
    {
        $error = $this->error('shape check');

        self::assertSame($error->__serialize(), $error->toArray());
        self::assertSame($error->__serialize(), $error->jsonSerialize());
        self::assertSame(
            ['type', 'message', 'file', 'line'],
            \array_keys($error->toArray()),
        );
    }

    public function testToStringFormatsFileLineAndMessage(): void
    {
        $error = RuntimeError::from([
            'type'    => \E_WARNING,
            'message' => '  padded message  ',
            'file'    => '/path/to/file.php',
            'line'    => 9,
        ]);

        self::assertSame('/path/to/file.php:9: padded message', (string) $error);
    }

    public function testJsonEncodeUsesErrorArray(): void
    {
        $error = $this->error('json payload');

        $decoded = \json_decode(\json_encode($error, \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR);

        self::assertSame($error->toArray(), $decoded);
    }

    public function testSerializeRoundTrip(): void
    {
        $error    = $this->error('serialize me');
        $restored = \unserialize(\serialize($error));

        self::assertInstanceOf(RuntimeError::class, $restored);
        self::assertSame($error->toArray(), $restored->toArray());
        self::assertSame((string) $error, (string) $restored);
    }

    private function error(
        string $message,
    ): RuntimeError {
        return RuntimeError::from([
            'type'    => \E_USER_NOTICE,
            'message' => $message,
            'file'    => __FILE__,
            'line'    => __LINE__,
        ]);
    }
}
