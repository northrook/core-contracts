<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests;

use Northrook\Assets\AssetType;
use Northrook\Container\Secret;
use Northrook\Context;
use Northrook\Context\AppEnv;
use Northrook\Context\ContextManager;
use Northrook\Contracts\Serializable;
use Northrook\Contracts\Tests\Support\ResetsContext;
use Northrook\Contracts\Tests\Support\SecretMask;
use Northrook\Exporter;
use Northrook\Kernel\KernelContext;
use Northrook\Parameter;
use Northrook\Parameter\Secret as SecretPolicy;
use Northrook\Parameter\Type as ParameterType;
use Northrook\RuntimeException;
use Northrook\Serializer;
use Northrook\Timestamp;
use PHPUnit\Framework\TestCase;

/**
 * Channel matrix enforced by the {@see Serializer} trait:
 *
 * - Debug out ({@see Serializer::__debugInfo()}) redacts any secret tier.
 * - Serialize / JSON: both tiers plaintext outside {@see KernelContext::Request};
 *   {@see SecretPolicy::CREDENTIAL} throws when outbound.
 */
final class SerializerTest extends TestCase
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

    public function testDebugInfoRedactsEverySecretTier(): void
    {
        $info = SerializerChannelFixture::make()->__debugInfo();

        self::assertSame('visible', $info['plain']);
        self::assertSame(SecretMask::sensitive('secret123'), $info['apiKey']);
        self::assertSame('[secret::credential]', $info['dsn']);
        self::assertSame(SecretMask::sensitive('param-secret'), $info['token']); // #[Secret] on constructor parameter
        self::assertSame('[uninitialized]', $info['uninitialized']);
    }

    public function testSerializeAllowsSensitiveOmitsUninitialized(): void
    {
        $state = SerializerSensitiveFixture::make()->__serialize();

        self::assertSame('visible', $state['plain']);
        self::assertSame('secret123', $state['apiKey'], 'sensitive serializes plaintext');
        self::assertSame('param-secret', $state['token']);
        self::assertArrayNotHasKey('uninitialized', $state);
    }

    public function testSerializeAllowsCredentialOutsideHttpContext(): void
    {
        $state = SerializerChannelFixture::make()->__serialize();

        self::assertSame('visible', $state['plain']);
        self::assertSame('secret123', $state['apiKey']);
        self::assertSame('postgres://…', $state['dsn'], 'credential serializes when trusted');
        self::assertSame('param-secret', $state['token']);
    }

    public function testSerializeThrowsOnCredentialPropertyInHttpContext(): void
    {
        $this->becomeOutbound();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot serialize credential property $dsn');

        SerializerChannelFixture::make()->__serialize();
    }

    public function testJsonSerializeMirrorsSerialize(): void
    {
        $fixture = SerializerSensitiveFixture::make();

        self::assertSame($fixture->__serialize(), $fixture->jsonSerialize());
    }

    public function testJsonSerializeThrowsOnCredentialPropertyInHttpContext(): void
    {
        $this->becomeOutbound();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot serialize credential property $dsn');

        SerializerChannelFixture::make()->jsonSerialize();
    }

    public function testCastsApplyInEveryChannel(): void
    {
        $fixture = SerializerSensitiveFixture::make();

        self::assertSame('1700000000005', $fixture->__serialize()['created']);
        self::assertSame('style', $fixture->__serialize()['type']);
        self::assertSame('1700000000005', $fixture->__debugInfo()['created']);
        self::assertSame('style', $fixture->jsonSerialize()['type']);
    }

    public function testUnserializeRestoresState(): void
    {
        $restored = SerializerRoundTripFixture::roundTrip('visible', 'secret123');

        self::assertSame('visible', $restored->plain);
        self::assertSame('secret123', $restored->apiKey, 'sensitive restores verbatim');
        self::assertSame('parent-value', $restored->inherited());
    }

    public function testParameterSensitiveRoundTripRevalidates(): void
    {
        $parameter = new Parameter(
            key   : 'app.token',
            value : 'secret',
            type  : ParameterType::Setting,
            secret: SecretPolicy::SENSITIVE,
            tags  : ['api' => 'api'],
        );

        /** @var Parameter $restored */
        $restored = \unserialize(\serialize($parameter));

        self::assertSame('secret', $restored->value);
        self::assertSame('app.token', $restored->key);
        self::assertSame(['api'], \array_values($restored->tags));
        self::assertSame(SecretPolicy::SENSITIVE, $restored->secret);
    }

    public function testParameterCredentialRoundTripOutsideHttpContext(): void
    {
        $parameter = new Parameter(
            key   : 'db.dsn',
            value : 'postgres://…',
            type  : ParameterType::Setting,
            secret: SecretPolicy::CREDENTIAL,
            tags  : ['api' => 'api'],
        );

        /** @var Parameter $restored */
        $restored = \unserialize(\serialize($parameter));

        self::assertSame('postgres://…', $restored->value);
        self::assertSame(SecretPolicy::CREDENTIAL, $restored->secret);
        self::assertSame('[secret::credential]', $restored->__debugInfo()['value']);
    }

    public function testParameterCredentialSerializeThrowsInHttpContext(): void
    {
        $this->becomeOutbound();

        $parameter = new Parameter(
            key   : 'db.dsn',
            value : 'postgres://…',
            type  : ParameterType::Setting,
            secret: SecretPolicy::CREDENTIAL,
            tags  : ['api' => 'api'],
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot serialize credential property $value');

        \serialize($parameter);
    }

    public function testMagicMethodsAreFinal(): void
    {
        foreach (['__serialize', '__unserialize', '__debugInfo', 'jsonSerialize'] as $method) {
            $reflection = new \ReflectionMethod(Serializer::class, $method);
            self::assertTrue($reflection->isFinal(), "Serializer::{$method}() must be final");
        }
    }

    public function testNestedCredentialThrowsInHttpContextWithoutExporter(): void
    {
        $this->becomeOutbound();

        $outer = new SerializerNestedCredentialFixture(
            new SerializerCredentialBearer('runtime-only'),
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot serialize credential property $value');
        \serialize($outer);
    }

    public function testRuntimeContextAllowsCredentialSerializeAfterRequestContext(): void
    {
        $this->becomeOutbound();
        $this->contextManager->update(KernelContext::Runtime);

        $parameter = new Parameter(
            key   : 'db.dsn',
            value : 'postgres://…',
            type  : ParameterType::Setting,
            secret: SecretPolicy::CREDENTIAL,
            tags  : ['api' => 'api'],
        );

        $state = $parameter->__serialize();

        self::assertSame('postgres://…', $state['value']);

        /** @var Parameter $hydrated */
        $hydrated = eval('return ' . $parameter->_export());
        self::assertSame('postgres://…', $hydrated->value);
    }

    public function testExportThrowsOnCredentialWhenRequest(): void
    {
        $this->becomeOutbound();
        self::assertTrue(Context::is(KernelContext::Request));

        $parameter = new Parameter(
            key   : 'db.dsn',
            value : 'postgres://…',
            type  : ParameterType::Setting,
            secret: SecretPolicy::CREDENTIAL,
            tags  : [],
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot serialize credential property $value');
        $parameter->_export();
    }

    public function testExportAllowsCredentialWhenRuntime(): void
    {
        $this->contextManager->update(KernelContext::Runtime);

        $parameter = new Parameter(
            key   : 'db.dsn',
            value : 'postgres://…',
            type  : ParameterType::Setting,
            secret: SecretPolicy::CREDENTIAL,
            tags  : [],
        );

        $exported = $parameter->_export();
        /** @var Parameter $hydrated */
        $hydrated = eval('return ' . $exported);

        self::assertSame('postgres://…', $hydrated->value);
        self::assertSame(SecretPolicy::CREDENTIAL, $hydrated->secret);
    }

    private function becomeOutbound(): void
    {
        $this->contextManager->update(KernelContext::Request);
        self::assertTrue(Context::is(KernelContext::Request));
    }

    private function resetIsolation(): void
    {
        ResetsContext::reset();
    }
}

class SerializerChannelFixture implements Serializable
{
    use Serializer;

    public string $plain = 'visible';

    #[Secret]
    public string $apiKey = 'secret123';

    #[Secret(SecretPolicy::CREDENTIAL)]
    public string $dsn = 'postgres://…';

    public string $token;

    public Timestamp $created;

    public AssetType $type = AssetType::Style;

    public string $uninitialized;

    public function __construct(
        #[Secret]
        string $token,
    ) {
        $this->token   = $token;
        $this->created = new Timestamp(1_700_000_000_005);
    }

    public static function make(): self
    {
        return new self('param-secret');
    }
}

class SerializerSensitiveFixture implements Serializable
{
    use Serializer;

    public string $plain = 'visible';

    #[Secret]
    public string $apiKey = 'secret123';

    public string $token;

    public Timestamp $created;

    public AssetType $type = AssetType::Style;

    public string $uninitialized;

    public function __construct(
        #[Secret]
        string $token,
    ) {
        $this->token   = $token;
        $this->created = new Timestamp(1_700_000_000_005);
    }

    public static function make(): self
    {
        return new self('param-secret');
    }
}

class SerializerRoundTripParentFixture
{
    private string $inherited = 'parent-value';

    public function inherited(): string
    {
        return $this->inherited;
    }
}

class SerializerRoundTripFixture extends SerializerRoundTripParentFixture implements Serializable
{
    use Serializer;

    public string $plain = '';

    #[Secret]
    public string $apiKey = '';

    public static function roundTrip(
        string $plain,
        string $apiKey,
    ): self {
        $fixture         = new self;
        $fixture->plain  = $plain;
        $fixture->apiKey = $apiKey;

        $restored = \unserialize(\serialize($fixture));

        if (! $restored instanceof self) {
            throw new \LogicException('Round-trip failed to restore ' . self::class);
        }

        return $restored;
    }
}

final class SerializerCredentialBearer implements Serializable
{
    use Serializer;

    public function __construct(
        #[Secret(type: SecretPolicy::CREDENTIAL)]
        public string $value,
    ) {}
}

final class SerializerNestedCredentialFixture implements Serializable
{
    use Serializer;

    public function __construct(
        public SerializerCredentialBearer $secret,
    ) {}
}
