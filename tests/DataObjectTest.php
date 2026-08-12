<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests;

use Northrook\AppEnv;
use Northrook\Contracts;
use Northrook\Contracts\AppEnvironment;
use Northrook\Contracts\AssetType;
use Northrook\Contracts\DataObject;
use Northrook\Contracts\RuntimeException;
use Northrook\Contracts\Secret;
use Northrook\Contracts\Singleton;
use Northrook\Contracts\Tests\Support\DataObjectSecretFixture;
use Northrook\Contracts\Timestamp;
use Northrook\Contracts\Value;
use Northrook\Contracts\Value\Redactor;
use Northrook\Contracts\Value\Secret as SecretPolicy;
use PHPUnit\Framework\TestCase;

final class DataObjectTest extends TestCase
{
    protected function setUp(): void
    {
        $this->resetAppEnv();
    }

    protected function tearDown(): void
    {
        $this->resetAppEnv();
    }

    public function testJsonSerializeCastsSensitivePropertiesToPlaintext(): void
    {
        $dto = new DataObjectSecretFixture(
            secretTimestamp : new Timestamp(1_700_000_000_005),
            secretEnum      : AssetType::Style,
            visibleTimestamp: new Timestamp(1_700_000_000_005),
            visibleEnum     : AssetType::Script,
        );

        // Serialize / JSON channel: sensitive is allowed — plaintext with casts.
        $json = $dto->jsonSerialize();

        self::assertSame('1700000000005', $json['secretTimestamp']);
        self::assertSame('style', $json['secretEnum']);
        self::assertSame('1700000000005', $json['visibleTimestamp']);
        self::assertSame('script', $json['visibleEnum']);

        // Debug channel: any secret tier is masked.
        $debug = $dto->__debugInfo();

        self::assertSame('[sensitive::object]', $debug['secretTimestamp']);
        self::assertSame('[sensitive::object]', $debug['secretEnum']);
    }

    public function testSensitivePropertySerializesPlaintextDebugMasks(): void
    {
        $dto = new DataObjectSecretTypesFixture(
            apiKey : 'secret123',
            retries: 3,
        );

        $json = $dto->jsonSerialize();

        self::assertSame('secret123', $json['apiKey']);
        self::assertSame(3, $json['retries']);
        self::assertSame('[sensitive::string]', $dto->__debugInfo()['apiKey']);
    }

    public function testSensitiveIntegerDebugMaskDescribesType(): void
    {
        $dto = new DataObjectSecretTokenFixture(token: 123_456);

        self::assertSame(123_456, $dto->jsonSerialize()['token']);
        self::assertSame('[sensitive::integer]', $dto->__debugInfo()['token']);
    }

    public function testToStringMatchesCompactJsonString(): void
    {
        $dto = new DataObjectSecretFixture(
            secretTimestamp : new Timestamp(1_700_000_000_005),
            secretEnum      : AssetType::Style,
            visibleTimestamp: new Timestamp(1_700_000_000_005),
            visibleEnum     : AssetType::Script,
        );

        $expected = '{"secretTimestamp":"1700000000005","secretEnum":"style",' . '"visibleTimestamp":"1700000000005","visibleEnum":"script"}';

        self::assertSame($expected, $dto->jsonString());
        self::assertSame($expected, (string) $dto);
    }

    public function testJsonStringPrettyAndFormatter(): void
    {
        $dto = new DataObjectSecretTypesFixture(
            apiKey : 'ab',
            retries: 1,
        );

        $pretty = $dto->jsonString(pretty: true);

        self::assertStringContainsString("\n", $pretty);
        self::assertSame(
            '<' . $dto->jsonString() . '>',
            $dto->jsonString(formatter: static fn(string $json): string => "<{$json}>"),
        );
    }

    public function testSensitiveParameterOnPromotedMasksStringsInDebug(): void
    {
        $dto = new DataObjectSensitiveFixture(
            password: 'hunter2',
            label   : 'ok',
        );

        $json = $dto->jsonSerialize();

        self::assertSame('hunter2', $json['password']);
        self::assertSame('ok', $json['label']);
        self::assertSame('[sensitive::' . \SensitiveParameter::class . ']', $dto->__debugInfo()['password']);
    }

    public function testSecretOnConstructorParameterMasksPropertyInDebug(): void
    {
        $dto = new DataObjectParamSecretFixture(token: 'abc');

        self::assertSame('abc', $dto->jsonSerialize()['token']);
        self::assertSame('[sensitive::string]', $dto->__debugInfo()['token']);
    }

    public function testDuplicateAttributeOnPropertyAndParameterThrows(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('declared on both');

        new DataObjectDuplicateSecretFixture(token: 'x')->jsonSerialize();
    }

    public function testCredentialAttributeSerializesWhenNotPublic(): void
    {
        $dto = new DataObjectCredentialFixture(dsn: 'postgres://secret');

        self::assertSame('[credential::string]', $dto->__debugInfo()['dsn']);
        self::assertSame('postgres://secret', $dto->jsonSerialize()['dsn']);
    }

