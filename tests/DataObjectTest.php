<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests;

use Northrook\Contracts;
use Northrook\Contracts\AssetType;
use Northrook\Contracts\DataObject;
use Northrook\Contracts\Redactor;
use Northrook\Contracts\RuntimeException;
use Northrook\Contracts\Secret;
use Northrook\Contracts\Singleton;
use Northrook\Contracts\Tests\Support\DataObjectSecretFixture;
use Northrook\Contracts\Timestamp;
use PHPUnit\Framework\TestCase;

final class DataObjectTest extends TestCase
{
    public function testJsonSerializeKeepsSecretMaskOverTimestampAndEnum(): void
    {
        $dto = new DataObjectSecretFixture(
            secretTimestamp : new Timestamp(1_700_000_000_005),
            secretEnum      : AssetType::Style,
            visibleTimestamp: new Timestamp(1_700_000_000_005),
            visibleEnum     : AssetType::Script,
        );

        $json = $dto->jsonSerialize();

        self::assertSame('[Secret::object]', $json['secretTimestamp']);
        self::assertSame('[Secret::object]', $json['secretEnum']);
        self::assertSame('1700000000005', $json['visibleTimestamp']);
        self::assertSame('script', $json['visibleEnum']);
    }

    public function testSecretMaskDescribesValueType(): void
    {
        $dto = new DataObjectSecretTypesFixture(
            apiKey : 'secret123',
            retries: 3,
        );

        $json = $dto->jsonSerialize();

        self::assertSame('[Secret::string]', $json['apiKey']);
        self::assertSame(3, $json['retries']);
    }

    public function testSecretMaskDescribesIntegerType(): void
    {
        $dto = new DataObjectSecretTokenFixture(token: 123_456);

        self::assertSame('[Secret::integer]', $dto->jsonSerialize()['token']);
    }

    public function testToStringMatchesCompactJsonString(): void
    {
        $dto = new DataObjectSecretFixture(
            secretTimestamp : new Timestamp(1_700_000_000_005),
            secretEnum      : AssetType::Style,
            visibleTimestamp: new Timestamp(1_700_000_000_005),
            visibleEnum     : AssetType::Script,
        );

        $expected =
            '{"secretTimestamp":"[Secret::object]","secretEnum":"[Secret::object]",'
            . '"visibleTimestamp":"1700000000005","visibleEnum":"script"}';

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

    public function testNonFinalDataObjectThrowsOnSerialize(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('must be `final`');

        new DataObjectNonFinalFixture()->jsonSerialize();
    }

    public function testSensitiveParameterOnPromotedMasksStrings(): void
    {
        $dto = new DataObjectSensitiveFixture(
            password: 'hunter2',
            label   : 'ok',
        );

        $json = $dto->jsonSerialize();

        self::assertSame('*******', $json['password']);
        self::assertSame('ok', $json['label']);
    }

    public function testSecretOnConstructorParameterMasksProperty(): void
    {
        $dto = new DataObjectParamSecretFixture(token: 'abc');

        self::assertSame('[Secret::string]', $dto->jsonSerialize()['token']);
    }

    public function testDuplicateAttributeOnPropertyAndParameterThrows(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('declared on both');

        new DataObjectDuplicateSecretFixture(token: 'x')->jsonSerialize();
    }

    public function testCredentialAttributeUsesCredentialPlaceholder(): void
    {
        $dto = new DataObjectCredentialFixture(dsn: 'postgres://secret');

        self::assertSame('[Credential::string]', $dto->jsonSerialize()['dsn']);
    }

    public function testCustomRedactorReceivesConditionFromAttribute(): void
    {
        $this->resetContracts();

        try {
            Contracts::register(
                rootDirectory: __DIR__ . '/..',
                secretRedactor: new class() extends Redactor {
                    protected function redact(
                        mixed       $value,
                        string      $type,
                        null|string $condition = null,
                    ): string {
                        return "{$type}:{$condition}:" . \gettype($value);
                    }
                },
            );

            $dto = new DataObjectConditionSecretFixture(token: 'abc');

            self::assertSame('credential:oauth-token:string', $dto->jsonSerialize()['token']);
        } finally {
            $this->resetContracts();
        }
    }

    public function testSecretInstancePropertyIsRedacted(): void
    {
        $dto = new DataObjectSecretInstanceFixture(
            token: new Secret('LEAKED'),
            label: 'ok',
        );

        $json = $dto->jsonSerialize();

        self::assertSame('[Secret::string]', $json['token']);
        self::assertSame('ok', $json['label']);
        self::assertStringNotContainsString('LEAKED', $dto->jsonString());
    }

    public function testCredentialSecretInstanceUsesCredentialPlaceholder(): void
    {
        $dto = new DataObjectSecretInstanceFixture(
            token: new Secret('LEAKED', type: Secret::CREDENTIAL),
            label: 'ok',
        );

        self::assertSame('[Credential::string]', $dto->jsonSerialize()['token']);
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

readonly class DataObjectNonFinalFixture extends DataObject
{
    public function __construct(
        public string $name = 'x',
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
        #[Secret(type: Secret::CREDENTIAL)]
        public string $dsn,
    ) {
        parent::__construct();
    }
}

final readonly class DataObjectConditionSecretFixture extends DataObject
{
    public function __construct(
        #[Secret(
            type     : Secret::CREDENTIAL,
            condition: 'oauth-token',
        )]
        public string $token,
    ) {
        parent::__construct();
    }
}

final readonly class DataObjectSecretInstanceFixture extends DataObject
{
    public function __construct(
        public Secret $token,
        public string $label,
    ) {
        parent::__construct();
    }
}
