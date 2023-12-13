<?php declare(strict_types=1);

namespace GuySartorelli\DdevWrapper;

use GuySartorelli\DdevWrapper\Command\HelpCommand;
use GuySartorelli\DdevWrapper\Console\DdevCommandLoader;
use GuySartorelli\DdevWrapper\Console\VersionlessInput;
use Symfony\Component\Console\Application as BaseApplication;
use Symfony\Component\Console\Command\CompleteCommand;
use Symfony\Component\Console\Command\HelpCommand as BaseHelpCommand;
use Symfony\Component\Console\Command\ListCommand;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Application extends BaseApplication
{
    private string $version = '';

    public function __construct(string $name)
    {
        parent::__construct($name);
        $this->setDefaultCommand('list-commands');
        $this->setCommandLoader(new DdevCommandLoader());
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

        // This is where we'd be dealing with verbosity and the like...
        // but DDEV doesn't have the same verbosity nonsense that symfony console tries to force down our throats
        // so we don't want to call the parent method or reintroduce any of that.
    }

    protected function getDefaultInputDefinition(): InputDefinition
    {
        $definition = parent::getDefaultInputDefinition();
        $realDefinition = $definition->getArguments();
        foreach ($definition->getOptions() as $option) {
            // Don't include options that we're explicitly not using for this application.
            if (!in_array($option->getName(), ['no-interaction', 'quiet', 'verbose', 'version'])) {
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
