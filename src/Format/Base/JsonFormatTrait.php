<?php

namespace Tuchsoft\IssueReporter\Format\Base;

use Symfony\Component\Console\Input\InputOption;

trait JsonFormatTrait {
    public static function getJsonOptions(int $returnType = self::OPTIONS_NORMAL):array {
        return [
            ...self::newOption('pretty', InputOption::VALUE_NEGATABLE, 'Force (or disable --no-color) prettied output', false, $returnType),
            ...self::newOption('escape-slash', InputOption::VALUE_NEGATABLE, 'Force (or disable --no-color) prettied output', true, $returnType),
            ...self::newOption('escape-unicode', InputOption::VALUE_NEGATABLE, 'Force (or disable --no-color) prettied output', true, $returnType),
            ];
    }

    public static function getOptionsDefinition(int $returnType = self::OPTIONS_NORMAL):array {
        return [
            ...parent::getOptionsDefinition($returnType),
            ...self::getJsonOptions($returnType)
        ];
    }


    protected function jsonEncode(mixed $value):string {
        $flags =
            ($this->options['pretty'] ? JSON_PRETTY_PRINT : 0) |
            (!$this->options['escape-slash'] ? JSON_UNESCAPED_SLASHES : 0) |
            (!$this->options['escape-unicode'] ? JSON_UNESCAPED_UNICODE : 0);
        return json_encode($value, $flags, 1024);
    }

    public static function getFormat(): string
    {
        return self::FORMAT_JSON;
    }
}