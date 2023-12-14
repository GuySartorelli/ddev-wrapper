<?php declare(strict_types=1);

namespace GuySartorelli\DdevWrapper;

use GuySartorelli\DdevWrapper\Command\HelpCommand;
use GuySartorelli\DdevWrapper\Command\PassThroughCommand;
use GuySartorelli\DdevWrapper\Console\DdevCommandLoader;
use GuySartorelli\DdevWrapper\Console\VersionlessInput;
use Symfony\Component\Console\Application as BaseApplication;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\CompleteCommand;
use Symfony\Component\Console\Command\HelpCommand as BaseHelpCommand;
use Symfony\Component\Console\Command\LazyCommand;
use Symfony\Component\Console\Command\ListCommand;
use Symfony\Component\Console\Exception\CommandNotFoundException;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

class Application extends BaseApplication
{
    private string $version = '';

    public function __construct(string $name)
    {
        parent::__construct($name);
        $this->setDefaultCommand('list-commands');
        $this->setCommandLoader(new DdevCommandLoader());
    }

    /**
     * Add a command which is a shortcut to a DDEV command (useful for exec commands)
     *
     * Returns the Command instance, so you can add input options etc for default args.
     */
    public function addShortcutCommand(string $name, string $description, string $ddevCommand, array $args): Command
    {
        $command = $this->register($name);
        $command->setDescription($description);

        $command->setCode(function (InputInterface $input, OutputInterface $output) use ($ddevCommand, $args): int {
            // Get any passthrough values from the input arguments/options
            $passThrough = PassThroughCommand::getPassThroughArgsForInput($input, $this->getForbiddenOptions());

            // Run the command
            $process = new Process(['ddev', $ddevCommand, ...$args, ...$passThrough]);
            if (Process::isTtySupported()) {
                $process->setTimeout(null);
                $process->setTty(true);
            }
            $process->run();

            // Send output if we weren't able to run interactively
            if (!$process->isTty()) {
                $output->write($process->isSuccessful() ? $process->getOutput() : $process->getErrorOutput());
            }

            return $process->isSuccessful() ? Command::SUCCESS : Command::FAILURE;
        });

        return $command;
    }

    public function getVersion(): string
    {
        if (!$this->version) {
            $this->version = trim(DDevHelper::run('-v'));
        }
        return '(' . $this->version . ')';
    }

    protected function getDefaultCommands(): array
    {
        $defaults = parent::getDefaultCommands();
        foreach ($defaults as $i => $command) {
            if ($command instanceof ListCommand) {
                $command->setName('list-commands');
            } elseif ($command instanceof BaseHelpCommand) {
                $defaults[$i] = new HelpCommand($command->getName());
            } elseif ($command instanceof CompleteCommand) {
                $command->addOption('no-interaction', 'n');
            }
        }
        return $defaults;
    }

    public function doRun(InputInterface $input, OutputInterface $output)
    {
        // Allow getting the version using "--version" or "-V", but only for the help command or no command.
        if ($input->hasParameterOption(['--version', '-V']) && (!$input->getFirstArgument() || $input->getFirstArgument() === 'help')) {
            $output->writeln($this->getLongVersion());
            return 0;
        }

        // Decorate the input so we can remove the default "--version" option
        $input = new VersionlessInput($input);
        return parent::doRun($input, $output);
    }

    protected function configureIO(InputInterface $input, OutputInterface $output)
    {
        if (true === $input->hasParameterOption(['--ansi'], true)) {
            $output->setDecorated(true);
        } elseif (true === $input->hasParameterOption(['--no-ansi'], true)) {
            $output->setDecorated(false);
        }

        try {
            $command = $this->get($this->getCommandName($input) ?? '');
        } catch (CommandNotFoundException) {
            // If we have no command, it's no a passthrough command, so we can just carry on.
        }

        // If we have a pass through command, bail out. Let DDEV handle its own verbosity.
        if ($command instanceof LazyCommand) {
            $command = $command->getCommand();
        }
        if ($command instanceof PassThroughCommand) {
            return;
        }

        if ($input->hasParameterOption('-vvv', true) || $input->hasParameterOption('--verbose=3', true) || 3 === $input->getParameterOption('--verbose', false, true)) {
            $output->setVerbosity(OutputInterface::VERBOSITY_DEBUG);
            $shellVerbosity = 3;
        } elseif ($input->hasParameterOption('-vv', true) || $input->hasParameterOption('--verbose=2', true) || 2 === $input->getParameterOption('--verbose', false, true)) {
            $output->setVerbosity(OutputInterface::VERBOSITY_VERY_VERBOSE);
            $shellVerbosity = 2;
        } elseif ($input->hasParameterOption('-v', true) || $input->hasParameterOption('--verbose=1', true) || $input->hasParameterOption('--verbose', true) || $input->getParameterOption('--verbose', false, true)) {
            $output->setVerbosity(OutputInterface::VERBOSITY_VERBOSE);
            $shellVerbosity = 1;
        } else {
            $shellVerbosity = 0;
        }

        if (\function_exists('putenv')) {
            @putenv('SHELL_VERBOSITY='.$shellVerbosity);
        }
        $_ENV['SHELL_VERBOSITY'] = $shellVerbosity;
        $_SERVER['SHELL_VERBOSITY'] = $shellVerbosity;
    }

    protected function getDefaultInputDefinition(): InputDefinition
    {
        $definition = parent::getDefaultInputDefinition();
        $realDefinition = $definition->getArguments();
        foreach ($definition->getOptions() as $option) {
            // Don't include options that we're explicitly not using for this application.
            if (!in_array($option->getName(), ['no-interaction', 'quiet', 'version'])) {
                $realDefinition[] = $option;
            }
        }
        $definition->setDefinition($realDefinition);
        return $definition;
    }

    public function getForbiddenOptions(): array
    {
        $options = $this->getDefaultInputDefinition()->getOptions();
        $forbidden = [
            'long' => [],
            'short' => [],
        ];
        foreach ($options as $option)
        {
            $forbidden['long'][] = $option->getName();
            if ($option->getShortcut()) {
                $forbidden['short'][] = $option->getShortcut();
            }
        }
        return $forbidden;
    }
}
