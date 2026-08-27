<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests;

use Northrook\Assets\AssetType;
use Northrook\Container\Secret;
use Northrook\Context;
use Northrook\Context\AppEnv;
use Northrook\Context\ContextManager;
use Northrook\Contracts\Tests\Support\DataObjectSecretFixture;
use Northrook\Contracts\Tests\Support\ResetsContext;
use Northrook\Contracts\Tests\Support\SecretMask;
use Northrook\DataObject;
use Northrook\Kernel\KernelContext;
use Northrook\Parameter\Secret as SecretPolicy;
use Northrook\RuntimeException;
use Northrook\Timestamp;
use PHPUnit\Framework\TestCase;

final class DataObjectTest extends TestCase
{
    private ContextManager $contextManager;

    protected function setUp(): void
    {
        $this->resetSingleton();

        $this->contextManager = new ContextManager;
        Context::register(
            appEnv        : AppEnv::Testing,
            contextManager: $this->contextManager,
        );
    }

    protected function tearDown(): void
    {
        $this->resetSingleton();
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

        self::assertSame(SecretMask::sensitive($dto->secretTimestamp), $debug['secretTimestamp']);
        self::assertSame(SecretMask::sensitive($dto->secretEnum), $debug['secretEnum']);
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
        self::assertSame(SecretMask::sensitive('secret123'), $dto->__debugInfo()['apiKey']);
    }

    public function testSensitiveIntegerDebugMaskDescribesType(): void
    {
        $dto = new DataObjectSecretTokenFixture(token: 123_456);

        self::assertSame(123_456, $dto->jsonSerialize()['token']);
        self::assertSame(SecretMask::sensitive(123_456), $dto->__debugInfo()['token']);
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
        self::assertSame('[secret::' . \SensitiveParameter::class . ']', $dto->__debugInfo()['password']);
    }

    public function testSecretOnConstructorParameterMasksPropertyInDebug(): void
    {
        $dto = new DataObjectParamSecretFixture(token: 'abc');

        self::assertSame('abc', $dto->jsonSerialize()['token']);
        self::assertSame(SecretMask::sensitive('abc'), $dto->__debugInfo()['token']);
    }

    public function testDuplicateAttributeOnPropertyAndParameterThrows(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('declared on both');

        new DataObjectDuplicateSecretFixture(token: 'x')->jsonSerialize();
    }

    public function testCredentialAttributeSerializesOutsideHttpContext(): void
    {
        $dto = new DataObjectCredentialFixture(dsn: 'postgres://secret');

        self::assertSame('[secret::credential]', $dto->__debugInfo()['dsn']);
        self::assertSame('postgres://secret', $dto->jsonSerialize()['dsn']);
    }

    public function testCredentialAttributeThrowsOnSerializeInHttpContext(): void
    {
        $this->becomeOutbound();

        $dto = new DataObjectCredentialFixture(dsn: 'postgres://secret');

        self::assertSame('[secret::credential]', $dto->__debugInfo()['dsn']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot serialize credential property $dsn');

        $dto->jsonSerialize();
    }

    private function becomeOutbound(): void
    {
        $this->contextManager->update(KernelContext::Request);
    }

    private function resetSingleton(): void
    {
        ResetsContext::reset();
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
