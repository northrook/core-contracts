<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests;

use Northrook\Container\Secret;
use Northrook\Contracts\Tests\Support\RegistersTestContext;
use Northrook\Filesystem\Directory;
use Northrook\Filesystem\File;
use Northrook\Filesystem\Path;
use Northrook\InvalidArgumentException;
use Northrook\Parameter;
use Northrook\Parameter\Secret as SecretPolicy;
use Northrook\Parameter\Type as ParameterType;
use Northrook\ParameterDefinition;
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

    public function testGetParameterFreezesAndBuildsParameter(): void
    {
        $reference = new ParameterDefinition(
            'App.Token',
            'secret',
            SecretPolicy::SENSITIVE,
            ['api'],
            ParameterType::Setting,
        );

        $parameter = $reference->getParameter();

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
        $reference = new ParameterDefinition(
            'db.dsn',
            'postgres://…',
            new Secret(SecretPolicy::CREDENTIAL, 'db-dsn', 'primary'),
        );

        self::assertSame(SecretPolicy::CREDENTIAL, $reference->secret);
        self::assertTrue($reference->hasTags('db-dsn', 'primary'));

        $parameter = $reference->getParameter();
        self::assertTrue($parameter->isTagged('db-dsn', 'primary'));
    }

    public function testExportIsEvalableFrozenParameter(): void
    {
        $reference = new ParameterDefinition(
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
        self::assertTrue($reference->immutable, 'export freezes via getParameter');
    }

    public function testOmittingTypeResolvesFromValue(): void
    {
        $value = new ParameterDefinition('app.name', 'Contracts');
        self::assertSame(ParameterType::Value, $value->type);

        $path = new ParameterDefinition('app.path', new Path('var/cache'));
        self::assertSame(ParameterType::Path, $path->type);
        self::assertSame('var/cache', $path->value);

        $file = new ParameterDefinition('app.file', new File('app.ini'));
        self::assertSame(ParameterType::File, $file->type);
        self::assertSame('app.ini', $file->value);

        $directory = new ParameterDefinition('app.dir', new Directory('var/cache'));
        self::assertSame(ParameterType::Directory, $directory->type);
        self::assertSame('var/cache', $directory->value);
    }

    public function testStringableValueIsStoredAsString(): void
    {
        $reference = new ParameterDefinition(
            'app.label',
            new class implements \Stringable {
                public function __toString(): string
                {
                    return 'label';
                }
            },
        );

        self::assertSame(ParameterType::Value, $reference->type);
        self::assertSame('label', $reference->value);
    }

    public function testConstructorAllowsTypeMismatchUntilGetParameter(): void
    {
        $reference = new ParameterDefinition(
            'app.file',
            'var/cache',
            type: ParameterType::File,
        );

        self::assertSame(ParameterType::File, $reference->type);
        self::assertSame('var/cache', $reference->value);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid value for parameter type File');
        $reference->getParameter();
    }

    public function testValueValidateRejectsMismatch(): void
    {
        $reference = new ParameterDefinition(
            'app.file',
            'app.ini',
            type: ParameterType::File,
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid value for parameter type File');
        $reference->value('var/cache', validate: true);
    }

    public function testTypeValidateRechecksExistingValue(): void
    {
        $reference = new ParameterDefinition(
            'app.path',
            'var/cache',
            type: ParameterType::Path,
        );

        $reference->type(ParameterType::Directory);

        $this->expectException(InvalidArgumentException::class);
        $reference->type(ParameterType::File, validate: true);
    }
}
