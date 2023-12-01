<?php declare(strict_types=1);

namespace GuySartorelli\DdevWrapper\Command;

use RuntimeException;
use Symfony\Component\Console\Command\Command;
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

    public function __construct(string $name, string $description)
    {
        $this->setDescription($description);
        parent::__construct($name);
    }

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
        $ignoreOptions = $this->getApplication()?->getForbiddenOptions() ?? ['long' => []];
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

    protected function configure()
    {
        // Add the one global flag we know DDEV has - easier to just hardcode this rather than fetch it dynamically.
        // If they add more later, it might make sense to fetch these from `ddev -h`
        $this->addOption('json-output', 'j', description: 'If true, user-oriented output will be in JSON format.');
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

        $process = new Process(['ddev', $this->getName(), '-h']);
        $process->run();
        $output = $process->getOutput();

        // Add help information
        preg_match('/^.+?\n/', $output, $matches);
        $help = trim($matches[0] ?? '');
        $examplesSection = $this->getSectionFromDdev('Examples', $output);
        if ($examplesSection) {
            // Replace "ddev <commandName>" with the wrapper execution, and add examples to help info.
            $newName = $appName ? "$appName {$this->getName()}" : $this->getName();
            $help .= "\n\nExamples:\n" . trim(preg_replace('/ddev ' . $regexSafeCmdName . '/', $newName, $examplesSection));
        }
        $this->setHelp($help);

        // Add aliases
        $aliasSection = $this->getSectionFromDdev('Aliases', $output);
        if ($aliasSection) {
            $aliases = explode(',', str_replace(' ', '', $aliasSection));
            $aliases = array_diff($aliases, [$this->getName()]);
            $this->setAliases($aliases);
        }

        // Add usages
        $usageSection = $this->getSectionFromDdev('Usage', $output);
        if ($usageSection) {
            // Remove the "ddev <commandName"> since that'll be added automagically by symfony console.
            $usages = explode("\n", preg_replace('/ddev ' . $regexSafeCmdName . '/', '', $usageSection));
            foreach ($usages as $usage) {
                $this->addUsage(trim($usage));
            }
        }

        // Add options (flags, not arguments)
        $flagSection = $this->getSectionFromDdev('Flags', $output);
        if ($flagSection) {
            $flagRegex = '/^\h*(?>-(?<shortFlag>[a-zA-Z]),?)?\h*--(?<longFlag>[a-zA-Z-]+)\h+(?<description>[^\v]*)$/m';
            $hasValidFlags = preg_match_all($flagRegex, $flagSection, $matches);
            if ($hasValidFlags) {
                $forbiddenFlags = $this->getApplication()?->getForbiddenOptions() ?? ['long' => [], 'short' => []];
                for ($i = 0; $i < count($matches[0]); $i++) {
                    $shortFlag = $matches['shortFlag'][$i] ?? null;
                    $longFlag = $matches['longFlag'][$i];
                    $description = trim($matches['description'][$i]);

                    // We get info about the help flag for free with symfony console.
                    if (strtolower($longFlag) === 'help' || $shortFlag === 'h') {
                        continue;
                    }

                    // Throw a wobbly if we get a conflict - then I'll know I need to do something about it.
                    if (in_array(strtolower($longFlag), $forbiddenFlags['long']) || in_array($shortFlag, $forbiddenFlags['short'])) {
                        throw new RuntimeException("Conflict between symfony console default option and the '--$longFlag' option for command {$this->getName()}");
                    }

                    $this->addOption($longFlag, $shortFlag, InputOption::VALUE_OPTIONAL, $description, self::NULL_OPTION_VALUE);
                }
            }
        }

        // We can't easily detect which commands should allow arguments, so lets just allow an array of arguments.
        $this->addArgument('arguments', InputArgument::IS_ARRAY | InputArgument::OPTIONAL, 'Arguments to be passed through to the DDEV command, if any. See help below.');

        $this->initialised = true;
    }

    private function getSectionFromDdev(string $section, string $output): ?string
    {
        $regexSafeSection = preg_quote($section, '/');
        $hasSection = preg_match('/(?>(?>\v|^)' . $regexSafeSection . ':\v)(.+?(?=(?>\v{2}|$)))/s', $output, $matches);
        return $hasSection ? $matches[1] : null;
    }
}
