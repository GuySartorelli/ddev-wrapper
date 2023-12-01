<?php declare(strict_types=1);

namespace GuySartorelli\DdevWrapper\Command;

use GuySartorelli\DdevWrapper\DDevHelper;
use ReflectionProperty;
use Symfony\Component\Console\Command\HelpCommand as BaseHelpCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class HelpCommand extends BaseHelpCommand
{
    private function getCommand()
    {
        $reflectionCommand = new ReflectionProperty(parent::class, 'command');
        $reflectionCommand->setAccessible(true);
        return $reflectionCommand->isInitialized($this) ? $reflectionCommand->getValue($this) : null;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $command = $this->getCommand();
        if (!$command) {
            $command = $this->getApplication()->find($input->getArgument('command_name'));
            $this->setCommand($command);
        }

        // Pass through commands should just pass through the help request.
        if ($command instanceof PassThroughCommand) {
            $ddevOutput = DDevHelper::run($command->getName(), ['-h', ...$this->getArgsAndFlags($input, $command)]);

            // Replace "ddev <commandName>" with the wrapper execution, and add examples to help info.
            $appName = $command->getApplication()?->getName();
            $commandName = $command->getName();
            $regexSafeCmdName = preg_quote($commandName, '/');
            $newName = $appName ? "$appName $commandName" : $commandName;
            $description = preg_replace('/ddev ' . $regexSafeCmdName . '/', $newName, $ddevOutput);

            // Output to terminal
            $output->write($description);
            return 0;
        }

        // Normal Symfony Console help for everything else.
        return parent::execute($input, $output);
    }

    private function getArgsAndFlags(InputInterface $input, PassThroughCommand $command): array
    {
        $passThrough = [];

        // Grab all arguments to be passed through
        $args = $input->getArguments();
        $commandName = '';
        foreach ($args as $name => $value) {
            if ($name === 'command') {
                $commandName = $value;
                if ($value === 'help') {
                    continue;
                }
            }
            if ($commandName === 'help' && $name === 'command_name') {
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
        foreach ($options as $name => $value) {
            if ($value === PassThroughCommand::NULL_OPTION_VALUE || $name === 'help') {
                continue;
            }

            // Don't pass options that aren't part of the command we're getting help for
            if (!$command->getDefinition()->hasOption($name)) {
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
}
