<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests;

use Northrook\Container\Secret;
use Northrook\Context;
use Northrook\Parameter;
use Northrook\Parameter\Secret as SecretPolicy;
use Northrook\Parameter\Type as ParameterType;
use Northrook\ParameterStoreInterface;
use Northrook\Redactor;
use PHPUnit\Framework\TestCase;

final class ValueSecretTest extends TestCase
{
    public function testParameterAssignsKeyTypeAndTags(): void
    {
        $parameter = new Parameter(
            key   : 'app.token',
            value : 'secret',
            type  : ParameterType::Setting,
            secret: SecretPolicy::SENSITIVE,
            tags  : ['api' => 'api'],
        );

        self::assertSame('app.token', $parameter->key);
        self::assertSame('secret', $parameter->value);
        self::assertSame(ParameterType::Setting, $parameter->type);
        self::assertSame(SecretPolicy::SENSITIVE, $parameter->secret);
        self::assertTrue($parameter->isTagged('api'));
        self::assertSame(['api'], \array_values($parameter->tags));
    }

    public function testParameterAcceptsCredentialWithTags(): void
    {
        $parameter = new Parameter(
            key   : 'db.dsn',
            value : 'postgres://…',
            type  : ParameterType::Setting,
            secret: SecretPolicy::CREDENTIAL,
            tags  : ['db-dsn' => 'db-dsn'],
        );

        self::assertSame(SecretPolicy::CREDENTIAL, $parameter->secret);
        self::assertTrue($parameter->isTagged('db-dsn'));
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
            self::assertContains(Secret::class, $names);
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
        self::assertTrue(Context::isDebug());

        $redactor = new Redactor;

        self::assertSame('[secret::integer:6]', $redactor(123_456, SecretPolicy::SENSITIVE, []));
        self::assertSame('[secret::float:4]', $redactor(3.14, SecretPolicy::SENSITIVE, []));
        self::assertSame('[secret::bool:true]', $redactor(true, SecretPolicy::SENSITIVE, []));
    }
}
