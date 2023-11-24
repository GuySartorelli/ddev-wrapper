<?php declare(strict_types=1);

namespace GuySartorelli\DdevWrapper\Application;

use GuySartorelli\DdevWrapper\Command\Help;
use Symfony\Component\Console\Application as BaseApplication;
use Symfony\Component\Console\Command\HelpCommand;
use Symfony\Component\Console\Command\ListCommand;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
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

    public function getVersion(): string
    {
        if (!$this->version) {
            $process = new Process(['ddev', '-v']);
            $process->run();
            if ($process->getErrorOutput()) {
                return 'UNKNOWN';
            }
            $this->version = trim($process->getOutput());
        }
        return '(' . $this->version . ')';
    }

    protected function getDefaultCommands(): array
    {
        $defaults = parent::getDefaultCommands();
        foreach ($defaults as $i => $command) {
            if ($command instanceof ListCommand) {
                $command->setName('list-commands');
            } elseif ($command instanceof HelpCommand) {
                $defaults[$i] = new Help($command->getName());
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
        // Add the one global flag we know DDEV has - easier to just hardcode this rather than fetch it dynamically.
        // If they add more later, it might make sense to fetch these from `ddev -h`
        $realDefinition[] = new InputOption('json-output', 'j', description: 'If true, user-oriented output will be in JSON format.');
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
