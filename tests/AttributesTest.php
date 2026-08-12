<?php /** @noinspection PhpToStringImplementationInspection */

/** @noinspection PhpExpressionResultUnusedInspection */

declare(strict_types=1);

namespace Northrook\Contracts\Tests;

use Northrook\Contracts\Autowire;
use Northrook\Contracts\Autowire\Logger;
use Northrook\Contracts\Autowire\Pathfinder;
use Northrook\Contracts\DependencyException;
use Northrook\Contracts\InvalidArgumentException;
use Northrook\Contracts\Path;
use Northrook\Contracts\PathfinderInterface;
use Northrook\Contracts\RuntimeException;
use Northrook\Contracts\Secret;
use Northrook\Contracts\Url;
use Northrook\Contracts\Value\Redactor;
use Northrook\Contracts\Value\Secret as SecretPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final class AttributesTest extends TestCase
{
    // ── Secret attribute ─────────────────────────────────────────

    public function testSecretAttributeDefaultsToFrozenSensitive(): void
    {
        $attribute = new Secret;

        self::assertSame(SecretPolicy::SENSITIVE, $attribute->secret->type);
        self::assertSame([], $attribute->secret->conditions);
        self::assertTrue($attribute->secret->isFrozen());
    }

    public function testSecretAttributeAcceptsCredentialType(): void
    {
        $attribute = new Secret(type: SecretPolicy::CREDENTIAL);

        self::assertSame(SecretPolicy::CREDENTIAL, $attribute->secret->type);
        self::assertTrue($attribute->secret->isFrozen());
    }

    public function testSecretAttributeAcceptsCondition(): void
    {
        $attribute = new Secret(SecretPolicy::CREDENTIAL, 'db-dsn');

        self::assertSame(SecretPolicy::CREDENTIAL, $attribute->secret->type);
        self::assertTrue($attribute->secret->hasCondition('db-dsn'));
        self::assertTrue($attribute->secret->isFrozen());
    }

    public function testSecretAttributeAcceptsMultipleConditions(): void
    {
        $attribute = new Secret(SecretPolicy::SENSITIVE, 'oauth-token', 'api-key');

        self::assertTrue($attribute->secret->hasCondition('oauth-token'));
        self::assertTrue($attribute->secret->hasCondition('api-key'));
        self::assertSame(
            ['oauth-token', 'api-key'],
            \array_keys($attribute->secret->conditions),
        );
    }

    public function testSecretAttributeFreezesPolicyAgainstMutation(): void
    {
        $attribute = new Secret(SecretPolicy::SENSITIVE, 'x');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Secret is immutable.');
        $attribute->secret->addCondition('y');
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

    public function testSecretAttributeRejectsInvalidCondition(): void
    {
        $this->expectException(InvalidArgumentException::class);

        // @phpstan-ignore-next-line Testing invalid input.
        new Secret(SecretPolicy::SENSITIVE, '');
    }

    public function testSecretAttributeFromReflection(): void
    {
        $property   = new \ReflectionProperty(SecretAttributeFixture::class, 'token');
        $attributes = $property->getAttributes(Secret::class);

        self::assertCount(1, $attributes);

        $attribute = $attributes[0]->newInstance();

        self::assertSame(SecretPolicy::CREDENTIAL, $attribute->secret->type);
        self::assertTrue($attribute->secret->hasCondition('oauth-token'));
        self::assertTrue($attribute->secret->isFrozen());
    }

    public function testSecretPolicyTypeConstants(): void
    {
        self::assertSame('sensitive', SecretPolicy::SENSITIVE);
        self::assertSame('credential', SecretPolicy::CREDENTIAL);
    }

    public function testRedactorDistinguishesTypeAndSensitiveParameter(): void
    {
        $redactor = new Redactor;

        self::assertSame(
            '[sensitive::string]',
            $redactor('x', new SecretPolicy(SecretPolicy::SENSITIVE)),
        );
        self::assertSame(
            '[credential::string]',
            $redactor('x', new SecretPolicy(SecretPolicy::CREDENTIAL)),
        );
        self::assertSame(
            '[sensitive::' . \SensitiveParameter::class . ']',
            $redactor(
                'abc',
                new SecretPolicy(SecretPolicy::SENSITIVE, [\SensitiveParameter::class]),
            ),
        );
        self::assertSame(
            '[sensitive::string]',
            ( new SecretPolicy(SecretPolicy::SENSITIVE) )('x'),
        );
    }

    public function testRedactorExtensionCanOverrideAndDefer(): void
    {
        $redactor = new class() extends Redactor {
            protected function redact(
                mixed $value,
            ): mixed {
                if ($this->secret->hasCondition('db-dsn')) {
                    return '[dsn]';
                }

                return parent::redact($value);
            }
        };

        self::assertSame(
            '[dsn]',
            $redactor('postgres://…', new SecretPolicy(SecretPolicy::CREDENTIAL, ['db-dsn'])),
        );
        self::assertSame(
            '[sensitive::string]',
            $redactor('x', new SecretPolicy(SecretPolicy::SENSITIVE)),
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
            '[credential::string]',
            ( new SecretPolicy(SecretPolicy::CREDENTIAL) )('token'),
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

final class SecretAttributeFixture
{
    public function __construct(
        #[Secret(SecretPolicy::CREDENTIAL, 'oauth-token')]
        public string $token = 'x',
    ) {}
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
