<?php

declare(strict_types=1);

namespace Northrook\Contracts;

use Northrook\Contracts\Exceptions\RuntimeException;

/**
 * Semantic CSS color palette.
 *
 * @phpstan-type Formatter callable(string $value, string $name): string
 */
final readonly class ColorPalette extends DataObject
{
    /**
     * Color names in declaration order.
     *
     * @var list<string>
     */
    private const array COLOR_NAMES = [
        'background',
        'surface',
        'overlay',
        'outline',
        'muted',
        'text',
        'primary',
        'accent',
        'notice',
        'info',
        'success',
        'warning',
        'danger',
    ];

    /**
     * @param string      $theme      Palette id (`light`, `dark`, `latte`, `mocha`, …)
     * @param ColorScheme $scheme     Canvas polarity for flips and `color-mix`
     * @param string      $background Page / root canvas
     * @param string      $surface    Raised panels, cards
     * @param string      $overlay    Popovers, modals
     * @param string      $outline    Borders, dividers, hairlines
     * @param string      $muted      De-emphasized content/chrome
     * @param string      $text       Primary foreground
     * @param string      $primary    Brand / main action
     * @param string      $accent     Secondary brand highlight
     * @param string      $notice     Neutral callout
     * @param string      $info       Informational
     * @param string      $success    Positive
     * @param string      $warning    Caution
     * @param string      $danger     Error / destructive
     */
    public function __construct(
        public string $theme,
        public ColorScheme $scheme,
        public string $background,
        public string $surface,
        public string $overlay,
        public string $outline,
        public string $muted,
        public string $text,
        public string $primary,
        public string $accent,
        public string $notice,
        public string $info,
        public string $success,
        public string $warning,
        public string $danger,
    ) {
        parent::__construct();
    }

    /**
     * Whether this palette is {@see ColorScheme::Light}
     */
    public function isLight(): bool
    {
        return $this->scheme === ColorScheme::Light;
    }

    /**
     * Whether this palette is {@see ColorScheme::Dark}
     */
    public function isDark(): bool
    {
        return $this->scheme === ColorScheme::Dark;
    }

    /**
     * All colors in declaration order.
     *
     * @return array<string, string> as `[name => value]`
     */
    public function colors(): array
    {
        $colors = [];

        foreach (self::COLOR_NAMES as $name) {
            $colors[$name] = $this->{$name};
        }

        return $colors;
    }

    /**
     * Prefixed CSS custom properties for each color.
     *
     * Example: `['--theme-background' => '#0f0f0f', …]` when `$prefix` is `theme`.
     *
     * @param null|Formatter $parse Optional transform for each CSS value
     *
     * @return array<string, string> as `[name => value]`
     */
    public function variables(
        null|string $prefix = null,
        null|callable $parse = null,
    ): array {
        $array = [];

        foreach ($this->colors() as $name => $value) {
            if ($parse !== null) {
                $value = $parse($value, $name);
            }

            $array[$this->variableName($name, $prefix)] = $value;
        }

        return $array;
    }

    /**
     * A `var(…)` reference for a single color.
     *
     * Example: `variable('background')` → `var(--background)`.
     *
     * @throws RuntimeException when `$name` is not a valid color
     */
    public function variable(
        string $name,
        null|string $prefix = null,
    ): string {
        if (! \in_array($name, self::COLOR_NAMES, true)) {
            throw new RuntimeException(
                message: "Unknown color name '{$name}'.",
                context: ['name' => $name, 'colors' => self::COLOR_NAMES],
            );
        }

        return 'var(' . $this->variableName($name, $prefix) . ')';
    }

    /**
     * Semicolon-separated custom-property declarations for a `style` attribute.
     *
     * Example: `--background: #0f0f0f; --surface: #1a1a1a; …`
     *
     * @param null|Formatter $parse Optional transform for each CSS value
     */
    public function toStyle(
        null|string $prefix = null,
        null|callable $parse = null,
    ): string {
        $parts = [];

        foreach ($this->variables(
            $prefix,
            $parse,
        ) as $property => $value) {
            $parts[] = $property . ': ' . $value;
        }

        return \implode('; ', $parts);
    }

    private function variableName(
        string $name,
        null|string $prefix = null,
    ): string {
        return '--' . ( $prefix === null ? '' : \ltrim($prefix, '-') ) . \ltrim($name, '-');
    }
}
