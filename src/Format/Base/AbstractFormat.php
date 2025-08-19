<?php

namespace Tuchsoft\IssueReporter\Format\Base;

use Symfony\Component\Console\Input\InputOption;
use Tuchsoft\IssueReporter\Base\LoadableTrait;
use Tuchsoft\IssueReporter\Report;

abstract class AbstractFormat implements FormatInterface
{

    use LoadableTrait;

    public function __construct(array $options = []) {
        $this->setOptions($options);
    }


    static public function getOptionsDefinition(int $returnType = self::OPTIONS_NORMAL):array {
        return[
            ...self::newOption('show-ref', InputOption::VALUE_NEGATABLE, 'Show (or don\'t show --no-show-ref) external reference field', false, $returnType),
            ...self::newOption('show-help', InputOption::VALUE_NEGATABLE, 'Show (or don\'t show --no-show-help) help (fix) field', true, $returnType),
            ...self::newOption('show-code', InputOption::VALUE_NEGATABLE, 'Show (or don\'t show --no-show-code) issue code field', true, $returnType),
        ];
    }


    protected function getSeverityIcon(int $severity): string
    {
        return match ($severity) {
                Report::SEVERITY_ERROR => 'ERROR',
                Report::SEVERITY_WARNING => 'WARNING',
                Report::SEVERITY_TIP => 'TIP'
            };
    }

    public static function getDefaultReportName(): string
    {
        return 'Parsed '.self::getName().' report';
    }


}