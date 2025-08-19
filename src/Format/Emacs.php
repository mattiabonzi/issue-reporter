<?php

namespace Tuchsoft\IssueReporter\Format;

use Tuchsoft\IssueReporter\Format\Base\AbstractFormat;
use Tuchsoft\IssueReporter\Format\Base\ParsableFormatInterface;
use Tuchsoft\IssueReporter\Format\Base\ParsableMessageFormatTrait;
use Tuchsoft\IssueReporter\Issue;
use Tuchsoft\IssueReporter\Report;
use Tuchsoft\IssueReporter\Utils\Path;

/**
 * An implementation of a report format that serializes and deserializes
 * a Report object to and from a simple Emacs-style text format.
 */
class Emacs extends AbstractFormat implements ParsableFormatInterface
{


    use ParsableMessageFormatTrait;

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

                $severityString = match ($issue->getSeverity()) {
                    Report::SEVERITY_ERROR => 'error',
                    Report::SEVERITY_WARNING, Report::SEVERITY_TIP => 'warning',
                    default => 'warning',
                };

                $outputLines[] = $this->getParsableMessage($issue, true, $severityString);
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
            $parsed = $this->parseMessage($line, true);
            if (isset($parsed['message'])) {
                $allPath[] = $parsed['path'];
                $issueData = [
                    'message' => $parsed['message'],
                    'line' => (int)$parsed['line'],
                    'column' => (int)$parsed['col'],
                    'path' => $parsed['path'],
                    'code' => $parsed['code'] ?? Issue::UNKNOW_CODE,
                    'severity' => match ($parsed['severity']) {
                        'error' => Report::SEVERITY_ERROR,
                        default => Report::SEVERITY_WARNING,
                    },
                ];

                if (isset($parsed['help'])) {
                    $issueData['help'] = $parsed['help'];
                }

                if (isset($parsed['ref'])) {
                    $issueData['ref'] = $parsed['ref'];
                }

                $flatIssues[] = $issueData;

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
            self::FEATURE_ISSUE_LINE];
    }

    public static function supportsExtra(): array
    {
        return [
            self::FEATURE_ISSUE_REF,
            self::FEATURE_ISSUE_HELP,
            self::FEATURE_ISSUE_CODE,
        ];
    }
}
