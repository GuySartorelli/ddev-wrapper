<?php declare(strict_types=1);

namespace GuySartorelli\DdevWrapper;

use stdClass;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

class DDevHelper
{
    /**
     * Run a DDEV command interactively (assumes TTY is supported)
     *
     * @return bool Whether the command was successful or not
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
     * Run a DDEV command interactively (assumes TTY is supported) when output is verbose - but otherwise run it normally.
     * Takes an optional callback to handle output when not running interactively.
     *
     * @return bool Whether the command was successful or not
     */
    public static function runInteractiveOnVerbose(string $command, array $args = [], OutputInterface $output, ?callable $quietModeCallback = null): bool
    {
        if ($output->isVerbose()) {
            return static::runInteractive($command, $args);
        }

        $process = new Process(['ddev', $command, ...$args]);
        $process->setTimeout(null);
        $process->run($quietModeCallback);
        return $process->isSuccessful();
    }

    /**
     * Run a DDEV command non-interactively
     *
     * @return string The output from the command
     */
    public static function run(string $command, array $args = []): string
    {
        $process = new Process(['ddev', $command, ...$args]);
        $process->run();
        return $process->isSuccessful() ? $process->getOutput() : $process->getErrorOutput();
    }

    /**
     * Run a DDEV command and get the output as JSON
     *
     * @return mixed The output from the command as JSON output (e.g. stdClass), or null if there was no JSON output.
     */
    public static function runJson(string $command, array $args = []): mixed
    {
        $response = json_decode(static::run($command, [...$args, '--json-output']), false);
        return $response?->raw ?? null;
    }

    /**
     * Get the details of the project, if it exists.
     *
     * @param string $project The name of the project to get details for. If ommitted, the CWD is used.
     * @return stdClass|null The output from the command as a JSON object, or null if there was no JSON output.
     */
    public static function getProjectDetails(string $project = ''): ?stdClass
    {
        return static::runJson('describe', $project ? [$project] : []);
    }
}
