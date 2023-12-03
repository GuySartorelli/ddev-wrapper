<?php declare(strict_types=1);

namespace GuySartorelli\DdevWrapper;

use stdClass;
use Symfony\Component\Process\Process;

class DDevHelper
{
    /**
     * Run a DDEV command interactively (assumes TTY is supported)
     */
    public static function runInteractive(string $command, array $args = []): bool
    {
        $process = new Process(['ddev', $command, ...$args]);
        $process->setTimeout(null);
        $process->setTty(true);
        $process->run();
        return $process->isSuccessful();
    }

    /**
     * Run a DDEV command non-interactively
     */
    public static function run(string $command, array $args = []): string
    {
        $process = new Process(['ddev', $command, ...$args]);
        $process->run();
        return $process->isSuccessful() ? $process->getOutput() : $process->getErrorOutput();
    }

    /**
     * Run a DDEV command and get the output as JSON
     */
    public static function runJson(string $command, array $args = []): ?stdClass
    {
        $response = json_decode(static::run($command, [...$args, '--json-output']), false);
        return $response?->raw ?? null;
    }

    /**
     * Get the details of the project, if it exists.
     *
     * @param string $project The name of the project to get details for. If ommitted, the CWD is used.
     */
    public static function getProjectDetails(string $project = ''): ?stdClass
    {
        return static::runJson('describe', $project ? [$project] : []);
    }
}
