<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests;

use Northrook\AppEnv;
use Northrook\Contracts\AppEnvironment;
use Northrook\Contracts\AssetType;
use Northrook\Contracts\Exporter;
use Northrook\Contracts\RuntimeException;
use Northrook\Contracts\Secret;
use Northrook\Contracts\Serializable;
use Northrook\Contracts\Serializer;
use Northrook\Contracts\Tests\Support\TestParameter;
use Northrook\Contracts\Timestamp;
use Northrook\Contracts\Value;
use Northrook\Contracts\Value\Secret as SecretPolicy;
use PHPUnit\Framework\TestCase;

/**
 * Channel matrix enforced by the {@see Serializer} trait:
 *
 * - Debug out ({@see Serializer::__debugInfo()}) redacts any secret tier.
 * - Serialize / JSON: both tiers plaintext when `!{@see AppEnv::isPublic()}`;
 *   {@see SecretPolicy::CREDENTIAL} throws when public.
 */
final class SerializerTest extends TestCase
{
    protected function setUp(): void
    {
        $this->resetAppEnv();
    }

    protected function tearDown(): void
    {
        $this->resetAppEnv();
    }

    public function testDebugInfoRedactsEverySecretTier(): void
    {
        $info = SerializerChannelFixture::make()->__debugInfo();

        self::assertSame('visible', $info['plain']);
        self::assertSame('[sensitive::string]', $info['apiKey']);
        self::assertSame('[credential::string]', $info['dsn']);
        self::assertSame('[sensitive::string]', $info['token']); // #[Secret] on constructor parameter
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

    public function testSerializeAllowsCredentialWhenNotPublic(): void
    {
        self::assertFalse(AppEnv::isPublic());

        $state = SerializerChannelFixture::make()->__serialize();

        self::assertSame('visible', $state['plain']);
        self::assertSame('secret123', $state['apiKey']);
        self::assertSame('postgres://…', $state['dsn'], 'credential serializes when trusted');
        self::assertSame('param-secret', $state['token']);
    }

    public function testSerializeThrowsOnCredentialPropertyWhenPublic(): void
    {
        $this->becomePublic();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot serialize credential property $dsn');

        SerializerChannelFixture::make()->__serialize();
    }

    public function testJsonSerializeMirrorsSerialize(): void
    {
        $fixture = SerializerSensitiveFixture::make();

        self::assertSame($fixture->__serialize(), $fixture->jsonSerialize());
    }

    public function testJsonSerializeThrowsOnCredentialPropertyWhenPublic(): void
    {
        $this->becomePublic();

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

    public function testValueSelfRedactsPerChannel(): void
    {
        $sensitive  = new Value('plaintext', SecretPolicy::SENSITIVE);
        $credential = new Value('runtime-only', SecretPolicy::CREDENTIAL);

        self::assertSame('plaintext', $sensitive->__serialize()['value']);
        self::assertSame('runtime-only', $credential->__serialize()['value'], 'credential OK when trusted');
        self::assertSame('[sensitive::string]', $sensitive->__debugInfo()['value']);
        self::assertSame('[credential::string]', $credential->__debugInfo()['value']);
    }

    public function testValueCredentialSerializeThrowsWhenPublic(): void
    {
        $this->becomePublic();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot serialize credential property $value');

        ( new Value('runtime-only', SecretPolicy::CREDENTIAL) )->__serialize();
    }

    public function testValueCredentialPhpSerializeThrowsWhenPublic(): void
    {
        $this->becomePublic();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot serialize credential property $value');

        \serialize(new Value('runtime-only', SecretPolicy::CREDENTIAL));
    }

    public function testParameterSensitiveRoundTripRevalidates(): void
    {
        $parameter = new TestParameter(
            key   : 'App.Token',
            value : 'secret',
            secret: SecretPolicy::SENSITIVE,
            tags  : ['api'],
        );

        /** @var TestParameter $restored */
        $restored = \unserialize(\serialize($parameter));

        self::assertSame('secret', $restored->value);
        self::assertSame('app.token', $restored->key, 'key set hook re-validates');
        self::assertSame(['api'], \array_values($restored->tags));
        self::assertTrue($restored->isSecret(SecretPolicy::SENSITIVE));
    }

    public function testParameterCredentialRoundTripWhenNotPublic(): void
    {
        $parameter = new TestParameter(
            key   : 'Db.Dsn',
            value : 'postgres://…',
            secret: SecretPolicy::CREDENTIAL,
            tags  : ['api'],
        );

        /** @var TestParameter $restored */
        $restored = \unserialize(\serialize($parameter));

        self::assertSame('postgres://…', $restored->value);
        self::assertTrue($restored->isSecret(SecretPolicy::CREDENTIAL));
        self::assertSame('[credential::string]', $restored->__debugInfo()['value']);
    }

    public function testParameterCredentialSerializeThrowsWhenPublic(): void
    {
        $this->becomePublic();

        $parameter = new TestParameter(
            key   : 'Db.Dsn',
            value : 'postgres://…',
            secret: SecretPolicy::CREDENTIAL,
            tags  : ['api'],
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

    public function testNestedCredentialThrowsWhenPublicWithoutExporter(): void
    {
        $this->becomePublic();

        $outer = new SerializerNestedCredentialFixture(
            new Value('runtime-only', SecretPolicy::CREDENTIAL),
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot serialize credential property $value');
        \serialize($outer);
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

    public static function roundTrip(string $plain, string $apiKey): self
    {
        $fixture         = new self();
        $fixture->plain  = $plain;
        $fixture->apiKey = $apiKey;

        $restored = \unserialize(\serialize($fixture));

        if ( ! $restored instanceof self ) {
            throw new \LogicException('Round-trip failed to restore ' . self::class);
        }

        return $restored;
    }
}

final class SerializerNestedCredentialFixture implements Serializable
{
    use Serializer;

    public function __construct(
        public Value $secret,
    ) {}
}
