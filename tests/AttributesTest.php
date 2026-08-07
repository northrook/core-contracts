<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests;

use Northrook\Contracts\Autowire;
use Northrook\Contracts\Autowire\Logger;
use Northrook\Contracts\Autowire\Pathfinder;
use Northrook\Contracts\DependencyException;
use Northrook\Contracts\InvalidArgumentException;
use Northrook\Contracts\Path;
use Northrook\Contracts\PathfinderInterface;
use Northrook\Contracts\Redactor;
use Northrook\Contracts\Secret;
use Northrook\Contracts\Url;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final class AttributesTest extends TestCase
{
    // ── Secret ───────────────────────────────────────────────────

    public function testSecretDefaultsToSensitive(): void
    {
        $secret = new Secret('hunter2');

        self::assertSame('hunter2', $secret->value);
        self::assertSame(Secret::SENSITIVE, $secret->type);
        self::assertNull($secret->condition);
    }

    public function testSecretDefaultsToNullValue(): void
    {
        $secret = new Secret;

        self::assertNull($secret->value);
        self::assertSame(Secret::SENSITIVE, $secret->type);
        self::assertNull($secret->condition);
    }

    public function testSecretAcceptsCredentialType(): void
    {
        $secret = new Secret('token', Secret::CREDENTIAL);

        self::assertSame('token', $secret->value);
        self::assertSame(Secret::CREDENTIAL, $secret->type);
    }

    public function testSecretAcceptsCondition(): void
    {
        $secret = new Secret('dsn', Secret::CREDENTIAL, 'db-dsn');

        self::assertSame('dsn', $secret->value);
        self::assertSame(Secret::CREDENTIAL, $secret->type);
        self::assertSame('db-dsn', $secret->condition);
    }

    public function testSecretRejectsInvalidType(): void
    {
        $this->expectException(InvalidArgumentException::class);
        // @phpstan-ignore-next-line Testing invalid input.
        new Secret('token', 'bogus');
    }

    public function testSecretTypeConstants(): void
    {
        self::assertSame('sensitive', Secret::SENSITIVE);
        self::assertSame('credential', Secret::CREDENTIAL);
    }

    public function testDefaultRedactorDistinguishesTypeAndSensitiveParameter(): void
    {
        $redactor = new Redactor;

        self::assertSame('[Secret::string]', $redactor('x', Secret::SENSITIVE));
        self::assertSame('[Credential::string]', $redactor('x', Secret::CREDENTIAL));
        self::assertSame(
            '***',
            $redactor('abc', Secret::SENSITIVE, Secret::CONDITION_SENSITIVE_PARAMETER),
        );
        self::assertSame(
            '[Secret::string]',
            Secret::defaultRedactor('x', Secret::SENSITIVE),
        );
    }

    public function testRedactorExtensionCanOverrideAndDefer(): void
    {
        $redactor = new class() extends Redactor {
            protected function redact(
                mixed       $value,
                string      $type,
                null|string $condition = null,
            ): string {
                if ($condition === 'db-dsn') {
                    return '[dsn]';
                }

                return parent::redact($value, $type, $condition);
            }
        };

        self::assertSame('[dsn]', $redactor('postgres://…', Secret::CREDENTIAL, 'db-dsn'));
        self::assertSame('[Secret::string]', $redactor('x', Secret::SENSITIVE));
    }

    public function testRedactorDisallowsCloneAndToStringViaPhpDoc(): void
    {
        $reflection = new \ReflectionClass(Redactor::class);
        $doc        = $reflection->getDocComment();

        self::assertFalse($reflection->hasMethod('__clone'));
        self::assertFalse($reflection->hasMethod('__toString'));
        self::assertNotFalse($doc);
        self::assertStringContainsString('@disallows __clone(), __toString()', $doc);
    }

    public function testRedactorStringCastFailsAtEngine(): void
    {
        $this->expectException(\Error::class);

        // @phpstan-ignore-next-line Testing engine rejection of non-Stringable cast.
        (string) new Redactor;
    }

    public function testRedactUsesInstanceTypeAndHint(): void
    {
        self::assertSame(
            '[Credential::string]',
            Secret::redact(new Secret('token', Secret::CREDENTIAL)),
        );
    }

    public function testSecretAttributeTargetsAll(): void
    {
        self::assertSame(\Attribute::TARGET_ALL, self::attributeFlags(Secret::class));
    }

    // ── Autowire ─────────────────────────────────────────────────

    public function testAutowireResolvesReferenceHandler(): void
    {
        $autowire = new Autowire(reference: 'app.token');

        self::assertSame('reference', $autowire->type);
        self::assertSame('app.token', $autowire->handler);
    }

    public function testAutowireResolvesResolveHandler(): void
    {
        $resolve  = static fn(): string => 'value';
        $autowire = new Autowire(resolve: $resolve);

        self::assertSame('resolve', $autowire->type);
        self::assertSame($resolve, $autowire->handler);
    }

    public function testAutowireRejectsMissingHandler(): void
    {
        $this->expectException(DependencyException::class);
        $this->expectExceptionMessage('No autowire handler provided; expected reference or resolve.');
        new Autowire;
    }

    public function testAutowireRejectsMultipleHandlers(): void
    {
        $this->expectException(DependencyException::class);
        $this->expectExceptionMessage('Invalid autowire handler; provide exactly one of reference or resolve.');
        new Autowire(
            reference: 'app.token',
            resolve  : static fn(): string => 'value',
        );
    }

    public function testAutowireAttributeTargetsParameterAndProperty(): void
    {
        self::assertSame(
            \Attribute::TARGET_PARAMETER | \Attribute::TARGET_PROPERTY,
            self::attributeFlags(Autowire::class),
        );
    }

    // ── Autowire\Logger trait ────────────────────────────────────

    public function testLoggerAssignmentSkippedWithoutLogger(): void
    {
        $fixture = new AttributesLoggerFixture;

        $fixture->__autowireLogger(null);

        self::assertFalse($fixture->loggerIsSet());
    }

    public function testLoggerAssignsNullLoggerWhenRequested(): void
    {
        $fixture = new AttributesLoggerFixture;

        $fixture->__autowireLogger(null, assignNull: true);

        self::assertTrue($fixture->loggerIsSet());
        self::assertInstanceOf(NullLogger::class, $fixture->loggerInstance());
    }

    public function testLoggerAssignsProvidedLogger(): void
    {
        $fixture = new AttributesLoggerFixture;
        $logger  = new AttributesSpyLogger;

        $fixture->__autowireLogger($logger);

        self::assertSame($logger, $fixture->loggerInstance());
    }

    #[DataProvider('provideLogExceptionLevels')]
    public function testLogExceptionResolvesLevel(
        \Throwable $exception,
        string     $expectedLevel,
    ): void {
        $fixture = new AttributesLoggerFixture;
        $logger  = new AttributesSpyLogger;
        $fixture->__autowireLogger($logger);

        $fixture->captureLogException($exception, continue: true);

        self::assertCount(1, $logger->records);
        self::assertSame($expectedLevel, $logger->records[0]['level']);
        self::assertSame($exception->getMessage(), $logger->records[0]['message']);
        self::assertSame($exception, $logger->records[0]['context']['exception']);
    }

    public static function provideLogExceptionLevels(): \Generator
    {
        yield 'runtime exception defaults to critical' => [new \RuntimeException('boom'), 'critical'];
        yield 'logic exception defaults to critical' => [new \LogicException('boom'), 'critical'];
        yield 'plain exception defaults to error' => [new \Exception('boom'), 'error'];
        yield 'error defaults to warning' => [new \Error('boom'), 'warning'];
        yield 'mapped code resolves level name' => [new \RuntimeException('boom', 400), 'error'];
        yield 'mapped emergency code' => [new \Exception('boom', 600), 'emergency'];
    }

    public function testLogExceptionUsesOverrideMessageAndContext(): void
    {
        $fixture = new AttributesLoggerFixture;
        $logger  = new AttributesSpyLogger;
        $fixture->__autowireLogger($logger);

        $fixture->captureLogException(
            new \RuntimeException('original'),
            message: 'override',
            context: ['key' => 'value'],
            continue: true,
        );

        self::assertSame('override', $logger->records[0]['message']);
        self::assertSame('value', $logger->records[0]['context']['key']);
    }

    public function testLogExceptionRethrowsByDefault(): void
    {
        $fixture = new AttributesLoggerFixture;
        $logger  = new AttributesSpyLogger;
        $fixture->__autowireLogger($logger);

        $exception = new \RuntimeException('boom');

        try {
            $fixture->captureLogException($exception, continue: false);
            self::fail('Expected the exception to be rethrown.');
        } catch (\RuntimeException $caught) {
            self::assertSame($exception, $caught);
        }

        self::assertCount(1, $logger->records);
    }

    // ── Autowire\Pathfinder trait ────────────────────────────────

    public function testPathfinderAssignment(): void
    {
        $fixture = new AttributesPathfinderFixture;

        self::assertFalse($fixture->pathfinderIsSet());

        $pathfinder = new AttributesPathfinderStub;
        $fixture->__autowirePathfinder($pathfinder);

        self::assertTrue($fixture->pathfinderIsSet());
        self::assertSame($pathfinder, $fixture->pathfinderInstance());
    }

    /**
     * @param class-string $class
     */
    private static function attributeFlags(
        string $class,
    ): int {
        $attributes = new \ReflectionClass($class)->getAttributes(\Attribute::class);

        self::assertCount(1, $attributes);

        return $attributes[0]->newInstance()->flags;
    }
}

