<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests;

use Northrook\Callback;
use Northrook\Container\Service\Tag;
use Northrook\Export;
use Northrook\Export\Exporter;
use Northrook\InvalidArgumentException;
use Northrook\Order;
use Northrook\Parameter;
use Northrook\Parameter\Secret;
use Northrook\Parameter\Type;
use Northrook\RuntimeException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ExportTest extends TestCase
{
    protected function tearDown(): void
    {
        Export::reset();
    }

    private static function useExporter(
        Exporter $exporter,
    ): void {
        new \ReflectionProperty(Export::class, 'exporter')->setValue(null, $exporter);
    }

    /**
     * @return list<array{0: \Northrook\Export\Exporter}>
     */
    public static function provideObjectExporters(): array
    {
        return [
            [Exporter::Symfony],
            [Exporter::Polyfill],
            [Exporter::Reflection],
        ];
    }

    /**
     * @return list<array{0: mixed, 1: string}>
     */
    public static function provideScalarDumps(): array
    {
        return [
            [true, 'true'],
            [false, 'false'],
            [null, 'null'],
            ['', "''"],
            [[], '[]'],
            [0, '0'],
            [1, '1'],
            [-7, '-7'],
            [\PHP_INT_MAX, (string) \PHP_INT_MAX],
            [0.0, '0.0'],
            [-0.0, '-0.0'],
            [1.5, '1.5'],
            [1.0, '1.0'],
        ];
    }

    private static function evalDump(
        string $dump,
    ): mixed {
        $dump = \rtrim($dump);

        return eval('return ' . ( \str_ends_with($dump, ';') ? $dump : $dump . ';' ));
    }

    public function testArrayIndentsNestedMaps(): void
    {
        $export = Export::array([
            'app' => [
                'name' => 'contracts',
                'bag'  => [
                    'list' => ['alpha', 'beta'],
                ],
            ],
        ]);

        self::assertSame(
            <<<'PHP'
                [
                    'app' => [
                        'name' => 'contracts',
                        'bag'  => [
                            'list' => [
                                0 => 'alpha',
                                1 => 'beta',
                            ],
                        ],
                    ],
                ]
                PHP,
            $export,
        );
        self::assertSame(
            [
                'app' => [
                    'name' => 'contracts',
                    'bag'  => [
                        'list' => ['alpha', 'beta'],
                    ],
                ],
            ],
            eval('return ' . $export . ';'),
        );
    }

    public function testEmptyArrayIsCompact(): void
    {
        self::assertSame('[]', Export::array([]));
        self::assertSame(
            <<<'PHP'
                [
                    'empty' => [],
                ]
                PHP,
            Export::array(['empty' => []]),
        );
    }

    public function testNestedExportableOmitsStatementTerminator(): void
    {
        $parameter = new Parameter(
            'app.name',
            'contracts',
            Type::Setting,
            null,
            ['core' => 'core'],
        );

        $export = Export::array(['name' => $parameter]);

        self::assertSame(
            <<<'PHP'
                [
                    'name' => new \Northrook\Parameter(
                        'app.name',
                        'contracts',
                        \Northrook\Parameter\Type::Setting,
                        null,
                        [
                            'core' => 'core',
                        ],
                    ),
                ]
                PHP,
            $export,
        );
        self::assertStringNotContainsString(');', $export);

        $hydrated = eval('return ' . $export . ';');
        self::assertInstanceOf(Parameter::class, $hydrated['name']);
        self::assertSame('contracts', $hydrated['name']->value);
    }

    public function testClassExportRemainsEvalableAtRoot(): void
    {
        $export = Export::class(Parameter::class, 'k', 1, Type::Value, null, []);

        self::assertStringEndsWith(");\n", $export);
        self::assertSame(
            <<<'PHP'
                new \Northrook\Parameter(
                    'k',
                    1,
                    \Northrook\Parameter\Type::Value,
                    null,
                    [],
                );

                PHP,
            $export,
        );

        $hydrated = eval('return ' . $export);
        self::assertInstanceOf(Parameter::class, $hydrated);
        self::assertSame(1, $hydrated->value);
    }

    public function testClassExportEmitsNamedArguments(): void
    {
        $export = Export::class(Tag::class, 'role.logger', handler: 'stream', priority: 2);

        self::assertSame(
            <<<'PHP'
                new \Northrook\Container\Service\Tag(
                    'role.logger',
                    handler: 'stream',
                    priority: 2,
                );

                PHP,
            $export,
        );

        $hydrated = self::evalDump($export);
        self::assertInstanceOf(Tag::class, $hydrated);
        self::assertSame('role.logger', $hydrated->reference);
        self::assertSame(['handler' => 'stream', 'priority' => 2], $hydrated->arguments);
    }

    public function testClassExportRejectsInvalidNamedArgumentIdentifiers(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Named export arguments must be valid PHP identifiers.');

        $arguments = ['bad-key' => 'value'];
        Export::class(Tag::class, 'role.logger', ...$arguments);
    }

    public function testExportableTagRoundTripsNamedArguments(): void
    {
        $tag      = new Tag('role.logger', handler: 'stream', priority: 2);
        $exported = $tag->_export();

        self::assertStringContainsString("handler: 'stream'", $exported);
        self::assertStringContainsString('priority: 2', $exported);

        $hydrated = self::evalDump($exported);
        self::assertInstanceOf(Tag::class, $hydrated);
        self::assertSame($tag->reference, $hydrated->reference);
        self::assertSame($tag->arguments, $hydrated->arguments);
    }

    public function testCallExportRemainsEvalableAtRoot(): void
    {
        $export = Export::call(Callback::class, 'restore', 'strlen', []);

        self::assertStringEndsWith(");\n", $export);
        self::assertSame(
            <<<'PHP'
                \Northrook\Callback::restore(
                    'strlen',
                    [],
                );

                PHP,
            $export,
        );

        $hydrated = self::evalDump($export);
        self::assertInstanceOf(Callback::class, $hydrated);
        self::assertSame(5, $hydrated('hello'));
    }

    public function testCallExportEmitsNamedArguments(): void
    {
        $export = Export::call(
            Callback::class,
            'restore',
            descriptor: 'strlen',
            arguments: [],
        );

        self::assertSame(
            <<<'PHP'
                \Northrook\Callback::restore(
                    descriptor: 'strlen',
                    arguments: [],
                );

                PHP,
            $export,
        );

        $hydrated = self::evalDump($export);
        self::assertInstanceOf(Callback::class, $hydrated);
        self::assertSame(4, $hydrated('abcd'));
    }

    public function testCallExportRejectsInvalidMethodIdentifiers(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Export::call method must be a valid PHP identifier.');

        Export::call(Callback::class, 'bad-method');
    }

    public function testExportableCallbackRoundTripsViaRestore(): void
    {
        $callback = Callback::staticMethod(\DateTimeImmutable::class, 'createFromFormat', 'Y-m-d');
        $exported = $callback->_export();

        self::assertStringStartsWith('\\Northrook\\Callback::restore(', $exported);

        $hydrated = self::evalDump($exported);
        self::assertInstanceOf(Callback::class, $hydrated);
        self::assertInstanceOf(\DateTimeImmutable::class, $hydrated('2026-08-25'));
    }

    public function testNestedCallOmitsStatementTerminator(): void
    {
        $export = Export::array([
            'cb' => Callback::function(
                'strlen',
            ),
        ]);

        self::assertStringNotContainsString(");\n", $export);
        self::assertStringContainsString('::restore(', $export);

        $hydrated = self::evalDump($export);
        self::assertInstanceOf(Callback::class, $hydrated['cb']);
        self::assertSame(3, $hydrated['cb']('abc'));
    }

    public function testConstEmitsTheGivenSource(): void
    {
        $export = Export::array([
            'env'   => Export::const('APP_ENV'),
            'class' => Export::const('\\__CLASS__'),
            'halt'  => Export::const('__COMPILER_HALT_OFFSET__'),
            'case'  => Export::const('\\' . Type::class . '::Setting'),
            'type'  => Export::const('\\' . Parameter::class . '::class'),
        ]);

        self::assertSame(
            <<<'PHP'
                [
                    'env'   => \APP_ENV,
                    'class' => __CLASS__,
                    'halt'  => __COMPILER_HALT_OFFSET__,
                    'case'  => \Northrook\Parameter\Type::Setting,
                    'type'  => \Northrook\Parameter::class,
                ]
                PHP,
            $export,
        );
    }

    /**
     * @return list<array{0: string, 1: string}>
     */
    public static function provideStringDumps(): array
    {
        return [
            ['',                          "''"],
            ['hello',                     "'hello'"],
            ["o'clock",                   "'o\\'clock'"],
            ['c:\\tmp',                   "'c:\\\\tmp'"],
            ["line\nbreak",               "'line'.\"\\n\".'break'"],
            ["\n",                        '"\\n"'],
            ["foo\n",                     "'foo'.\"\\n\""],
            ["\nfoo",                     '"\\n".\'foo\''],
            ["a\0b",                      "'a'.\"\\0\".'b'"],
            ["\0",                        '"\\0"'],
            ["a\r\nb",                    "'a'.\"\\r\".\"\\n\".'b'"],
            ["a\n\nb",                    "'a'.\"\\n\".\"\\n\".'b'"],
            ["abc\u{202E}cba",            "'abc'.\"\\u{202E}\".'cba'"],
            ["line\nbreak\r\ttab\\slash", "'line'.\"\\n\".'break'.\"\\r\".'\ttab\\\\slash'"],
        ];
    }

    #[DataProvider('provideStringDumps')]
    public function testStringDumpAndHydration(
        string $value,
        string $dump,
    ): void {
        self::assertSame($dump, Export::string($value));
        self::assertSame($dump, Export::value($value));
        self::assertSame($value, self::evalDump($dump));
        self::assertStringNotContainsString("\0", $dump);
        self::assertStringNotContainsString("\r", $dump);
        self::assertStringNotContainsString("\n", $dump);
    }

    public function testStringEscapesQuotesAndBackslashes(): void
    {
        self::assertSame(
            <<<'PHP'
                [
                    'o\'clock' => 'c:\\tmp',
                ]
                PHP,
            Export::array(["o'clock" => 'c:\\tmp']),
        );
        self::assertSame(
            <<<'PHP'
                [
                    'note' => 'line'."\n".'break',
                ]
                PHP,
            Export::array(['note' => "line\nbreak"]),
        );
    }

    public function testValueEmitsPhpLiterals(): void
    {
        self::assertSame('true', Export::value(true));
        self::assertSame('false', Export::value(false));
        self::assertSame('null', Export::value(null));
        self::assertSame("''", Export::value(''));
        self::assertSame('[]', Export::value([]));
        self::assertSame('1.5', Export::value(1.5));
    }

    public function testValueRejectsNonArrayPayload(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot export value of type stdClass as payload');

        Export::value(new \stdClass, true);
    }

    public function testValueRejectsEnumPayload(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot export value of type Northrook\Parameter\Type as payload');

        Export::value(Type::Setting, true);
    }

    public function testObjectExportPreservesPublicPromotedState(): void
    {
        $dto = new ExportPublicDtoFixture('ok', 2);

        $export = Export::object($dto);

        $hydrated = eval('return ' . $export . ';');
        self::assertInstanceOf(ExportPublicDtoFixture::class, $hydrated);
        self::assertSame('ok', $hydrated->name);
        self::assertSame(2, $hydrated->count);
    }

    public function testNestedObjectExportOmitsStatementTerminator(): void
    {
        $export = Export::array([
            'dto' => new ExportPublicDtoFixture('nested', 1),
        ]);

        self::assertStringNotContainsString(');', $export);

        $hydrated = eval('return ' . $export . ';');
        self::assertInstanceOf(ExportPublicDtoFixture::class, $hydrated['dto']);
        self::assertSame('nested', $hydrated['dto']->name);
    }

    public function testObjectFallsBackWhenPromotionIsIncomplete(): void
    {
        $dto       = new \ReflectionClass(ExportPublicDtoFixture::class)->newInstanceWithoutConstructor();
        $dto->name = 'partial';

        $export   = Export::object($dto);
        $hydrated = eval('return ' . $export . ';');

        self::assertInstanceOf(ExportPublicDtoFixture::class, $hydrated);
        self::assertSame('partial', $hydrated->name);
    }

    public function testObjectExportPreservesParentPrivateState(): void
    {
        $child = new \ReflectionClass(ExportRestoreChildFixture::class)->newInstanceWithoutConstructor();
        new \ReflectionProperty(ExportRestoreChildFixture::class, 'label')->setValue($child, 'child');
        new \ReflectionProperty(ExportRestoreParentFixture::class, 'origin')->setValue($child, 'parent');

        $hydrated = eval('return ' . Export::object($child) . ';');

        self::assertInstanceOf(ExportRestoreChildFixture::class, $hydrated);
        self::assertSame('child', $hydrated->label());
        self::assertSame('parent', $hydrated->origin());
    }

    public function testInstantiateHydratesPrivateState(): void
    {
        $restored = \instantiate(ExportRestoreFixture::class, ['label' => 'kept']);

        self::assertInstanceOf(ExportRestoreFixture::class, $restored);
        self::assertSame('kept', $restored->label());
    }

    public function testInstantiateHydratesParentPrivateState(): void
    {
        $restored = \instantiate(ExportRestoreChildFixture::class, [
            'label'  => 'child',
            'origin' => 'parent',
        ]);

        self::assertSame('child', $restored->label());
        self::assertSame('parent', $restored->origin());
    }

    public function testInstantiateChildPrivateWinsOverParentSameName(): void
    {
        $restored = \instantiate(ExportShadowChildFixture::class, [
            'label' => 'child-only',
        ]);

        self::assertSame('child-only', $restored->childLabel());
        $this->expectException(\Error::class);
        (void) $restored->parentLabel();
    }

    public function testInstantiateThrowsOnUnknownProperty(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to restore');

        \instantiate(ExportRestoreFixture::class, ['missing' => 'x']);
    }

    public function testObjectThrowsWhenBackendCannotExport(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to export');

        Export::object(new class('x') {
            public function __construct(
                public string $value,
            ) {}
        });
    }

    public function testFailsafeFallsBackToReflectionState(): void
    {
        Export::setFailsafe();

        $path     = \sys_get_temp_dir() . '/nr-export-failsafe-' . \bin2hex(\random_bytes(4)) . '.log';
        $previous = \ini_get('error_log');
        \ini_set('error_log', $path);

        try {
            $export = Export::object(new \ReflectionClass(\stdClass::class));
        }
        finally {
            if (\is_string($previous)) {
                \ini_set('error_log', $previous);
            }
            if (\is_file($path)) {
                \unlink($path);
            }
        }

        self::assertStringStartsWith('\\instantiate(', $export);
    }

    #[DataProvider('provideScalarDumps')]
    public function testValueDumpAndHydrationForScalars(
        mixed  $value,
        string $dump,
    ): void {
        self::assertSame($dump, Export::value($value));
        self::assertSame($value, self::evalDump(Export::value($value)));
    }

    public function testValueHydratesInfAndNan(): void
    {
        self::assertSame('INF', Export::value(\INF));
        self::assertSame('-INF', Export::value(-\INF));
        self::assertSame('NAN', Export::value(\NAN));
        self::assertInfinite(self::evalDump(Export::value(\INF)));
        self::assertInfinite(self::evalDump(Export::value(-\INF)));
        self::assertLessThan(0, self::evalDump(Export::value(-\INF)));
        self::assertNan(self::evalDump(Export::value(\NAN)));
    }

    public function testValueHydratesEnums(): void
    {
        self::assertSame('\\' . Order::class . '::ASC', Export::value(Order::ASC));
        self::assertSame(Order::ASC, self::evalDump(Export::value(Order::ASC)));
        self::assertSame(Type::Setting, self::evalDump(Export::value(Type::Setting)));
        self::assertSame(Secret::CREDENTIAL, self::evalDump(Export::value(Secret::CREDENTIAL)));
        self::assertSame(Secret::SENSITIVE, self::evalDump(Export::value(Secret::SENSITIVE)));
    }

    public function testValueMatchesArrayForMaps(): void
    {
        $map = ['a' => 1, 'b' => [2, 3]];
        self::assertSame(Export::array($map), Export::value($map));
        self::assertSame($map, self::evalDump(Export::value($map)));
    }

    public function testValueUsesExportableConstructorDump(): void
    {
        $parameter = new Parameter('k', 'v', Type::Setting, null, []);
        $export    = Export::value($parameter);

        self::assertStringStartsWith('new \\Northrook\\Parameter(', $export);
        $hydrated = self::evalDump($export);
        self::assertInstanceOf(Parameter::class, $hydrated);
        self::assertSame('v', $hydrated->value);
        self::assertSame(Type::Setting, $hydrated->type);
    }

    public function testValueEmitsConstantSource(): void
    {
        self::assertSame('\\PHP_VERSION', Export::value(Export::const('PHP_VERSION')));
        self::assertSame('__CLASS__', Export::value(Export::const('__CLASS__')));
        self::assertSame(\PHP_VERSION, self::evalDump(Export::value(Export::const('PHP_VERSION'))));
    }

    public function testPayloadAllowsScalarsAndNestedArrays(): void
    {
        self::assertSame('1', Export::value(1, true));
        self::assertSame("'x'", Export::value('x', true));
        self::assertSame('true', Export::value(true, true));
        self::assertSame('null', Export::value(null, true));
        self::assertSame('[]', Export::value([], true));
        self::assertSame(
            [
                'a' => [1, 2],
            ],
            self::evalDump(Export::array(['a' => [1, 2]], true)),
        );
    }

    public function testPayloadRejectsConstant(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot export value of type Northrook\Export\Constant as payload');

        Export::value(Export::const('PHP_VERSION'), true);
    }

    public function testPayloadRejectsObjectNestedInArray(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot export value of type stdClass as payload');

        Export::array(['plain' => new \stdClass], true);
    }

    public function testKeyFormatsIntsAndQuotedStrings(): void
    {
        self::assertSame('0', Export::key(0));
        self::assertSame('12', Export::key(12));
        self::assertSame("'app'", Export::key('app'));
        self::assertSame("'o\\'clock'", Export::key("o'clock"));
    }

    public function testClassWithNoArgumentsHydrates(): void
    {
        $export = Export::class(\stdClass::class);

        self::assertSame("new \\stdClass();\n", $export);
        self::assertInstanceOf(\stdClass::class, self::evalDump($export));
    }

    public function testClassTrimsBackslashes(): void
    {
        $export = Export::class('\\' . Parameter::class, 'k', 1, Type::Value, null, []);

        self::assertStringContainsString('new \\Northrook\\Parameter(', $export);
        $hydrated = self::evalDump($export);
        self::assertInstanceOf(Parameter::class, $hydrated);
        self::assertSame(1, $hydrated->value);
    }

    public function testExporterOverrideAndReset(): void
    {
        self::useExporter(Exporter::Reflection);
        self::assertSame(Exporter::Reflection, Export::exporter());

        Export::reset();
        self::assertSame(Exporter::resolve(), Export::exporter());
    }

    public function testHasReflectionAcceptsThisPackage(): void
    {
        self::assertTrue(Exporter::hasReflection());
        self::assertContains(Exporter::resolve(), [
            Exporter::Symfony,
            Exporter::Extension,
            Exporter::Polyfill,
            Exporter::Reflection,
        ]);
    }

    public function testSetFailsafeReturnAndReset(): void
    {
        self::assertTrue(Export::setFailsafe());
        self::assertFalse(Export::setFailsafe(false));
        Export::setFailsafe();
        Export::reset();
        self::assertTrue(Export::setFailsafe());
    }

    #[DataProvider('provideObjectExporters')]
    public function testObjectRoundTripOnEachBackend(
        Exporter $exporter,
    ): void {
        self::useExporter($exporter);
        $dto = new ExportPublicDtoFixture('ok', 2);

        $hydrated = self::evalDump(Export::object($dto));

        self::assertInstanceOf(ExportPublicDtoFixture::class, $hydrated);
        self::assertSame('ok', $hydrated->name);
        self::assertSame(2, $hydrated->count);
    }

    #[DataProvider('provideObjectExporters')]
    public function testNestedObjectGraphRoundTripOnEachBackend(
        Exporter $exporter,
    ): void {
        self::useExporter($exporter);

        $hydrated = self::evalDump(Export::value([
            'dto'   => new ExportPublicDtoFixture('nested', 1),
            'inner' => new ExportNestedDtoFixture(new ExportPublicDtoFixture('child', 3)),
        ]));

        self::assertSame('nested', $hydrated['dto']->name);
        self::assertSame('child', $hydrated['inner']->inner->name);
        self::assertSame(3, $hydrated['inner']->inner->count);
    }

    public function testDateTimeRoundTripOnCloneBackends(): void
    {
        $date = new \DateTimeImmutable('2024-01-15T12:00:00+00:00');

        foreach ([Exporter::Symfony, Exporter::Polyfill] as $exporter) {
            Export::reset();
            self::useExporter($exporter);
            $hydrated = self::evalDump(Export::object($date));
            self::assertInstanceOf(\DateTimeImmutable::class, $hydrated);
            self::assertSame($date->format('c'), $hydrated->format('c'));
        }
    }

    public function testReflectionDateTimeRestoreSkipsConstructor(): void
    {
        self::useExporter(Exporter::Reflection);
        $hydrated = self::evalDump(Export::object(new \DateTimeImmutable('2024-01-15T12:00:00+00:00')));

        self::assertInstanceOf(\DateTimeImmutable::class, $hydrated);
        $this->expectException(\DateObjectError::class);
        $hydrated->format('c');
    }

    public function testCloneBackendsPreserveStdClassDynamics(): void
    {
        $plain    = new \stdClass;
        $plain->x = 1;
        $plain->y = 'two';

        foreach ([Exporter::Symfony, Exporter::Polyfill] as $exporter) {
            Export::reset();
            self::useExporter($exporter);
            $hydrated = self::evalDump(Export::object($plain));
            self::assertSame(1, $hydrated->x);
            self::assertSame('two', $hydrated->y);
        }
    }

    public function testReflectionExportOmitsStdClassDynamicProperties(): void
    {
        self::useExporter(Exporter::Reflection);
        $plain    = new \stdClass;
        $plain->x = 1;

        $hydrated = self::evalDump(Export::object($plain));

        self::assertInstanceOf(\stdClass::class, $hydrated);
        self::assertFalse(isset($hydrated->x));
    }

    public function testReflectionSkipsStaticAndVirtualProperties(): void
    {
        self::useExporter(Exporter::Reflection);
        $export = Export::object(new ExportHookFixture('Ada', 'Lovelace'));

        self::assertStringNotContainsString('ignored', $export);
        self::assertStringNotContainsString("'full'", $export);
        self::assertStringContainsString("'first'", $export);
        self::assertStringContainsString("'last'", $export);

        $hydrated = self::evalDump($export);
        self::assertSame('Ada Lovelace', $hydrated->full);
        self::assertSame('static', ExportHookFixture::$ignored);
    }

    public function testInstantiateRejectsVirtualPropertyName(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to restore');

        \instantiate(ExportHookFixture::class, [
            'first' => 'Ada',
            'last'  => 'Lovelace',
            'full'  => 'nope',
        ]);
    }

    public function testInstantiateRejectsMissingClass(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to restore');

        \instantiate('Northrook\\Contracts\\Tests\\ExportMissingClass', []);
    }

    public function testInstantiateRejectsTypeMismatch(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to restore');

        \instantiate(ExportRestoreFixture::class, ['label' => ['not', 'a', 'string']]);
    }

    public function testInstantiateEmptyStdClass(): void
    {
        $restored = \instantiate(\stdClass::class, []);

        self::assertInstanceOf(\stdClass::class, $restored);
    }

    public function testAnonymousClassThrowsOnCloneBackends(): void
    {
        foreach ([Exporter::Symfony, Exporter::Polyfill] as $exporter) {
            Export::reset();
            self::useExporter($exporter);

            try {
                Export::object(new class('x') {
                    public function __construct(
                        public string $value,
                    ) {}
                });
                self::fail($exporter->name . ' should refuse anonymous classes');
            }
            catch (RuntimeException $exception) {
                self::assertStringContainsString('Failed to export', $exception->getMessage());
            }
        }
    }

    public function testAnonymousClassReflectionDumpIsNotValidPhp(): void
    {
        self::useExporter(Exporter::Reflection);
        $dump = Export::object(new class {
            public int $a = 1;
        });

        $this->expectException(\ParseError::class);
        self::evalDump($dump);
    }

    public function testArrayDepthIsRestoredAfterPayloadFailure(): void
    {
        try {
            Export::array(['ok' => 1, 'bad' => new \stdClass], true);
            self::fail('payload should reject nested objects');
        }
        catch (InvalidArgumentException) {
        }

        self::assertSame(
            <<<'PHP'
                [
                    'x' => [
                        'y' => 1,
                    ],
                ]
                PHP,
            Export::array(['x' => ['y' => 1]]),
        );
    }

    public function testIntegerAndStringKeysSurviveHydration(): void
    {
        $value = [
            0       => 'zero',
            2       => 'gap',
            '00'    => 'double-zero',
            'plain' => true,
        ];

        self::assertSame($value, self::evalDump(Export::value($value)));
    }

    public function testNewlineAndControlCharactersRoundTrip(): void
    {
        $value = "line\nbreak\r\ttab\\slash";

        self::assertSame($value, self::evalDump(Export::string($value)));
        self::assertSame(
            [$value => $value],
            self::evalDump(Export::array([$value => $value])),
        );
    }

    public function testReflectionBackendDoesNotUseFailsafeCatch(): void
    {
        self::useExporter(Exporter::Reflection);
        Export::setFailsafe();

        $dump = Export::object(new class {
            public int $a = 1;
        });

        self::assertStringStartsWith('\\instantiate(', $dump);
    }

    public function testFailsafeOffThrowsForUnexportableObject(): void
    {
        self::useExporter(Exporter::Symfony);
        Export::setFailsafe(false);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to export');

        Export::object(new \ReflectionClass(\stdClass::class));
    }
}

