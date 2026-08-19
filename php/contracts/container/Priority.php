<?php

declare(strict_types=1);

namespace Northrook;

use Northrook\Contracts\Resettable;

/**
 * Priority value with shared auto-allocation across a series.
 *
 * `null` ({@see AUTO}) resolves via {@see get()} to the next free slot.
 * Pass `$relative` to seed that search; omit it to plain auto-increment.
 *
 * When `$relative` equals an already-assigned value, the result is bumped in
 * that value's direction — unless {@see $fixed}, which throws.
 *
 * Allocation is scoped by {@see $domain} (default: this class FQCN), so compiler
 * passes, render stacks, etc. claim independently.
 *
 * {@see reset()} clears the domain registry only; already-resolved instances
 * keep their concrete value.
 */
#[\Attribute]
final class Priority implements \Stringable, Resettable
{
    public const null AUTO = null;

    public const int MAX = 1_024;

    public const int MIN = -1_024;

    /** @var array<string, int> */
    private static array $autoIterator = [];

    /** @var array<string, array<int, true>> */
    private static array $taken = [];

    /** @var null|int<self::MIN, self::MAX> */
    private(set) null|int $value;

    private bool $resolved = false;

    /**
     * @param null|int<self::MIN, self::MAX> $value
     * @param non-empty-string               $domain
     */
    public function __construct(
        null|int               $value,
        public readonly bool   $fixed = false,
        public readonly string $domain = self::class,
    ) {
        if ($domain === '') {
            throw new InvalidArgumentException(
                message: 'Priority domain must be a non-empty string.',
            );
        }

        if ($value === null && $this->fixed) {
            throw new InvalidArgumentException(
                message: 'Cannot create a fixed priority without a value.',
            );
        }

        $this->value = $value === null ? self::AUTO : $this->bound($value);
    }

    /**
     * Resolve to a concrete priority, claiming it in the domain series.
     *
     * Idempotent per instance unless `$relative` collides with the assigned value.
     *
     * @param null|int $relative  seed / neighbor slot; collision bumps unless fixed
     *
     * @return int<self::MIN, self::MAX>
     */
    public function get(
        null|int $relative = null,
    ): int {
        if ($this->resolved) {
            $value = $this->value ?? throw new InvalidArgumentException(
                message: 'Resolved priority has no concrete value.',
                context: ['domain' => $this->domain],
            );

            if ($relative !== null && $relative === $value) {
                $value = $this->bumpAway($this->step($value), $relative);
                $this->claim($value);
                $this->value = $value;
            }

            return $value;
        }

        $assigned = $this->value !== null
            ? $this->resolveExplicit($this->value, $relative)
            : $this->allocate($relative);

        $this->claim($assigned);
        $this->value    = $assigned;
        $this->resolved = true;

        return $assigned;
    }

    public function __toString(): string
    {
        return (string) $this->get();
    }

    /**
     * @param null|int<self::MIN, self::MAX> $value
     *
     * @return $this
     */
    public function set(
        null|int $value,
    ): self {
        if ($this->fixed) {
            throw new InvalidArgumentException(
                message: 'Cannot modify a fixed priority.',
                context: ['value' => $this->value, 'domain' => $this->domain],
            );
        }

        if ($this->resolved) {
            $this->release();
            $this->resolved = false;
        }

        $this->value = $value === null ? self::AUTO : $this->bound($value);

        return $this;
    }

    /**
     * Clears allocation state for this instance's domain, or every domain when
     * `$all` is true.
     */
    public function reset(
        bool $all = false,
    ): void {
        if ($all) {
            self::$autoIterator = [];
            self::$taken        = [];
        } else {
            unset(self::$autoIterator[$this->domain], self::$taken[$this->domain]);
        }
    }

    /**
     * @param non-empty-string $domain
     */
    public static function auto(
        string $domain = self::class,
    ): self {
        return new self(
            value : self::AUTO,
            domain: $domain,
        );
    }

    /**
     * @param int<self::MIN, self::MAX> $value
     * @param non-empty-string          $domain
     */
    public static function fixed(
        int    $value,
        string $domain = self::class,
    ): self {
        return new self(
            value : $value,
            fixed : true,
            domain: $domain,
        );
    }

