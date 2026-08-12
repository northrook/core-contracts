<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests;

use Northrook\AppEnv;
use Northrook\Contracts\AppEnvironment;
use Northrook\Contracts\Parameter\Type as ParameterType;
use Northrook\Contracts\ParameterStoreInterface;
use Northrook\Contracts\Tests\Support\TestParameter;
use Northrook\Contracts\Value;
use Northrook\Contracts\Value\Redactor;
use Northrook\Contracts\Value\Secret as SecretPolicy;
use PHPUnit\Framework\TestCase;

final class ValueSecretTest extends TestCase
{
    public function testValueStoresSecretPolicy(): void
    {
        $value = new Value('hunter2', SecretPolicy::SENSITIVE);

        self::assertSame('hunter2', $value->value);
        self::assertTrue($value->isSecret());
        self::assertTrue($value->isSecret(SecretPolicy::SENSITIVE));
        self::assertFalse($value->isSecret(SecretPolicy::CREDENTIAL));
        self::assertSame(SecretPolicy::SENSITIVE, $value->secret?->type);
    }

    public function testValueWithoutSecret(): void
    {
        $value = new Value('plain');

        self::assertFalse($value->isSecret());
        self::assertNull($value->secret);
    }

    public function testValueFromSecretInstance(): void
    {
        $policy = new SecretPolicy(SecretPolicy::CREDENTIAL, 'db-dsn');
        $value = new Value('postgres://…', $policy);

        self::assertTrue($value->isSecret(SecretPolicy::CREDENTIAL));
        self::assertTrue($value->secret?->hasCondition('db-dsn'));
    }

    public function testParameterAssignsKeyTypeAndTags(): void
    {
        $parameter = new TestParameter(
            key   : 'App.Token',
            value : 'secret',
            secret: SecretPolicy::SENSITIVE,
            tags  : ['api'],
        );

        self::assertSame('app.token', $parameter->key);
        self::assertSame('secret', $parameter->value);
        self::assertSame(ParameterType::String, $parameter->type);
        self::assertTrue($parameter->isSecret(SecretPolicy::SENSITIVE));
        self::assertTrue($parameter->isTagged('api'));
        self::assertSame(['api'], \array_values($parameter->tags));
    }

    public function testParameterAcceptsSecretPolicyInstance(): void
    {
        $secret = new SecretPolicy(SecretPolicy::CREDENTIAL, ['db-dsn']);
        $parameter = new TestParameter(
            key   : 'db.dsn',
            value : 'postgres://…',
            secret: $secret,
        );

        self::assertTrue($parameter->isSecret(SecretPolicy::CREDENTIAL));
        self::assertTrue($parameter->secret?->hasCondition('db-dsn'));
    }

    public function testParameterStoreAddSetAcceptSecretPolicy(): void
    {
        foreach (['add', 'set'] as $method) {
            $parameter = new \ReflectionParameter(
                [ParameterStoreInterface::class, $method],
                'secret',
            );
            $type = $parameter->getType();
            self::assertInstanceOf(\ReflectionUnionType::class, $type);

            $names = \array_map(
                static fn(\ReflectionType $part): string => (string) $part,
                $type->getTypes(),
            );
            self::assertContains('string', $names);
            self::assertContains(SecretPolicy::class, $names);
            self::assertContains('null', $names);
        }

        $set = new \ReflectionMethod(ParameterStoreInterface::class, 'set');
        self::assertSame(
            ['key', 'value', 'secret', 'tag'],
            \array_map(
                static fn(\ReflectionParameter $parameter): string => $parameter->getName(),
                $set->getParameters(),
            ),
        );
    }

    public function testDebugRedactionDoesNotEmbedScalarPayload(): void
    {
        $property = new \ReflectionProperty(AppEnv::class, 'instance');
        $property->setValue(null, null);

        try {
            new AppEnv(AppEnvironment::Development, debug: true);
            self::assertTrue(AppEnv::isDebug());

            $redactor = new Redactor;
            $policy = new SecretPolicy(SecretPolicy::SENSITIVE);

            self::assertSame('[sensitive::integer:6]', $redactor(123_456, $policy));
            self::assertSame('[sensitive::float:4]', $redactor(3.14, $policy));
            self::assertSame('[sensitive::bool:true]', $redactor(true, $policy));
            self::assertSame('[sensitive::string:7]', $redactor('hunter2', $policy));
        } finally {
            $property->setValue(null, null);
        }
    }
}