final class ExportPublicDtoFixture
{
    public function __construct(
        public string $name,
        public int    $count,
    ) {}
}

final class ExportRestoreFixture
{
    public function __construct(
        private readonly string $label,
    ) {}

    public function label(): string
    {
        return $this->label;
    }
}

class ExportRestoreParentFixture
{
    public function __construct(
        private readonly string $origin,
    ) {}

    public function origin(): string
    {
        return $this->origin;
    }
}

class ExportRestoreChildFixture extends ExportRestoreParentFixture
{
    public function __construct(
        private readonly string $label,
        string                  $origin,
    ) {
        parent::__construct($origin);
    }

    public function label(): string
    {
        return $this->label;
    }
}

class ExportShadowParentFixture
{
    public function __construct(
        private readonly string $label,
    ) {}

    public function parentLabel(): string
    {
        return $this->label;
    }
}

class ExportShadowChildFixture extends ExportShadowParentFixture
{
    public function __construct(
        private readonly string $label,
        string                  $parentLabel,
    ) {
        parent::__construct($parentLabel);
    }

    public function childLabel(): string
    {
        return $this->label;
    }
}

final class ExportNestedDtoFixture
{
    public function __construct(
        public ExportPublicDtoFixture $inner,
    ) {}
}

final class ExportHookFixture
{
    public static string $ignored = 'static';

    public function __construct(
        public string $first,
        public string $last,
    ) {}

    public string $full {
        get => $this->first . ' ' . $this->last;
    }
}
