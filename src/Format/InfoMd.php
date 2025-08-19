<?php

namespace Tuchsoft\IssueReporter\Format;

// The TableBuilder class is not needed for this library.
use Tuchsoft\IssueReporter\Format\Base\AbstractFormat;
use Tuchsoft\IssueReporter\Format\Base\InfoFormatTrait;
use Tuchsoft\IssueReporter\Format\Base\MdFormatTrait;
use Tuchsoft\IssueReporter\Format\Base\NativeFormatInterface;
use Tuchsoft\IssueReporter\Report;

class InfoMd extends AbstractFormat implements NativeFormatInterface
{
    use InfoFormatTrait;
    use MdFormatTrait;

    public function __construct(array $options = [])
    {
        parent::__construct($options);
        $this->initMd($options);
    }

    /**
     * Generates a Markdown report from a Report object.
     *
     * @param Report $report The report data to format.
     * @return string The Markdown string of the formatted report.
     */
    public function generate(Report $report): string
    {
        // Use MarkdownBuilder's h1() method for the main title.
        $this->builder->h1($report->getName());

        $this->generateSummary($report);
        $this->generateDetails($report);

        // The getMarkdown() method retrieves the final, built Markdown string.

        return $this->writeMd();
    }

    /**
     * Generates the summary section of the report.
     *
     * @param Report $report The report data.
     */
    protected function generateSummary(Report $report): void
    {
        // Assumes a getSummary() method exists and returns headers and rows.
        list($headers, $rows) = $this->getSummary($report);

        // Call the table method directly with headers and rows as arguments.
        $this->builder
            ->table($headers, $rows)
            ->hr();
    }

    /**
     * Generates the detailed issues section of the report.
     *
     * @param Report $report The report data.
     */
    protected function generateDetails(Report $report): void
    {
        if (!$report->hasIssues()) {
            // Replaced custom success() with a simple text block.
            $this->builder->p('No issues found. Everything looks good!');
            return;
        }

        // Use MarkdownBuilder's h2() for a section heading.
        $this->builder->h2('Detailed Issues');

        // Assumes a getDetails() method exists and returns an array of tables.
        $tables = $this->getDetails($report);
        foreach ($tables as $path => $table) {
            list($headers, $rows) = $table;

            // Replaced custom <info> with standard Markdown bolding.
            $this->builder->p("File: **{$path}**");

            // Call the table method directly with headers and rows as arguments.
            $this->builder->table($headers, $rows);

            // Add a simple line break between tables.
            $this->builder->br();
        }
        $this->builder->br();
    }

    /**
     * Returns a brief description of the report format.
     *
     * @return string
     */
    public static function getDesc(): string
    {
        return "Pretty formatted detailed info";
    }
}
