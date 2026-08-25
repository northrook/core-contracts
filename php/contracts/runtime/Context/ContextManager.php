<?php

declare(strict_types=1);

namespace Northrook\Context;

use Northrook\Context;
use Northrook\Contracts\ContextEnum;
use Northrook\Contracts\Resettable;
use Northrook\InvalidArgumentException;
use Northrook\Kernel\KernelContext;
use Northrook\LogicException;
use Northrook\RingBuffer;
use Psr\Log\LoggerInterface;

/**
 * Typed context map: one active {@see ContextEnum} case per enum class.
 *
 * Owned by the Kernel runtime; not a public application API.
 *
 * - Current Contexts are stored in {@see ContextManager::$context}.
 * - When changed, the previous Context is stored in {@see ContextManager::$contextHistory}.
 * - Identical case writes are no-ops (no history, entry retained).
 *
 * @phpstan-type ContextKey class-string<\Northrook\Contracts\ContextEnum>
 * @phpstan-type EnumReference ContextKey|\Northrook\Contracts\ContextEnum
 */
final class ContextManager implements Resettable
{
    /**
     * The {@see ContextManager} must be a unique instance.
     *
     * @var bool
     */
    private static bool $initialized = false;

    /**
     * @var \Northrook\RingBuffer<\Northrook\Context\ContextEntry>
     */
    private readonly RingBuffer $contextHistory;

    /**
     * @var array<ContextKey, \Northrook\Context\ContextEntry>
     */
    private array $context = [];

    /**
     * Displaced Context entries, most-recent first.
     *
     * @var list<\Northrook\Context\ContextEntry>
     */
    public array $history {
        get => $this->contextHistory->values();
    }

    /**
     * Currently active Context entries.
     *
     * @var list<\Northrook\Context\ContextEntry>
     */
    public array $current {
        get => \array_values($this->context);
    }

    /**
     * Soft-lock against mutation ({@see freeze()}).
     *
     * @var bool
     */
    private(set) bool $frozen = false;

    public function __construct(
        private readonly null|LoggerInterface $logger = null,
    ) {
        if (self::$initialized) {
            throw new LogicException(
                message: 'Cannot instantiate multiple ContextManagers.',
            );
        }
        $this->contextHistory = new RingBuffer(12);
        self::$initialized    = true;
    }

    public function __destruct()
    {
        self::$initialized = false;
    }

    //region Get

    /**
     * Active {@see ContextEntry} for the given enum class, or `null` when unset.
     *
     * Keys by enum class; the case of a passed {@see ContextEnum} is ignored.
     *
     * @param ContextEnum  $context
     *
     * @return null|\Northrook\Context\ContextEntry
     */
    public function entry(
        string|ContextEnum $context,
    ): null|ContextEntry {
        return $this->context[$this->key($context)] ?? null;
    }

    //endregion Get
    //
    //region Has

    /**
     * Whether a Context is active.
     *
     * - Class-string: true when that enum class has any active case.
     * - Case: true only when that exact case is active.
     *
     * @param ContextEnum  $context
     *
     * @return bool
     */
    public function has(
        string|ContextEnum $context,
    ): bool {
        return \is_string($context)
            ? $this->entry($context) !== null
            : $this->entry($context)?->context === $context;
    }

    /**
     * @param ContextEnum  ...$context
     *
     * @return bool
     */
    public function hasAll(
        string|ContextEnum ...$context,
    ): bool {
        if (empty($context)) {
            return false;
        }

        return \array_all(
            array   : $context,
            callback: $this->has(...),
        );
    }

    /**
     * @param ContextEnum  ...$context
     *
     * @return bool
     */
    public function hasAny(
        string|ContextEnum ...$context,
    ): bool {
        if (empty($context)) {
            return false;
        }
        return \array_any(
            array   : $context,
            callback: $this->has(...),
        );
    }

    //endregion Has
    //
    //region Set

    /**
     * Sets a {@see ContextEnum} case; returns the previous case if set.
     *
     * No-op when the same case is already active.
     *
     * @template T of \Northrook\Contracts\ContextEnum
     *
     * @param T  $context
     *
     * @return null|T
     */
    public function replace(
        ContextEnum $context,
    ): null|ContextEnum {
        $this->editable();
        $current = $this->entry($context);

        $this->guard($context, $current);

        if ($current?->context === $context) {
            return $current->context;
        }
        if ($current) {
            $this->contextHistory->push($current);
        }
        $this->context[$context::class] = new ContextEntry($context);
        return $current?->context;
    }

    /**
     * @template T of \Northrook\Contracts\ContextEnum
     *
     * @param T  $default
     *
     * @return T
     */
    public function resolve(
        ContextEnum $default,
    ): ContextEnum {
        $current = $this->entry($default);
        if ($current) {
            return (
                $current->context instanceof $default
                    ? $current->context
                    : throw new LogicException(
                        message: 'Context mismatch: ' . $current->context::class . ' != ' . $default::class,
                        context: ['current' => $current, 'default' => $default],
                    )
            );
        }

        $this->update($default);

        return $default;
    }

