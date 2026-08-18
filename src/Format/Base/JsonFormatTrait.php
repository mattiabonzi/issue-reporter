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

    protected function jsonDecode(string $json, bool $associative):mixed {
        if (empty(trim($json))) {
            throw new \InvalidArgumentException('Invalid JSON input: empty string');
        }

        $value =  json_decode($json, $associative, 1024, JSON_INVALID_UTF8_SUBSTITUTE );

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \InvalidArgumentException('Invalid JSON input: ' . json_last_error_msg());
        }

        if ($value === null) {
            throw new \InvalidArgumentException('Invalid JSON input: Unknow error (input deserialized into NULL)');
        }

        return $value;
    }



    public static function getFormat(): string
    {
        return self::FORMAT_JSON;
    }
}