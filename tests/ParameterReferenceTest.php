<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests;

use Northrook\Container\Secret;
use Northrook\Contracts\Tests\Support\RegistersTestContext;
use Northrook\Parameter;
use Northrook\Parameter\Secret as SecretPolicy;
use Northrook\Parameter\Type as ParameterType;
use Northrook\ParameterReference;
use PHPUnit\Framework\TestCase;

final class ParameterReferenceTest extends TestCase
{
    use RegistersTestContext;

    protected function setUp(): void
    {
        $this->setUpTestContext();
    }

    protected function tearDown(): void
    {
        $this->tearDownTestContext();
    }

    public function testInvokeFreezesAndBuildsParameter(): void
    {
        $reference = new ParameterReference(
            'App.Token',
            'secret',
            SecretPolicy::SENSITIVE,
            ['api'],
            ParameterType::Setting,
        );

        $parameter = $reference();

        self::assertTrue($reference->immutable);
        self::assertInstanceOf(Parameter::class, $parameter);
        self::assertSame('app.token', $parameter->key);
        self::assertSame('secret', $parameter->value);
        self::assertSame(ParameterType::Setting, $parameter->type);
        self::assertSame(SecretPolicy::SENSITIVE, $parameter->secret);
        self::assertTrue($parameter->isTagged('api'));
    }

    public function testSecretAttributeMergesConditionsIntoTags(): void
    {
        $reference = new ParameterReference(
            'db.dsn',
            'postgres://…',
            new Secret(SecretPolicy::CREDENTIAL, 'db-dsn', 'primary'),
        );

        self::assertSame(SecretPolicy::CREDENTIAL, $reference->secret);
        self::assertTrue($reference->hasTags('db-dsn', 'primary'));

        $parameter = $reference();
        self::assertTrue($parameter->isTagged('db-dsn', 'primary'));
    }

    public function testExportIsEvalableFrozenParameter(): void
    {
        $reference = new ParameterReference(
            'app.flag',
            true,
            null,
            [],
            ParameterType::Setting,
        );

        $exported = $reference->_export();
        /** @var Parameter $hydrated */
        $hydrated = eval('return ' . $exported);

        self::assertInstanceOf(Parameter::class, $hydrated);
        self::assertSame('app.flag', $hydrated->key);
        self::assertTrue($hydrated->value);
        self::assertSame(ParameterType::Setting, $hydrated->type);
        self::assertNull($hydrated->secret);
        self::assertTrue($reference->immutable, 'export freezes via __invoke');
    }
}
