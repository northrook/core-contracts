<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests;

use Northrook\Export;
use Northrook\Parameter;
use Northrook\Parameter\Type;
use PHPUnit\Framework\TestCase;

final class ExportTest extends TestCase
{
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
                        NULL,
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
                    NULL,
                    [],
                );

                PHP,
            $export,
        );

        $hydrated = eval('return ' . $export);
        self::assertInstanceOf(Parameter::class, $hydrated);
        self::assertSame(1, $hydrated->value);
    }
}