final class AttributesLoggerFixture
{
    use Logger;

    public function loggerIsSet(): bool
    {
        return isset($this->logger);
    }

    public function loggerInstance(): LoggerInterface
    {
        return $this->logger;
    }

    /**
     * @param array<string, mixed> $context
     */
    public function captureLogException(
        \Throwable  $exception,
        null|string $message = null,
        array       $context = [],
        bool        $continue = true,
    ): void {
        $this->logException($exception, $message, $context, $continue);
    }
}

final class AttributesSpyLogger extends AbstractLogger
{
    /**
     * @var list<array{level: string, message: string, context: array<string, mixed>}>
     */
    public array $records = [];

    public function log(
                           $level,
        string|\Stringable $message,
        array              $context = [],
    ): void {
        if (! \is_string($level) && ! $level instanceof \Stringable) {
            throw new \InvalidArgumentException('Log level must be stringable.');
        }

        $this->records[] = [
            'level'   => (string) $level,
            'message' => (string) $message,
            'context' => $context,
        ];
    }
}

final class AttributesPathfinderFixture
{
    use Pathfinder;

    public function pathfinderIsSet(): bool
    {
        return isset($this->pathfinder);
    }

    public function pathfinderInstance(): PathfinderInterface
    {
        return $this->pathfinder;
    }
}

final class AttributesPathfinderStub implements PathfinderInterface
{
    public function getPath(
        string|\Stringable $reference,
    ): null|Path {
        return null;
    }

    public function getUrl(
        string|\Stringable $reference,
    ): null|Url {
        return null;
    }
}