    /**
     * @param int<self::MIN, self::MAX> $value
     *
     * @return int<self::MIN, self::MAX>
     */
    public static function value(
        int $value,
    ): int {
        return (
            new self(value: $value)->value ?? throw new InvalidArgumentException(
                message: 'Expected a concrete priority value.',
            )
        );
    }

    /**
     * @param null|int<self::MIN, self::MAX> $value
     * @param non-empty-string               $domain
     */
    public static function from(
        null|int $value,
        bool     $fixed = false,
        string   $domain = self::class,
    ): self {
        return new self($value, $fixed, $domain);
    }

    /**
     * @param int<self::MIN, self::MAX> $assigned
     *
     * @return int<self::MIN, self::MAX>
     */
    private function resolveExplicit(
        int      $assigned,
        null|int $relative,
    ): int {
        $relativeHit = $relative !== null && $relative === $assigned;

        if (! $relativeHit && ! $this->isTaken($assigned)) {
            return $assigned;
        }

        return $this->bumpAway(
            $relativeHit ? $this->step($assigned) : $assigned,
            $relative,
        );
    }

    /**
     * Throw if fixed; otherwise release any current claim and take the next free
     * slot starting at `$from`.
     *
     * @return int<self::MIN, self::MAX>
     */
    private function bumpAway(
        int      $from,
        null|int $relative,
    ): int {
        if ($this->fixed) {
            throw new InvalidArgumentException(
                message: $relative !== null && $relative === $this->value
                    ? "Fixed priority `{$this->value}` collides with relative `{$relative}`."
                    : "Fixed priority `{$from}` slot already taken.",
                context: [
                    'value'    => $this->value ?? $from,
                    'relative' => $relative,
                    'domain'   => $this->domain,
                ],
            );
        }

        if ($this->resolved) {
            $this->release();
        }

        return $this->nextFree($from);
    }

    /**
     * @return int<self::MIN, self::MAX>
     */
    private function allocate(
        null|int $relative,
    ): int {
        if ($relative !== null) {
            return $this->nextFree($relative);
        }

        $iterator = self::$autoIterator[$this->domain] ?? 0;
        $assigned = $this->nextFree($iterator);

        self::$autoIterator[$this->domain] = $assigned + $this->direction($assigned);

        return $assigned;
    }

    /**
     * @return int<self::MIN, self::MAX>
     */
    private function nextFree(
        int $start,
    ): int {
        $candidate = $this->bound($start);
        $step      = $this->direction($candidate);

        while ($this->isTaken($candidate)) {
            $next = $candidate + $step;

            if ($next < self::MIN || $next > self::MAX) {
                throw new InvalidArgumentException(
                    message: "No free priority slot in domain `{$this->domain}` from `{$start}`.",
                    context: [
                        'start'  => $start,
                        'domain' => $this->domain,
                        'min'    => self::MIN,
                        'max'    => self::MAX,
                    ],
                );
            }

            $candidate = $next;
        }

        return $candidate;
    }

    /**
     * @return int<self::MIN, self::MAX>
     */
    private function step(
        int $value,
    ): int {
        $next = $value + $this->direction($value);

        if ($next < self::MIN || $next > self::MAX) {
            throw new InvalidArgumentException(
                message: "Cannot bump priority `{$value}` beyond bounds.",
                context: ['value' => $value, 'min' => self::MIN, 'max' => self::MAX],
            );
        }

        return $next;
    }

    private function direction(
        int $value,
    ): int {
        return $value < 0 ? -1 : 1;
    }

    private function isTaken(
        int $value,
    ): bool {
        return isset(self::$taken[$this->domain][$value]);
    }

    private function claim(
        int $value,
    ): void {
        self::$taken[$this->domain][$value] = true;
    }

    private function release(): void
    {
        if ($this->value !== null) {
            unset(self::$taken[$this->domain][$this->value]);
        }
    }

    /**
     * @return int<self::MIN, self::MAX>
     */
    private function bound(
        int $value,
    ): int {
        if ($value < self::MIN || $value > self::MAX) {
            throw new InvalidArgumentException(
                message: "Invalid priority: `{$value}`, it must be between `" . self::MIN . '` and `' . self::MAX . '`.',
                context: ['value' => $value, 'min' => self::MIN, 'max' => self::MAX],
            );
        }

        return $value;
    }
}
