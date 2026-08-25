<?php

declare(strict_types=1);

namespace Northrook\View;

use Northrook\ViewInterface;

class Toast implements ViewInterface, \Stringable
{
    /** @var null|non-empty-string */
    public private(set) null|string $message;

    /** @var null|non-empty-string */
    public private(set) null|string $type;

    public function __construct(
        null|string $message = null,
        null|string $type = null,
    ) {
        $message = \trim($message ?? '') ?: null;
        $type    = \trim($type ?? '') ?: null;

        if ($message !== null) {
            $this->message = \htmlspecialchars($message);
        }
        if ($type !== null) {
            $this->type = $type;
        }
    }

    /**
     * @return non-empty-string
     */
    public function __toString(): string
    {
        $this->type ??= 'info';

        return <<<HTML
            <div class="toast {$this->type}">{$this->message}</div>
            HTML;
    }

    public function render(): null|string
    {
        if ($this->message === null) {
            return null;
        }
        return $this->__toString();
    }
}
