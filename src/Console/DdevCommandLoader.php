<?php declare(strict_types=1);

namespace GuySartorelli\DdevWrapper\Console;

use GuySartorelli\DdevWrapper\Command\PassThroughCommand;
use GuySartorelli\DdevWrapper\DDevHelper;
use LogicException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LazyCommand;
use Symfony\Component\Console\CommandLoader\CommandLoaderInterface;
use Symfony\Component\Console\Exception\CommandNotFoundException;

/**
 * Finds available DDEV commands
 */
class DdevCommandLoader implements CommandLoaderInterface
{
    private array $commands = [];

    /**
     * Find what commands DDEV has available for us
     */
    private function initFromDdev(): void
    {
        // Skip if we've already done it.
        if (!empty($this->commands)) {
            return;
        }

        $helpOutput = DDevHelper::runJson('help');
        if (!$helpOutput) {
            throw new LogicException('No command list found - run "ddev -hj" and confirm it outputs correctly.');
        }

        $commands = array_merge($helpOutput->Commands ?? [], $helpOutput->AdditionalHelpCommands ?? [], $helpOutput->AdditionalCommands ?? []);
        if (empty($commands)) {
            throw new LogicException('No commands found - run "ddev -hj" and confirm it outputs correctly.');
        }

        // Build a command object for each command in the list
        foreach ($commands as $command) {
            $name = $command->Name;
            // Use LazyCommand to avoid bootstrapping EVERY command EVERY time we want autocompletion or to list commands.
            // Aliases won't work - and they're not listed in the command list or used in autcompletion (which IMO is actually better anyway).
            $this->commands[$name] = new LazyCommand($name, [], $command->Description, false, fn () => new PassThroughCommand($name));
        }
    }

    public function get(string $name): Command
    {
        if (!$this->has($name)) {
            throw new CommandNotFoundException(sprintf('Command "%s" does not exist.', $name));
        }
        return $this->commands[$name];
    }

    public function has(string $name): bool
    {
        $this->initFromDdev();
        return array_key_exists($name, $this->commands);
    }

    public function getNames(): array
    {
        $this->initFromDdev();
        return array_keys($this->commands);
    }
}
