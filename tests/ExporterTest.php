<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests;

use Northrook\AppEnv;
use Northrook\Contracts\AppEnvironment;
use Northrook\Contracts\Exporter;
use Northrook\Contracts\RuntimeException;
use Northrook\Contracts\Secret;
use Northrook\Contracts\Serializable;
use Northrook\Contracts\Serializer;
use Northrook\Contracts\Value;
use Northrook\Contracts\Value\Secret as SecretPolicy;
use PHPUnit\Framework\TestCase;

final class ExporterTest extends TestCase
{
    protected function setUp(): void
    {
        $this->resetAppEnv();
    }

    protected function tearDown(): void
    {
        $this->resetAppEnv();
    }

    public function testSerializeBypassesCredentialRefusalForNestedSerializableGraph(): void
    {
        $this->becomePublic();

        $fixture = new ExporterNestedCredentialFixture(
            dsn  : new Value('postgres://dsn', SecretPolicy::CREDENTIAL),
            plain: 'visible',
            token: 'param-secret',
        );

        $payload  = Exporter::serialize($fixture);
        $restored = \unserialize($payload);

        self::assertInstanceOf(ExporterNestedCredentialFixture::class, $restored);
        self::assertSame('postgres://dsn', $restored->dsn->value);
        self::assertSame('visible', $restored->plain);
        self::assertSame('param-secret', $restored->token);
    }

    public function testOverrideClearsAfterSuccessfulCall(): void
    {
        $this->becomePublic();

        $fixture = new ExporterNestedCredentialFixture(
            dsn  : new Value('postgres://dsn', SecretPolicy::CREDENTIAL),
            plain: 'visible',
            token: 'param-secret',
        );

        Exporter::serialize($fixture);
        self::assertFalse(Exporter::isOverrideActive());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot serialize credential property $value');
        \serialize($fixture);
    }

    public function testOverrideClearsAfterThrownBackend(): void
    {
        $this->becomePublic();

        $fixture = new ExporterNestedCredentialFixture(
            dsn  : new Value('postgres://dsn', SecretPolicy::CREDENTIAL),
            plain: 'visible',
            token: 'param-secret',
        );

        try {
            Exporter::json($fixture, \JSON_THROW_ON_ERROR, 1);
            self::fail('expected json depth throw');
        } catch (\JsonException) {
        }

        self::assertFalse(Exporter::isOverrideActive());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot serialize credential property $value');
        \serialize($fixture);
    }

    public function testReentrantExportKeepsOverrideUntilOutermostCompletes(): void
    {
        $this->becomePublic();

        $inner = new Value('runtime-only', SecretPolicy::CREDENTIAL);
        $outer = new ExporterReentrantTrigger($inner);

        $payload  = Exporter::serialize($outer);
        $restored = \unserialize($payload);

        self::assertInstanceOf(ExporterReentrantTrigger::class, $restored);
        self::assertSame('runtime-only', $restored->nested->value);
        self::assertFalse(Exporter::isOverrideActive());
    }

    public function testJsonBypassesCredentialRefusalForNestedSerializableGraph(): void
    {
        $this->becomePublic();

        $fixture = new ExporterNestedCredentialFixture(
            dsn  : new Value('postgres://dsn', SecretPolicy::CREDENTIAL),
            plain: 'visible',
            token: 'param-secret',
        );

        $json = Exporter::json($fixture, \JSON_THROW_ON_ERROR);

        self::assertIsString($json);
        self::assertStringContainsString('"dsn":{"value":"postgres:\/\/dsn"', $json);
        self::assertStringContainsString('"plain":"visible"', $json);
        self::assertStringContainsString('"token":"param-secret"', $json);
    }

    public function testVarExportBypassesCredentialRefusalForNestedSerializableGraph(): void
    {
        $this->becomePublic();

        $fixture = new ExporterNestedCredentialFixture(
            dsn  : new Value('postgres://dsn', SecretPolicy::CREDENTIAL),
            plain: 'visible',
            token: 'param-secret',
        );

        $export = Exporter::var($fixture);

        self::assertStringContainsString('postgres://dsn', $export);
        self::assertStringContainsString('param-secret', $export);
        self::assertFalse(Exporter::isOverrideActive());
    }

    private function becomePublic(): void
    {
        $this->resetAppEnv();
        new AppEnv(AppEnvironment::Production, public: true);
        self::assertTrue(AppEnv::isPublic());
    }

    private function resetAppEnv(): void
    {
        $property = new \ReflectionProperty(AppEnv::class, 'instance');
        $property->setValue(null, null);
        Exporter::reset();
    }
}

final class ExporterNestedCredentialFixture implements Serializable
{
    use Serializer;

    public function __construct(
        #[Secret]
        public string $token,
        public Value  $dsn,
        public string $plain,
    ) {}
}

/**
 * Nested {@see Exporter::serialize()} during an outer export — depth must stay
 * armed until the outermost call finishes.
 */
final class ExporterReentrantTrigger
{
    public function __construct(
        public Value $nested,
    ) {}

    /**
     * @return array{nested: Value}
     */
    public function __serialize(): array
    {
        $inner = Exporter::serialize($this->nested);

        $restored = \unserialize($inner);
        if (! $restored instanceof Value) {
            throw new \LogicException('Expected nested Value round-trip.');
        }

        return ['nested' => $restored];
    }

    /**
     * @param array{nested: Value} $data
     */
    public function __unserialize(
        array $data,
    ): void {
        $this->nested = $data['nested'];
    }
}
