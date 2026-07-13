<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests;

use Northrook\Contracts\ColorPalette;
use Northrook\Contracts\ColorScheme;
use Northrook\Contracts\Exceptions\RuntimeException;
use PHPUnit\Framework\TestCase;

final class ColorPaletteTest extends TestCase
{
    public function testSchemeHelpers(): void
    {
        $light = $this->palette(scheme: ColorScheme::Light);
        $dark  = $this->palette(scheme: ColorScheme::Dark);

        self::assertTrue($light->isLight());
        self::assertFalse($light->isDark());
        self::assertTrue($dark->isDark());
        self::assertFalse($dark->isLight());
    }

    public function testColorsMapsSlotNamesToValues(): void
    {
        $palette = $this->palette();

        self::assertSame(
            [
                'background' => '#111111',
                'surface'    => '#222222',
                'overlay'    => '#333333',
                'outline'    => '#444444',
                'muted'      => '#555555',
                'text'       => '#ffffff',
                'primary'    => '#6699ff',
                'accent'     => '#ff66aa',
                'notice'     => '#aaaaaa',
                'info'       => '#66ccff',
                'success'    => '#66cc66',
                'warning'    => '#ffcc66',
                'danger'     => '#ff6666',
            ],
            $palette->colors(),
        );
    }

    public function testVariablesUsesDoubleDashPrefixByDefault(): void
    {
        $palette = $this->palette();

        self::assertSame('#111111', $palette->variables()['--background']);
        self::assertSame('#ffffff', $palette->variables()['--text']);
        self::assertArrayNotHasKey('background', $palette->variables());
    }

    public function testVariablesAcceptsInfixPrefixAndFormatter(): void
    {
        $palette = $this->palette();

        $variables = $palette->variables(
            'theme-',
            static fn(string $value, string $name): string => $name === 'text' ? 'white' : $value,
        );

        self::assertSame('#111111', $variables['--theme-background']);
        self::assertSame('white', $variables['--theme-text']);
    }

    public function testVariableReturnsCssVarReference(): void
    {
        $palette = $this->palette();

        self::assertSame('var(--background)', $palette->variable('background'));
        self::assertSame('var(--theme-background)', $palette->variable('background', 'theme-'));
    }

    public function testVariableRejectsUnknownColorName(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Unknown color name 'rosewater'.");

        $this->palette()->variable('rosewater');
    }

    public function testToStyleBuildsDeclarationsFromVariables(): void
    {
        $palette = $this->palette();

        self::assertSame(
            '--background: #111111; --surface: #222222; --overlay: #333333; --outline: #444444; --muted: #555555; --text: #ffffff; --primary: #6699ff; --accent: #ff66aa; --notice: #aaaaaa; --info: #66ccff; --success: #66cc66; --warning: #ffcc66; --danger: #ff6666',
            $palette->toStyle(),
        );

        $styled = $palette->toStyle(
            'theme-',
            static fn(string $value, string $name): string => $name === 'text' ? 'white' : $value,
        );

        self::assertStringStartsWith('--theme-background: #111111;', $styled);
        self::assertStringContainsString('--theme-text: white;', $styled);
        self::assertStringEndsWith('--theme-danger: #ff6666', $styled);
    }

    public function testJsonSerializeIncludesBackedScheme(): void
    {
        $palette = $this->palette(
            theme: 'mocha',
            scheme: ColorScheme::Dark,
        );

        self::assertSame('mocha', $palette->jsonSerialize()['theme']);
        self::assertSame('dark', $palette->jsonSerialize()['scheme']);
        self::assertSame('#111111', $palette->jsonSerialize()['background']);
    }

    private function palette(
        string $theme = 'test',
        ColorScheme $scheme = ColorScheme::Dark,
    ): ColorPalette {
        return new ColorPalette(
            theme: $theme,
            scheme: $scheme,
            background: '#111111',
            surface: '#222222',
            overlay: '#333333',
            outline: '#444444',
            muted: '#555555',
            text: '#ffffff',
            primary: '#6699ff',
            accent: '#ff66aa',
            notice: '#aaaaaa',
            info: '#66ccff',
            success: '#66cc66',
            warning: '#ffcc66',
            danger: '#ff6666',
        );
    }
}
