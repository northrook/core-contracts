<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests;

use Northrook\Contracts\ColorPalette;
use Northrook\Contracts\ColorScheme;
use Northrook\Contracts\RuntimeException;
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

    public function testColorSchemeBackedValues(): void
    {
        self::assertSame('light', ColorScheme::Light->value);
        self::assertSame('dark', ColorScheme::Dark->value);
        self::assertSame(ColorScheme::Dark, ColorScheme::from('dark'));
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

        self::assertCount(13, $palette->variables());
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

    public function testVariablesConcatenatesPrefixWithoutSeparator(): void
    {
        $palette = $this->palette();

        // No hyphen is injected between prefix and name — pass it in the prefix.
        self::assertArrayHasKey('--themebackground', $palette->variables('theme'));
        self::assertArrayHasKey('--theme-background', $palette->variables('--theme-'));
    }

    public function testVariableReturnsCssVarReference(): void
    {
        $palette = $this->palette();

        self::assertSame('var(--background)', $palette->variable('background'));
        self::assertSame('var(--theme-background)', $palette->variable('background', 'theme-'));
        self::assertSame('var(--themebackground)', $palette->variable('background', 'theme'));
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
            theme : 'mocha',
            scheme: ColorScheme::Dark,
        );

        self::assertSame('mocha', $palette->jsonSerialize()['theme']);
        self::assertSame('dark', $palette->jsonSerialize()['scheme']);
        self::assertSame('#111111', $palette->jsonSerialize()['background']);
    }

    public function testDefaultPalettes(): void
    {
        $light = ColorPalette::default();
        $dark  = ColorPalette::default(ColorScheme::Dark);

        self::assertSame('default', $light->theme);
        self::assertTrue($light->isLight());
        self::assertSame('#f6f4fa', $light->background);
        self::assertSame('#8f4dff', $light->primary);

        self::assertSame('default', $dark->theme);
        self::assertTrue($dark->isDark());
        self::assertSame('#0c0b10', $dark->background);
        self::assertSame('#c794ff', $dark->primary);
    }

    public function testToStringIsJson(): void
    {
        $palette = $this->palette();

        /** @var array<string, mixed> $decoded */
        $decoded = \json_decode((string) $palette, true, 512, \JSON_THROW_ON_ERROR);

        self::assertSame('test', $decoded['theme']);
        self::assertSame('dark', $decoded['scheme']);
        self::assertSame('#111111', $decoded['background']);
    }

    private function palette(
        string      $theme = 'test',
        ColorScheme $scheme = ColorScheme::Dark,
    ): ColorPalette {
        return new ColorPalette(
            theme     : $theme,
            scheme    : $scheme,
            background: '#111111',
            surface   : '#222222',
            overlay   : '#333333',
            outline   : '#444444',
            muted     : '#555555',
            text      : '#ffffff',
            primary   : '#6699ff',
            accent    : '#ff66aa',
            notice    : '#aaaaaa',
            info      : '#66ccff',
            success   : '#66cc66',
            warning   : '#ffcc66',
            danger    : '#ff6666',
        );
    }
}
