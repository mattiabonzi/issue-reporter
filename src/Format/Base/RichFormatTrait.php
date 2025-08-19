<?php

namespace Tuchsoft\IssueReporter\Format\Base;

use Symfony\Component\Console\Input\InputOption;
use Tuchsoft\IssueReporter\Report;

trait RichFormatTrait
{

    static public function getRichOptions(int $returnType = self::OPTIONS_NORMAL):array {
        return[
            ...self::newOption('max-width', InputOption::VALUE_OPTIONAL, 'Max line width (in character) of the output, 0 means no wrap, in tabled output is used as per-column width', 0, $returnType),
            ...self::newOption('color', InputOption::VALUE_NEGATABLE, 'Force (or disable --no-color) colored output (ANSI)', true, $returnType),
            ...self::newOption('emoji', InputOption::VALUE_NEGATABLE, 'Force (or disable --no-emoji) emoji output', true,$returnType),
        ];
    }

    static public function getOptionsDefinition(int $returnType = self::OPTIONS_NORMAL):array {
        return[
           ...parent::getOptionsDefinition($returnType),
            ...self::getRichOptions($returnType)
        ];
    }

    protected function isColored(): bool {
        return $this->options['color'];
    }
    protected function areEmojiActive(): bool {
        return $this->options['emoji'];
    }

    protected function getSeverityEmoji(int $severity): string
    {
        if (!$this->areEmojiActive()) return '';
        return match ($severity) {
            Report::SEVERITY_ERROR =>  "\u{274C}  ",
            Report::SEVERITY_WARNING => "\u{26A0}\u{FE0F} ",
            Report::SEVERITY_TIP => "\u{1F4A1} "
        };
    }

}