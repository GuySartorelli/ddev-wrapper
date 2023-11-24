<?php declare(strict_types=1);

namespace GuySartorelli\DdevWrapper\Application;

use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;

/**
 * Decorates an input and asserts that it does NOT have --version or -V
 *
 * This is required to allow commands to have a version option, 'cause symfony console is hyper opinionated about that.
 */
class VersionlessInput implements InputInterface
{
    public function __construct(private InputInterface $input) {}

    public function __toString()
    {
        return $this->input->__toString();
    }

    public function getFirstArgument(): ?string
    {
        return $this->input->getFirstArgument();
    }

    public function hasParameterOption(string|array $values, bool $onlyParams = false): bool
    {
        if (is_string($values) && ($values === '--version' || $values === '-V')) {
            return false;
        }
        if (is_array($values) && (in_array('--version', $values) || in_array('-V', $values))) {
            return false;
        }
        return $this->input->hasParameterOption($values, $onlyParams);
    }

    public function getParameterOption(string|array $values, string|bool|int|float|array|null $default = false, bool $onlyParams = false)
    {
        return $this->input->getParameterOption($values, $default, $onlyParams);
    }

    public function bind(InputDefinition $definition)
    {
        return $this->input->bind($definition);
    }

    public function validate()
    {
        return $this->input->validate();
    }

    public function getArguments(): array
    {
        return $this->input->getArguments();
    }

    public function getArgument(string $name)
    {
        return $this->input->getArgument($name);
    }

    public function setArgument(string $name, mixed $value)
    {
        return $this->input->setArgument($name, $value);
    }

    public function hasArgument(string $name): bool
    {
        return $this->input->hasArgument($name);
    }

    public function getOptions(): array
    {
        return $this->input->getOptions();
    }

    public function getOption(string $name)
    {
        return $this->input->getOption($name);
    }

    public function setOption(string $name, mixed $value)
    {
        return $this->input->setOption($name, $value);
    }

    public function hasOption(string $name): bool
    {
        return $this->input->hasOption($name);
    }

    public function isInteractive(): bool
    {
        return $this->input->isInteractive();
    }

    public function setInteractive(bool $interactive)
    {
        return $this->input->setInteractive($interactive);
    }
}
