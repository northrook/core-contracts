<?php /** @noinspection PhpToStringImplementationInspection */

/** @noinspection PhpExpressionResultUnusedInspection */

declare(strict_types=1);

namespace Northrook\Contracts\Tests;

use Northrook\Container\Autowire;
use Northrook\Container\Secret;
use Northrook\Contracts\Tests\Support\LoggerFixture;
use Northrook\Contracts\Tests\Support\PathfinderFixture;
use Northrook\Contracts\Tests\Support\PathfinderStub;
use Northrook\Contracts\Tests\Support\SecretMask;
use Northrook\DependencyException;
use Northrook\InvalidArgumentException;
use Northrook\Parameter\Secret as SecretPolicy;
use Northrook\Redactor;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;

final class AttributesTest extends TestCase
{
    // ── Secret attribute ─────────────────────────────────────────

    public function testSecretAttributeDefaultsToSensitive(): void
    {
        $attribute = new Secret;

        self::assertSame(SecretPolicy::SENSITIVE, $attribute->secret);
        self::assertSame([], $attribute->conditions);
    }

    public function testSecretAttributeAcceptsCredentialType(): void
    {
        $attribute = new Secret(type: SecretPolicy::CREDENTIAL);

        self::assertSame(SecretPolicy::CREDENTIAL, $attribute->secret);
    }

    public function testSecretAttributeAcceptsConditionTagSeeds(): void
    {
        $attribute = new Secret(SecretPolicy::CREDENTIAL, 'db-dsn');

        self::assertSame(SecretPolicy::CREDENTIAL, $attribute->secret);
        self::assertSame(['db-dsn'], $attribute->conditions);
    }

    public function testSecretAttributeAcceptsMultipleConditions(): void
    {
        $attribute = new Secret(SecretPolicy::SENSITIVE, 'oauth-token', 'api-key');

        self::assertSame(['oauth-token', 'api-key'], $attribute->conditions);
    }

    public function testSecretAttributeConditionRequiresTypeSlot(): void
    {
        $this->expectException(InvalidArgumentException::class);

        // First argument is `$type`, not a condition name.
        // @phpstan-ignore-next-line Testing invalid input.
        new Secret('db-dsn');
    }

    public function testSecretAttributeRejectsInvalidType(): void
    {
        $this->expectException(InvalidArgumentException::class);
        // @phpstan-ignore-next-line Testing invalid input.
        new Secret(type: 'bogus');
    }

    public function testSecretAttributeFromReflection(): void
    {
        $property   = new \ReflectionProperty(SecretAttributeFixture::class, 'token');
        $attributes = $property->getAttributes(Secret::class);

        self::assertCount(1, $attributes);

        $attribute = $attributes[0]->newInstance();

        self::assertSame(SecretPolicy::CREDENTIAL, $attribute->secret);
        self::assertSame(['oauth-token'], $attribute->conditions);
    }

    public function testSecretPolicyCases(): void
    {
        self::assertSame('SENSITIVE', SecretPolicy::SENSITIVE->name);
        self::assertSame('CREDENTIAL', SecretPolicy::CREDENTIAL->name);
    }

    public function testRedactorDistinguishesTypeAndSensitiveParameter(): void
    {
        $redactor = new Redactor;

        self::assertSame(
            SecretMask::sensitive('x'),
            $redactor('x', SecretPolicy::SENSITIVE, []),
        );
        self::assertSame(
            '[secret::credential]',
            $redactor('x', SecretPolicy::CREDENTIAL, []),
        );
        self::assertSame(
            '[secret::' . \SensitiveParameter::class . ']',
            $redactor(
                'abc',
                SecretPolicy::SENSITIVE,
                [\SensitiveParameter::class => \SensitiveParameter::class],
            ),
        );
        self::assertSame(
            SecretMask::sensitive('x'),
            ( SecretPolicy::SENSITIVE )('x'),
        );
    }

    public function testRedactorExtensionCanOverrideAndDefer(): void
    {
        $redactor = new class() extends Redactor {
            protected function redact(
                mixed $value,
            ): mixed {
                if ($this->hasContext('db-dsn')) {
                    return '[dsn]';
                }

                return parent::redact($value);
            }
        };

        self::assertSame(
            '[dsn]',
            $redactor('postgres://…', SecretPolicy::CREDENTIAL, ['db-dsn' => 'db-dsn']),
        );
        self::assertSame(
            SecretMask::sensitive('x'),
            $redactor('x', SecretPolicy::SENSITIVE, []),
        );
    }

    public function testRedactorStringCastFailsAtEngine(): void
    {
        $this->expectException(\Error::class);

        // @phpstan-ignore-next-line Testing engine rejection of non-Stringable cast.
        (string) new Redactor;
    }

    public function testSecretInvokeUsesPolicyType(): void
    {
        self::assertSame(
            '[secret::credential]',
            ( SecretPolicy::CREDENTIAL )('token'),
        );
    }

    public function testSecretAttributeInvokePassesConditionsAsContext(): void
    {
        $attribute = new Secret(SecretPolicy::SENSITIVE, \SensitiveParameter::class);

        self::assertSame(
            '[secret::' . \SensitiveParameter::class . ']',
            $attribute('token'),
        );
    }

    public function testSecretAttributeTargetsPropertyAndParameter(): void
    {
        self::assertSame(
            \Attribute::TARGET_PROPERTY | \Attribute::TARGET_PARAMETER,
            self::attributeFlags(Secret::class),
        );
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
        $fixture = new LoggerFixture;

        $fixture->_autowire_Logger(null);

        self::assertFalse($fixture->loggerIsExplicitlySet());
        self::assertInstanceOf(LoggerInterface::class, $fixture->loggerInstance());
    }

    public function testLoggerAssignsNullLoggerWhenRequested(): void
    {
        $this->markTestSkipped('LoggerInterface property cannot be explicitly set to null.');
    }

    public function testLoggerAssignsProvidedLogger(): void
    {
        $fixture = new LoggerFixture;
        $logger  = new AttributesSpyLogger;

        $fixture->_autowire_Logger($logger);

        self::assertSame($logger, $fixture->loggerInstance());
    }

    #[DataProvider('provideLogExceptionLevels')]
    public function testLogExceptionResolvesLevel(
        \Throwable $exception,
        string     $expectedLevel,
    ): void {
        $fixture = new LoggerFixture;
        $logger  = new AttributesSpyLogger;
        $fixture->_autowire_Logger($logger);

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
        $fixture = new LoggerFixture;
        $logger  = new AttributesSpyLogger;
        $fixture->_autowire_Logger($logger);

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
        $fixture = new LoggerFixture;
        $logger  = new AttributesSpyLogger;
        $fixture->_autowire_Logger($logger);

        $exception = new \RuntimeException('boom');

        try {
            $fixture->captureLogException($exception, continue: false);
            self::fail('Expected the exception to be rethrown.');
        }
        catch (\RuntimeException $caught) {
            self::assertSame($exception, $caught);
        }

        self::assertCount(1, $logger->records);
    }

    // ── Autowire\Pathfinder trait ────────────────────────────────

    public function testPathfinderAssignment(): void
    {
        $fixture = new PathfinderFixture;

        self::assertFalse($fixture->pathfinderIsSet());

        $pathfinder = new PathfinderStub;
        $fixture->_autowire_Pathfinder($pathfinder);

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

final class SecretAttributeFixture
{
    public function __construct(
        #[Secret(SecretPolicy::CREDENTIAL, 'oauth-token')]
        public string $token = 'x',
    ) {}
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
