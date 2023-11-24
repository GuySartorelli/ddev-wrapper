<?php declare(strict_types=1);

namespace GuySartorelli\DdevWrapper\Application;

use GuySartorelli\DdevWrapper\Command\PassThroughCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Descriptor\TextDescriptor as BaseTextDescriptor;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Process\Process;

/**
 * A subclass of TextDescriptor that handles pass-through commands appropriately.
 */
class TextDescriptor extends BaseTextDescriptor
{
    /**
     * Temporarily displaces the placeholder null default value of pass-through commands, in case
     * this method is called from {@see describeApplication()}.
     *
     * @inheritDoc
     */
    protected function describeInputOption(InputOption $option, array $options = []): void
    {
        // Temporarily set default to a true "null" while we're describing it.
        $default = $option->getDefault();
        if ($default === PassThroughCommand::NULL_OPTION_VALUE) {
            $option->setDefault(null);
        }

        // Print description to console.
        parent::describeInputOption($option, $options);

        // Set the default back, in case it's needed for something else.
        if ($default === PassThroughCommand::NULL_OPTION_VALUE) {
            $option->setDefault($default);
        }
    }

    /**
     * Outputs the DDEV help info for pass-through commands.
     * Uses default description for everything else.
     *
     * @inheritDoc
     */
    protected function describeCommand(Command $command, array $options = []): void
    {
        if ($command instanceof PassThroughCommand) {
            $process = new Process(['ddev', $command->getName(), '-h']);
            $process->run();
            $output = $process->getOutput();

            // Replace "ddev <commandName>" with the wrapper execution, and add examples to help info.
            $appName = $command->getApplication()?->getName();
            $commandName = $command->getName();
            $regexSafeCmdName = preg_quote($commandName, '/');
            $newName = $appName ? "$appName $commandName" : $commandName;
            $description = preg_replace('/ddev ' . $regexSafeCmdName . '/', $newName, $output);

            // Output to terminal
            $this->writeText($description, $options);
        } else {
            parent::describeCommand($command, $options);
        }
    }

    /**
     * Directly copied from the parent class.
     */
    private function writeText(string $content, array $options = []): void
    {
        $this->write(
            isset($options['raw_text']) && $options['raw_text'] ? strip_tags($content) : $content,
            isset($options['raw_output']) ? !$options['raw_output'] : true
        );
    }
}
