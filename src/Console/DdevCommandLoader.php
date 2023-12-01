<?php declare(strict_types=1);

namespace GuySartorelli\DdevWrapper\Console;

use GuySartorelli\DdevWrapper\Command\PassThroughCommand;
use GuySartorelli\DdevWrapper\DDevHelper;
use LogicException;
use Symfony\Component\Console\Command\Command;
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

        $output = DDevHelper::run('help');

        // Find commandList
        $hasCommandList = preg_match('/(?<=Available Commands:\n).+?(?=\n{2})/s', $output, $matches);
        if (!$hasCommandList) {
            throw new LogicException('No command list found - run "ddev -h" and confirm it outputs correctly.');
        }

        // Find all commands in list
        $commandList = $matches[0];
        $hasValidCommands = preg_match_all('/^\h*(?<name>[\w-]+)\h+(?<description>[^\v]+$)/m', $commandList, $matches);
        if (!$hasValidCommands) {
            throw new LogicException('No commands found - run "ddev -h" and confirm it outputs correctly.');
        }

        // Build a command object for each command in the list
        for ($i = 0; $i < count($matches[0]); $i++) {
            $name = $matches['name'][$i];
            $description = trim($matches['description'][$i]);
            $this->commands[$name] = new PassThroughCommand($name, $description);
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
