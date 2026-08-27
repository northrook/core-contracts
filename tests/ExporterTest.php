<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests;

use Northrook\Container\Secret;
use Northrook\Context;
use Northrook\Context\AppEnv;
use Northrook\Context\ContextManager;
use Northrook\Contracts\Serializable;
use Northrook\Contracts\Tests\Support\ResetsContext;
use Northrook\Exporter;
use Northrook\Kernel\KernelContext;
use Northrook\Parameter\Secret as SecretPolicy;
use Northrook\RuntimeException;
use Northrook\Serializer;
use PHPUnit\Framework\TestCase;

final class ExporterTest extends TestCase
{
    private ContextManager $contextManager;

    protected function setUp(): void
    {
        $this->resetIsolation();

        $this->contextManager = new ContextManager;
        Context::register(
            appEnv        : AppEnv::Testing,
            contextManager: $this->contextManager,
        );
        Exporter::reset();
    }

    protected function tearDown(): void
    {
        Exporter::reset();
        $this->resetIsolation();
    }

    public function testSerializeBypassesCredentialRefusalForNestedSerializableGraph(): void
    {
        $this->becomeOutbound();

        $fixture = new ExporterNestedCredentialFixture(
            dsn  : new ExporterCredentialBearer('postgres://dsn'),
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
        $this->becomeOutbound();

        $fixture = new ExporterNestedCredentialFixture(
            dsn  : new ExporterCredentialBearer('postgres://dsn'),
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
        $this->becomeOutbound();

        $fixture = new ExporterNestedCredentialFixture(
            dsn  : new ExporterCredentialBearer('postgres://dsn'),
            plain: 'visible',
            token: 'param-secret',
        );

        try {
            Exporter::json($fixture, \JSON_THROW_ON_ERROR, 1);
            self::fail('expected json depth throw');
        }
        catch (\JsonException) {
        }

        self::assertFalse(Exporter::isOverrideActive());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot serialize credential property $value');
        \serialize($fixture);
    }

    public function testReentrantExportKeepsOverrideUntilOutermostCompletes(): void
    {
        $this->becomeOutbound();

        $inner = new ExporterCredentialBearer('runtime-only');
        $outer = new ExporterReentrantTrigger($inner);

        $payload  = Exporter::serialize($outer);
        $restored = \unserialize($payload);

        self::assertInstanceOf(ExporterReentrantTrigger::class, $restored);
        self::assertSame('runtime-only', $restored->nested->value);
        self::assertFalse(Exporter::isOverrideActive());
    }

    public function testJsonBypassesCredentialRefusalForNestedSerializableGraph(): void
    {
        $this->becomeOutbound();

        $fixture = new ExporterNestedCredentialFixture(
            dsn  : new ExporterCredentialBearer('postgres://dsn'),
            plain: 'visible',
            token: 'param-secret',
        );

        $json = Exporter::json($fixture, \JSON_THROW_ON_ERROR);

        self::assertIsString($json);
        self::assertStringContainsString('"dsn":{"value":"postgres:\/\/dsn"}', $json);
        self::assertStringContainsString('"plain":"visible"', $json);
        self::assertStringContainsString('"token":"param-secret"', $json);
    }

    public function testVarExportBypassesCredentialRefusalForNestedSerializableGraph(): void
    {
        $this->becomeOutbound();

        $fixture = new ExporterNestedCredentialFixture(
            dsn  : new ExporterCredentialBearer('postgres://dsn'),
            plain: 'visible',
            token: 'param-secret',
        );

        $export = Exporter::var($fixture);

        self::assertStringContainsString('postgres://dsn', $export);
        self::assertStringContainsString('param-secret', $export);
        self::assertFalse(Exporter::isOverrideActive());
    }

    private function becomeOutbound(): void
    {
        $this->contextManager->update(KernelContext::Request);
    }

    private function resetIsolation(): void
    {
        ResetsContext::reset();
    }
}

final class ExporterCredentialBearer implements Serializable
{
    use Serializer;

    public function __construct(
        #[Secret(type: SecretPolicy::CREDENTIAL)]
        public string $value,
    ) {}
}

final class ExporterNestedCredentialFixture implements Serializable
{
    use Serializer;

    public function __construct(
        #[Secret]
        public string                   $token,
        public ExporterCredentialBearer $dsn,
        public string                   $plain,
    ) {}
}

/**
 * Nested {@see Exporter::serialize()} during an outer export — depth must stay
 * armed until the outermost call finishes.
 */
final class ExporterReentrantTrigger
{
    public function __construct(
        public ExporterCredentialBearer $nested,
    ) {}

    /**
     * @return array{nested: ExporterCredentialBearer}
     */
    public function __serialize(): array
    {
        $inner = Exporter::serialize($this->nested);

        $restored = \unserialize($inner);
        if (! $restored instanceof ExporterCredentialBearer) {
            throw new \LogicException('Expected nested ExporterCredentialBearer round-trip.');
        }

        return ['nested' => $restored];
    }

    /**
     * @param array{nested: ExporterCredentialBearer} $data
     */
    public function __unserialize(
        array $data,
    ): void {
        $this->nested = $data['nested'];
    }
}
