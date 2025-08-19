<?php

namespace Tuchsoft\IssueReporter\Format;

// The TableBuilder class is not needed for this library.
use Tuchsoft\IssueReporter\Format\Base\HtmlFormatTrait;
use Tuchsoft\IssueReporter\Format\Base\InfoFormatTrait;
use Tuchsoft\IssueReporter\Format\Base\NativeFormatInterface;
use Tuchsoft\IssueReporter\Report;

class InfoHtml extends InfoMd implements NativeFormatInterface
{
    use InfoFormatTrait;
    use HtmlFormatTrait;

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
        return $this->writeHtml();

    }

    /**
     * Returns a brief description of the report format.
     *
     * @return string
     */
    public static function getDesc(): string
    {
        return "Html detailed info";
    }
}
