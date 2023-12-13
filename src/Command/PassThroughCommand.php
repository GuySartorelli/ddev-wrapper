<?php declare(strict_types=1);

namespace GuySartorelli\DdevWrapper\Command;

use GuySartorelli\DdevWrapper\Application;
use GuySartorelli\DdevWrapper\DDevHelper;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

/**
 * A command identified as being part of DDEV, which when executed will run the associated DDEV command.
 * @author Guy Sartorelli
 */
class PassThroughCommand extends Command
{
    /**
     * A default value which indicates an option has no value.
     *
     * This is necessary because Symfony console sets the value to "null" if an optional
     * option is passed with no value, e.g. "--yell".
     * It *should* set it to true, but it's silly so it doesn't.
     *
     * The official docs say to set the default value to "false", but then the help info
     * says the default value is false, and in this case we're getting the help info mostly
     * from DDEV so we don't want to say "default: false" about things which may not have a
     * default value of false.
     */
    public const NULL_OPTION_VALUE = '____THIS_IS_THE_DEFAULT_VALUE____IT_HAS_NO_VALUE____';

    private bool $initialised = false;

    public function getHelp(): string
    {
        $this->initFromDdev();
        return parent::getHelp();
    }

    public function getDefinition(): InputDefinition
    {
        $this->initFromDdev();
        return parent::getDefinition();
    }

    public function getAliases(): array
    {
        $this->initFromDdev();
        return parent::getAliases();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $forbiddenOptions = ($this->getApplication() instanceof Application)
            ? $this->getApplication()->getForbiddenOptions()
            : [];
        $passThrough = static::getPassThroughArgsForInput($input, $forbiddenOptions);

        // Run the command
        $process = new Process(['ddev', $this->getName(), ...$passThrough]);
        if (Process::isTtySupported()) {
            $process->setTimeout(null);
            $process->setTty(true);
        }
        $process->run();

        // Send output if we weren't able to run interactively
        if (!$process->isTty()) {
            $output->write($process->isSuccessful() ? $process->getOutput() : $process->getErrorOutput());
        }

        return $process->isSuccessful() ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Get the values to be passed through to a DDEV command based on the input arguments/options
     */
    public static function getPassThroughArgsForInput(InputInterface $input, array $forbiddenOptions = null): array
    {
        $passThrough = [];

        // Grab all arguments to be passed through
        $args = $input->getArguments();
        foreach ($args as $name => $value) {
            if ($name === 'command') {
                continue;
            }

            if (is_array($value)) {
                $passThrough += $value;
            } else {
                $passThrough[] = $value;
            }
        }

        // Grab all options to be passed through
        $options = $input->getOptions();
        $ignoreOptions = $forbiddenOptions ?? ['long' => []];
        foreach ($options as $name => $value) {
            if ($value === self::NULL_OPTION_VALUE || in_array($name, $ignoreOptions['long'])) {
                continue;
            }

            if ($value) {
                $passThrough[] = "--{$name}={$value}";
            } else {
                $passThrough[] = "--{$name}";
            }
        }

        return $passThrough;
    }

    protected function configure()
    {
        // Add the one global flag we know DDEV has - easier to just hardcode this rather than fetch it dynamically.
        // If they add more later, it might make sense to fetch these from `ddev -h`
        $this->addOption('json-output', 'j', InputOption::VALUE_OPTIONAL, 'If true, user-oriented output will be in JSON format.', self::NULL_OPTION_VALUE);
    }

    /**
     * Get the definition for this command from DDev
     */
    private function initFromDdev(): void
    {
        if ($this->initialised) {
            return;
        }

        $appName = $this->getApplication()?->getName();
        $regexSafeCmdName = preg_quote($this->getName(), '/');

        $helpOutput = DDevHelper::runJson('help', [$this->getName()]);

        // Add help information
        $help = $helpOutput->LongDescription ?? '';
        if ($helpOutput->Example) {
            // Replace "ddev <commandName>" with the wrapper execution, and add examples to help info.
            $newName = $appName ? "$appName {$this->getName()}" : $this->getName();
            $help .= "\n\nExamples:\n" . trim(preg_replace('/ddev ' . $regexSafeCmdName . '/', $newName, $helpOutput->Example));
        }
        $this->setHelp($help);

        // Add aliases
        if (is_array($helpOutput->Aliases)) {
            $this->setAliases(array_diff($helpOutput->Aliases, [$this->getName()]));
        }

        // Add usages
        if ($helpOutput->Usage) {
            // Remove the "ddev <commandName"> since that'll be added automagically by symfony console.
            $usages = explode("\n", preg_replace('/ddev ' . $regexSafeCmdName . '/', '', $helpOutput->Usage));
            foreach ($usages as $usage) {
                $this->addUsage(trim($usage));
            }
        }

        // Add options (flags, not arguments)
        if (is_array($helpOutput->Flags)) {
            $forbiddenFlags = $this->getApplication()?->getForbiddenOptions() ?? ['long' => [], 'short' => []];
            foreach ($helpOutput->Flags as $flag) {
                // We get info about the help flag for free with symfony console.
                if (strtolower($flag->Name) === 'help' || $flag->Shorthand === 'h') {
                    continue;
                }

                // Throw a wobbly if we get a conflict - then I'll know I need to do something about it.
                if (in_array(strtolower($flag->Name), $forbiddenFlags['long']) || in_array($flag->Shorthand, $forbiddenFlags['short'])) {
                    throw new RuntimeException("Conflict between symfony console default option and the '--{$flag->Name}' option for command {$this->getName()}");
                }

                $this->addOption($flag->Name, $flag->Shorthand, InputOption::VALUE_OPTIONAL, $flag->Usage, self::NULL_OPTION_VALUE);
            }
        }

        // We can't easily detect which commands should allow arguments, so lets just allow an array of arguments.
        // Note that while some commands have sub-commands which would be arguments, those aren't the only acceptable arguments,
        // e.g. composer and exec can both have variable arguments.
        $this->addArgument(
            'arguments',
            InputArgument::IS_ARRAY | InputArgument::OPTIONAL,
            'Arguments to be passed through to the DDEV command, if any. See help below.',
            null,
            // Pass completion request through to ddev in case there are sub-commands or valid arguments.
            // Note that because this is an array type (to account for possible multi-nested commands), it will
            // give the same value multiple times (e.g. ddev auth \t ssh \t ssh)
            function (CompletionInput $input) {
                $ddevCompletion = DDevHelper::run('__complete', [$this->getName(), $input->getCompletionValue()]);
                return array_filter(explode("\n", $ddevCompletion), fn ($option) => !str_starts_with($option, ':'));
            }
        );

        $this->initialised = true;
    }
}
