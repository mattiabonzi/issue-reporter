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

    protected  const EMACS_REGEX = "/^(?<path>[^:]+):(?:(?<line>\d*):)?(?:(?<col>\d*):)?\s(?<severity>[a-z]+)\s-\s(?<message>.+)$/";
    protected const EXTRA_REGEX = "/(?:\s{2,}\((?<help>.+)\))?(?:\s{2,}\[(?<ref>.+)\])?/";
    protected const CODE_REGEX = "/\(#(?<code>[a-zA-Z0-9-_\.]+?)\)/";
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

        foreach ($report->getIssues(false) as $issue) {
            $severity = match ($issue->getSeverity()) {
                Issue::SEVERITY_ERROR => 'error',
                Issue::SEVERITY_WARNING, => 'warning',
                Issue::SEVERITY_TIP => 'info',
                default => Issue::SEVERITY_DEFAULT,
            };

            $line = sprintf(
                "%s:%d:%d: %s - %s (#%s)",
                $issue->getPath(),
                $issue->getLine(),
                $issue->getColumn(),
                $severity,
                $issue->getMessage(),
                $issue->getCode()
            );

            if ($this->options['show-help'] && $issue->getHelp()) {
                $line .= "  ({$issue->getHelp()})";
            }
            if ($this->options['show-ref'] && $issue->getRef()) {
                $line .= "  [{$issue->getRef()}]";
            }

            $outputLines[] = $line;
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

            $parsed = $this->parseMessage($line);

            if (isset($parsed['message'])) {
                $allPath[] = $parsed['path'];
                $issueData = [
                    'message' => $parsed['message'],
                    'line' => (int)$parsed['line'],
                    'column' => (int)$parsed['col'],
                    'path' => $parsed['path'],
                    'code' => $parsed['code'] ?? Issue::UNKNOW_CODE,
                    'severity' => match ($parsed['severity']) {
                        'error' => Issue::SEVERITY_ERROR,
                        'warning' => Issue::SEVERITY_WARNING,
                        'info' => Issue::SEVERITY_TIP,
                        default => Issue::SEVERITY_WARNING,
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


    private function parseMessage($msg): array
    {
        $match = [];
        $parsed = [];
        if (preg_match(self::CODE_REGEX, $msg, $match)) {
            //Has custom IssueReport style
            $splitted = explode($match[0], $msg);
            $msg = $splitted[0];
            $parsed['code'] = $match['code'];
            if (preg_match(self::EXTRA_REGEX, $splitted[1], $match)) {
                $parsed['help'] = $match['help'] ?? null;
                $parsed['ref'] = $match['ref'] ?? null;
            }
        }

        //Standard emacs
        if (preg_match(self::EMACS_REGEX, $msg, $match)) {
            $parsed['path'] = $match['path'];
            $parsed['message'] = $match['message'];
            $parsed['severity'] = $match['severity'];
            $parsed['line'] = $match['line'] ?? null;
            $parsed['col'] = $match['col'] ?? null;
        }

        return array_map(fn($v) => $v === null ? null : trim($v), $parsed);
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
        return self::FEATURE_ISSUE_STANDARD;
    }

    public static function supportsExtra(): array
    {
        return [
            self::FEATURE_ISSUE_REF,
            self::FEATURE_ISSUE_HELP,
        ];
    }
}