    /**
     * Updates one or more {@see ContextEnum} cases.
     *
     * Identical cases are skipped. Each enum class may appear at most once.
     */
    public function update(
        ContextEnum ...$context,
    ): void {
        $this->editable();

        foreach ($this->entries($context, __METHOD__) as $update) {
            $key = $update::class;
            if ($current = $this->entry($key)) {
                $this->guard($update, $current);
                if ($current->context === $update) {
                    continue;
                }
                $this->contextHistory->push($current);
            }
            $this->context[$key] = new ContextEntry($update);
        }
    }

    /**
     * Replaces all Context entries.
     *
     * - Omitted classes are displaced into history.
     * - Calling this with no arguments is equivalent to {@see clear()}.
     *
     * @param \Northrook\Contracts\ContextEnum  ...$context
     */
    public function set(
        ContextEnum ...$context,
    ): void {
        $this->editable();

        $map = [];

        foreach ($this->entries($context, __METHOD__) as $set) {
            $key = $set::class;
            if ($current = $this->entry($key)) {
                $this->guard($set, $current);
                unset($this->context[$key]);
                if ($current->context === $set) {
                    $map[$key] = $current;
                    continue;
                }
                $this->contextHistory->push($current);
            }
            $map[$key] = new ContextEntry($set);
        }

        foreach ($this->context as $entry) {
            $this->contextHistory->push($entry);
        }

        $this->context = $map;
    }

    /**
     * Removes active Contexts.
     *
     * - Class-string: clears that enum class regardless of the active case.
     * - Case: clears only when that exact case is active; otherwise a no-op.
     *
     * @param ContextEnum  ...$context
     */
    public function unset(
        ContextEnum|string ...$context,
    ): void {
        $this->editable();

        foreach ($context as $unset) {
            $key = $this->key($unset);
            if ($record = $this->entry($key)) {
                if (! \is_string($unset) && $record->context !== $unset) {
                    continue;
                }

                $this->contextHistory->push($record);
                unset($this->context[$key]);
            }
        }
    }

    /**
     * Displaces every active Context entry into history, then empties the map.
     */
    public function clear(): void
    {
        $this->editable();

        $this->logger?->info(message: __METHOD__);

        foreach ($this->context as $entry) {
            $this->contextHistory->push($entry);
        }

        $this->context = [];
    }

    /**
     * Empties the Context map and history.
     */
    public function reset(): void
    {
        $this->editable();

        $this->logger?->info(message: __METHOD__);

        $this->contextHistory->clear();
        $this->context = [];
    }

    /**
     * Soft-lock against further mutation.
     *
     * - Unfreezing in an untrusted context throws.
     *
     * @param bool $set
     */
    public function freeze(
        bool $set = true,
    ): void {
        if ($set === false && Context::isUntrusted()) {
            $exception = new LogicException(
                message: 'Cannot unfreeze context in an untrusted context.',
                context: [
                    'context' => $this->current,
                ],
            );
            $this->logger?->critical(
                $exception->getMessage(),
                ['exception' => $exception],
            );
            throw $exception;
        }

        $this->frozen = $set;
    }

    private function editable(): void
    {
        if ($this->frozen) {
            $exception = new LogicException(
                message: 'Cannot modify frozen context.',
                context: ['contextManager' => $this],
            );
            $this->logger?->critical(
                $exception->getMessage(),
                ['exception' => $exception],
            );
            throw $exception;
        }
    }

    /**
     * @param string|object  $from
     *
     * @return string
     */
    private function key(
        string|object $from,
    ): string {
        return \is_string($from) ? $from : $from::class;
    }

    /**
     * @param \Northrook\Contracts\ContextEnum      $context
     * @param null|\Northrook\Context\ContextEntry  $current
     *
     * @return void
     */
    private function guard(
        ContextEnum       $context,
        null|ContextEntry $current = null,
    ): void {
        $this->logger?->debug(
            message: __CLASS__ . ' handling ' . $context::class,
        );
        // KernelContext may only advance or stay in-band
        if ($context instanceof KernelContext) {
            $current ??= $this->entry(KernelContext::class);

            if (! $current?->context instanceof KernelContext) {
                return;
            }

            if ($current->context->order() <= $context->order()) {
                return;
            }

            throw new LogicException(
                message: 'Invalid KernelContext case order.',
                context: [
                    'current' => $current,
                    'context' => $context,
                ],
            );
        }
    }

    /**
     * @param ContextEnum[]  $from
     *
     * @return ContextEnum[]
     */
    private function entries(
        array  $from,
        string $caller,
    ): array {
        $entries = [];
        foreach ($from as $value) {
            $key = $this->key($value);

            if (! isset($entries[$key])) {
                $entries[$key] = $value;
            }
            else {
                throw new InvalidArgumentException(
                    message: $caller . ' requires unique Context entries.',
                );
            }
        }
        return $entries;
    }
}
