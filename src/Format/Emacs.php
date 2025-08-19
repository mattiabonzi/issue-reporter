<?php

namespace Tuchsoft\IssueReporter\Format;

use Tuchsoft\IssueReporter\Format\Base\AbstractFormat;
use Tuchsoft\IssueReporter\Format\Base\ParsableFormatInterface;
use Tuchsoft\IssueReporter\Issue;
use Tuchsoft\IssueReporter\Report;
use Tuchsoft\IssueReporter\Utils\Path;

/**
 * An implementation of a report format that serializes and deserializes
 * a Report object to and from a simple Emacs-style text format.
 */
class Emacs extends AbstractFormat implements ParsableFormatInterface
{



    /**
     * Generates a multiline string in the Emacs-style format from a Report object.
     *
     * Format: /path/to/file.ext:line:column: severity - message (code)
     *
     * @param Report $report The report object to serialize.
     * @return string The formatted multiline string.
     */
    public function generate(Report $report): string
    {
        $outputLines = [];
        $issuesByPath = $report->getIssues();

        foreach ($issuesByPath as $path => $issues) {
            /** @var Issue $issue */
            foreach ($issues as $issue) {
                // Map internal severity to the required string
                $severityString = match ($issue->getSeverity()) {
                    Report::SEVERITY_ERROR => 'error',
                    Report::SEVERITY_WARNING, Report::SEVERITY_TIP => 'warning',
                    default => 'warning',
                };

                $outputLines[] = sprintf(
                    "%s:%d:%d: %s - %s (%s)",
                    $path,
                    $issue->getLine(),
                    $issue->getColumn(),
                    $severityString,
                    $issue->getMessage(),
                    $issue->getCode()
                );
            }
        }

        return implode("\n", $outputLines);
    }

    /**
     * Parses a multiline string in the Emacs-style format and returns a Report object.
     *
     * @param string $input The text string to parse.
     * @param string $name The name for the new Report object.
     * @return Report The parsed Report object.
     * @throws \InvalidArgumentException If the input format is invalid.
     */
    public function parse(string $input, ?string $name = null): Report
    {
        if (!$name) {
            $name = static::getDefaultReportName();
        }
        $lines = explode("\n", $input);
        $flatIssues = [];
        $allPath = [];

        foreach ($lines as $line) {
            if (empty(trim($line))) {
                continue;
            }

            // Regex to match the Emacs-style format
            // Group 1: path, Group 2: line, Group 3: column, Group 4: severity, Group 5: message, Group 6: code
            $pattern = '/^([^:]+):(\d+):(\d+): (error|warning) - (.+) \((.+)\)$/';
            if (preg_match($pattern, $line, $matches)) {
                $allPath[] = $matches[1];
                $flatIssues[] = [
                    'message' => $matches[5],
                    'line' => (int)$matches[2],
                    'column' => (int)$matches[3],
                    'path' => $matches[1],
                    'code' => $matches[6],
                    'severity' => match ($matches[4]) {
                        'error' => Report::SEVERITY_ERROR,
                        default => Report::SEVERITY_WARNING,
                    },
                ];
            }
        }

        $reportData = [
            'name' => $name,
            'issues' => $flatIssues,
            'subReports' => [],
            'timeStart' => 0,
            'timeEnd' => 0,
            'basePath' => Path::findCommonBasePath($allPath),
        ];

        return Report::fromJson($reportData);
    }

    /**
     * @return string The description of the format.
     */
    public static function getDesc(): string
    {
        return 'Emacs-style text representation for static analysis reports';
    }

    public static function getFormat(): string
    {
        return self::FORMAT_TXT;
    }

    public static function supports(): array
    {
        return [
            self::FEATURE_ISSUE_COLUMN,
            self::FEATURE_ISSUE_CODE,
            self::FEATURE_ISSUE_LINE];
    }

    public static function supportsExtra(): array
    {
        return [
            self::FEATURE_ISSUE_REF,
            self::FEATURE_ISSUE_HELP
        ];
    }
}
