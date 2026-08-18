<?php

namespace Tuchsoft\IssueReporter\Format\Base;

use Symfony\Component\Console\Input\InputOption;
use Tuchsoft\IssueReporter\Base\LoadableTrait;
use Tuchsoft\IssueReporter\Issue;

/**
 * An abstract base class for all report formatters.
 *
 * This class provides common functionality that can be shared across different
 * format implementations, such as option handling and severity level to string
 * conversion. It implements the FormatInterface and uses the LoadableTrait.
 */
abstract class AbstractFormat implements FormatInterface
{

    use LoadableTrait;

    /**
     * AbstractFormat constructor.
     *
     * @param array<string, mixed> $options An array of options to configure the formatter.
     */
    public function __construct(array $options = []) {
        $this->setOptions($options);
    }


    /**
     * {@inheritdoc}
     *
     * Defines common options for controlling the output, such as showing help and reference links.
     */
    static public function getOptionsDefinition(int $returnType = self::OPTIONS_NORMAL):array {
        return[
            ...self::newOption('show-ref', InputOption::VALUE_NEGATABLE, 'Show (or don\'t show --no-show-ref) external reference field', false, $returnType),
            ...self::newOption('show-help', InputOption::VALUE_NEGATABLE, 'Show (or don\'t show --no-show-help) help (fix) field', true, $returnType),
        ];
    }

    /**
     * Return the severity mappings for this format
     * It MUST return an array with 3 items, 1 for each severity level
     * @return array Issue::severity => text value
     */
    static protected function getSeverityMap(): array {
        return [
            Issue::SEVERITY_ERROR => 'ERROR',
            Issue::SEVERITY_WARNING => 'WARNING',
            Issue::SEVERITY_TIP => 'TIP'
        ];
    }

    /**
     * Gets a string representation for a given severity level.
     * Format MUST override getSeverityMap() for custom value
     *
     * @param ?int $severity The severity level (e.g., Issue::SEVERITY_ERROR).
     * @return string The string representation of the severity (e.g., 'ERROR').
     */
    protected function getSeverity(?int $severity): string
    {
        $map = static::getSeverityMap();
        return $map[$severity] ?? $map[Issue::SEVERITY_DEFAULT];
    }

    /**
     * Parse the severity string into an Issue constant
     * Format MUST override getSeverityMap() for custom value
     *
     * @param ?string $severity
     * @return int
     */
    protected function parseSeverity(?string $severity): int
    {
        return array_flip(static::getSeverityMap())[$severity] ?? Issue::SEVERITY_DEFAULT;
    }


    /**
     * {@inheritdoc}
     */
    public static function getDefaultReportName(): string
    {
        return 'Parsed '.self::getName().' report';
    }


}