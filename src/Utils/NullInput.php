<?php

namespace Tuchsoft\IssueReporter\Utils;

use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;

class NullInput implements InputInterface {

    public function setOption(string $name, $value)
    {
        return false;
    }

    public function getFirstArgument()
    {
        return false;
    }

    public function hasParameterOption($values, bool $onlyParams = false)
    {
        return false;
    }

    public function getParameterOption($values, $default = false, bool $onlyParams = false)
    {
        return false;
    }

    public function bind(InputDefinition $definition)
    {
        return false;
    }

    public function validate()
    {
        return false;
    }

    public function getArguments()
    {
        return false;
    }

    public function getArgument(string $name)
    {
        return false;
    }

    public function setArgument(string $name, $value)
    {
        return false;
    }

    public function hasArgument(string $name)
    {
        return false;
    }

    public function getOptions()
    {
        return false;
    }

    public function getOption(string $name)
    {
        return false;
    }

    public function hasOption(string $name)
    {
        return false;
    }

    public function isInteractive()
    {
        return false;
    }

    public function setInteractive(bool $interactive)
    {
        return false;
    }
}