    public function testCredentialAttributeThrowsOnSerializeWhenPublic(): void
    {
        $this->becomePublic();

        $dto = new DataObjectCredentialFixture(dsn: 'postgres://secret');

        self::assertSame('[credential::string]', $dto->__debugInfo()['dsn']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot serialize credential property $dsn');

        $dto->jsonSerialize();
    }

    public function testCustomRedactorReceivesConditionFromAttribute(): void
    {
        $this->resetContracts();

        try {
            Contracts::register(
                rootDirectory: __DIR__ . '/..',
                secretRedactor: new class() extends Redactor {
                    protected function redact(
                        mixed $value,
                    ): mixed {
                        $condition = \array_values($this->secret->conditions)[0] ?? null;

                        return "{$this->secret->type}:{$condition}:" . \gettype($value);
                    }
                },
            );

            $dto = new DataObjectConditionSecretFixture(token: 'abc');

            // Custom redactor drives the debug channel; public wire refuses credentials.
            self::assertSame('credential:oauth-token:string', $dto->__debugInfo()['token']);

            $this->becomePublic();

            try {
                $dto->jsonSerialize();
                self::fail('Expected RuntimeException for credential serialize');
            } catch (RuntimeException $exception) {
                self::assertStringContainsString(
                    'Cannot serialize credential property $token',
                    $exception->getMessage(),
                );
            }
        } finally {
            $this->resetContracts();
        }
    }

    public function testSensitiveValuePropertySerializesPlaintextDebugMasks(): void
    {
        $dto = new DataObjectSecretInstanceFixture(
            token: new Value('LEAKED', SecretPolicy::SENSITIVE),
            label: 'ok',
        );

        $json = $dto->jsonSerialize();

        // Nested Value self-serializes; sensitive payloads may leave via JSON by policy.
        self::assertInstanceOf(Value::class, $json['token']);
        self::assertSame('ok', $json['label']);
        self::assertStringContainsString('LEAKED', $dto->jsonString());

        // Debug channel masks through the nested Value's own __debugInfo.
        $debug = $dto->__debugInfo();
        self::assertInstanceOf(Value::class, $debug['token']);
        self::assertSame('[sensitive::string]', $debug['token']->__debugInfo()['value']);
    }

    public function testCredentialValuePropertySerializesWhenNotPublic(): void
    {
        $dto = new DataObjectSecretInstanceFixture(
            token: new Value('LEAKED', SecretPolicy::CREDENTIAL),
            label: 'ok',
        );

        $json = $dto->jsonSerialize();
        self::assertInstanceOf(Value::class, $json['token']);
        self::assertStringContainsString('LEAKED', $dto->jsonString());
        self::assertSame('[credential::string]', $json['token']->__debugInfo()['value']);
    }

    public function testCredentialValuePropertyThrowsOnSerializeWhenPublic(): void
    {
        $this->becomePublic();

        $dto = new DataObjectSecretInstanceFixture(
            token: new Value('LEAKED', SecretPolicy::CREDENTIAL),
            label: 'ok',
        );

        // Parent walk returns the nested Value as-is; nested jsonSerialize throws when public.
        $json = $dto->jsonSerialize();
        self::assertInstanceOf(Value::class, $json['token']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot serialize credential property $value');

        $dto->jsonString();
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
    }

    private function resetContracts(): void
    {
        $property = new \ReflectionProperty(Singleton::class, '__instance');
        $property->setValue(null, []);
    }
}

final readonly class DataObjectSecretTypesFixture extends DataObject
{
    public function __construct(
        #[Secret]
        public string $apiKey,
        public int    $retries,
    ) {
        parent::__construct();
    }
}

final readonly class DataObjectSecretTokenFixture extends DataObject
{
    public function __construct(
        #[Secret]
        public int $token,
    ) {
        parent::__construct();
    }
}

final readonly class DataObjectSensitiveFixture extends DataObject
{
    public function __construct(
        #[\SensitiveParameter]
        public string $password,
        public string $label,
    ) {
        parent::__construct();
    }
}

final readonly class DataObjectParamSecretFixture extends DataObject
{
    public string $token;

    public function __construct(
        #[Secret]
        string $token,
    ) {
        $this->token = $token;
        parent::__construct();
    }
}

final readonly class DataObjectDuplicateSecretFixture extends DataObject
{
    #[Secret]
    public string $token;

    public function __construct(
        #[Secret]
        string $token,
    ) {
        $this->token = $token;
        parent::__construct();
    }
}

final readonly class DataObjectCredentialFixture extends DataObject
{
    public function __construct(
        #[Secret(type: SecretPolicy::CREDENTIAL)]
        public string $dsn,
    ) {
        parent::__construct();
    }
}

final readonly class DataObjectConditionSecretFixture extends DataObject
{
    public function __construct(
        #[Secret(SecretPolicy::CREDENTIAL, 'oauth-token')]
        public string $token,
    ) {
        parent::__construct();
    }
}

final readonly class DataObjectSecretInstanceFixture extends DataObject
{
    public function __construct(
        public Value  $token,
        public string $label,
    ) {
        parent::__construct();
    }
}
