<?php declare(strict_types=1);

namespace GuySartorelli\DdevWrapper\Command;

use GuySartorelli\DdevWrapper\Application\TextDescriptor;
use ReflectionProperty;
use Symfony\Component\Console\Command\HelpCommand;
use Symfony\Component\Console\Helper\DescriptorHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Help extends HelpCommand
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

        $helper = new DescriptorHelper();
        $helper->register('txt', new TextDescriptor());
        $helper->describe($output, $command, [
            'format' => $input->getOption('format'),
            'raw_text' => $input->getOption('raw'),
        ]);

        unset($command);

        return 0;
    }
}
