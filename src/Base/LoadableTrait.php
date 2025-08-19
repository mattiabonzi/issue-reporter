<?php

namespace Tuchsoft\IssueReporter\Base;

use Symfony\Component\Console\Input\InputOption;

trait LoadableTrait
{

    static public abstract function getOptionsDefinition(int $returnType = LoadableInterface::OPTIONS_NORMAL): array;

    protected array $options = [];

    static function getName(): string
    {
        $splitted = explode('\\', static::class);
        return strtolower(array_pop($splitted));
    }

    public function setOptions(array $input): void
    {
        $inputKeys = array_keys($input);
        foreach ($this->getOptionsDefinition(LoadableInterface::OPTIONS_BOTH) as $option) {
            $name = $option->getName();
            if (!str_contains($option->getName(), static::getName())) {
                if (in_array("{$option->getName()}-$name", $inputKeys)) {
                    continue;
                }
            } else {
                $name = str_replace(static::getName().'-', '', $name);
            }
            if (isset($this->options[$name]) && !isset($input[$option->getName()])) {
                continue;
            }
            $this->options[$name] = $input[$option->getName()] ?? $option->getDefault();
        }
    }

    static protected function newOption(string $name, int $mode, string $desc, mixed $default, int $returnType = LoadableInterface::OPTIONS_NORMAL): array
    {
        $options = [];
        if ($returnType == LoadableInterface::OPTIONS_NORMAL || $returnType == LoadableInterface::OPTIONS_BOTH) {
            $options[] = new InputOption($name, '', $mode, $desc, $default);
        }
        if ($returnType == LoadableInterface::OPTIONS_PREFIX || $returnType == LoadableInterface::OPTIONS_BOTH) {
            $options[] = new InputOption(self::getName() . "-$name", '', $mode, $desc, $default);
        }
        return $options;
    }

}