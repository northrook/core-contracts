<?php /** @noinspection PhpIllegalPsrClassPathInspection */

declare(strict_types=1);

namespace Northrook\Contracts;

use Northrook\Contracts\Container\Autowire\{Logger, Pathfinder, Profiler, Settings};
use Northrook\Contracts\Container\Parameter;
use Northrook\Contracts\Interfaces\{
    PathfinderInterface,
    PathInterface,
    ProfilerInterface,
    SettingsInterface,
    UrlInterface
};
use Northrook\Contracts\Profiler\ProfilerEvent;
use Stringable;

/**
 * @internal binds autowire traits so static analysis can verify them
 */
final class TraitsForAnalysis
{
    use Logger;
    use Pathfinder;
    use Profiler;
    use Settings;

    public function __construct()
    {
        $this->assignLogger(null, true);
        $this->assignPathfinder(new TraitsForAnalysisPathfinder());
        $this->assignProfiler(new TraitsForAnalysisProfiler());
        $this->assignSettings(new TraitsForAnalysisSettings());
    }
}

/**
 * @internal
 */
final class TraitsForAnalysisPathfinder implements PathfinderInterface
{
    public function getPath(
        string|Stringable $reference,
    ): null|PathInterface {
        return null;
    }

    public function getUrl(
        string|Stringable $reference,
    ): null|UrlInterface {
        return null;
    }
}

/**
 * @internal
 */
final class TraitsForAnalysisProfiler implements ProfilerInterface
{
    public function __invoke(
        string $name,
        null|string $category = null,
    ): null|ProfilerEvent {
        return null;
    }

    public function setCategory(
        null|string $category,
    ): static {
        return $this;
    }

    public function event(
        string $name,
        null|string $category = null,
    ): null|ProfilerEvent {
        return null;
    }

    public function start(
        string $name,
        null|string $category = null,
        null|string $note = null,
    ): null|ProfilerEvent {
        return null;
    }

    public function snapshot(
        string $name,
        null|string $category = null,
        null|string $note = null,
    ): static {
        return $this;
    }

    public function stop(
        null|string $name = null,
        null|string $category = null,
    ): void {}

    public function close(): void {}
}

/**
 * @internal
 */
final class TraitsForAnalysisSettings implements SettingsInterface
{
    public function has(
        string $parameter,
    ): bool {
        return false;
    }

    public function get(
        string $parameter,
    ): Parameter {
        return Parameter::from($parameter);
    }

    public function all(): array
    {
        return [];
    }
}
