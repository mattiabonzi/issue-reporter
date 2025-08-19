<?php

namespace Tuchsoft\IssueReporter;



use Tuchsoft\IssueReporter\Format\Base\FormatInterface;

class Reporter
{

    public static function registerFormats(string ...$formats): void
    {
        foreach ($formats as $format) {
            Factory::register(Factory::FORMAT, $format);
        }
    }


    public static function getOptionsDefinition(int $returnType = FormatInterface::OPTIONS_NORMAL): array {
        $output = [];
        foreach (Factory::getRegistered(Factory::FORMAT) as $format) {
            foreach ($format::getOptionsDefinition($returnType) as $option) {
                $output[$option->getName()] = $option;
            }
        }
        return array_values($output);
    }


    /**
     * Generates and prints a single final report.
     *
     * @param string $reportType The report type to print (e.g., 'full', 'json').
     *
     * @return void
     */
    public static function printReport(Report $report, string $reportType, string $output = 'php://stdout', array $options = []): void
    {

        foreach (Factory::getRegistered(Factory::TRANSFORMER) as $name => $transformer) {
            if ($transformer::isEnabled($options)) {
                $transformerClass = Factory::create(Factory::TRANSFORMER, $name, $options);
                $transformerClass->transform($report);
            }
        }

        $formatClass = Factory::create(Factory::FORMAT, $reportType, $options);
        file_put_contents($output, $formatClass->generate($report));
    }
}