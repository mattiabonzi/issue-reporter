<?php

namespace Tuchsoft\IssueReporter\Format;

use Tuchsoft\IssueReporter\Format\Base\AbstractFormat;
use Tuchsoft\IssueReporter\Format\Base\JsonFormatTrait;
use Tuchsoft\IssueReporter\Format\Base\ParsableFormatInterface;
use Tuchsoft\IssueReporter\Format\Base\ParsableMessageFormatTrait;
use Tuchsoft\IssueReporter\Issue;
use Tuchsoft\IssueReporter\Report;
use Tuchsoft\IssueReporter\Utils\Path;

class PhpCs extends AbstractFormat implements ParsableFormatInterface
{
    use JsonFormatTrait;
    use ParsableMessageFormatTrait;

    public function generate(Report $report): string
    {
        $output = [
            'totals' => [
                'errors' => $report->getTotalErrors(),
                'warnings' => $report->getTotalWarnings() + $report->getTotalTips(),
                'fixable' => 0,
            ],
            'files' => []
        ];

        foreach ($report->getIssues() as $path => $issues) {
            $errorsCount = 0;
            $warningsCount = 0;
            $messages = [];
            /** @var Issue $issue */
            foreach ($issues as $issue) {
                if ($issue->getSeverity() === Issue::SEVERITY_ERROR) {
                    $messageType = 'ERROR';
                    $errorsCount++;
                } else {
                    $messageType = 'WARNING';
                    $warningsCount++;
                }

                // --- Start of Fixes ---
                // 1. Build the message in a temporary variable first.
                $messageData = [
                    'message'  => $issue->getMessage(),
                    'severity' => $issue->getSeverity(),
                    'fixable'  => false, // FIXME: add support for autofixer
                    'type'     => $messageType,
                    'line'     => $issue->getLine(),
                    'column'   => $issue->getColumn(),
                    'source'   => $issue->getCode(),
                ];

                // 2. Optionally append help and ref info from the $issue object.
                if ($this->options['show-help'] && $issue->getHelp()) {
                    $messageData['message'] .= " ({$issue->getHelp()})";
                }
                if ($this->options['show-ref'] && $issue->getRef()) {
                    $messageData['message'] .= " [{$issue->getRef()}]";
                }

                $messages[] = $messageData;

            }

            $output['files'][$path] = [
                'errors'   => $errorsCount,
                'warnings' => $warningsCount,
                'messages' => $messages,
            ];
        }
        return $this->jsonEncode($output);
    }

    public function parse(string $input, ?string $name = null): Report
    {
        if (!$name) {
            $name = static::getDefaultReportName();
        }
        $data = json_decode($input, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \InvalidArgumentException('Invalid JSON input: ' . json_last_error_msg());
        }

        if (!is_array($data)) {
            throw new \InvalidArgumentException('Decoded JSON is not an array.');
        }

        if (!isset($data['files']) || !is_array($data['files'])) {
            throw new \InvalidArgumentException('Decoded JSON is not valid.');
        }


        $flatIssues = [];
        foreach ($data['files'] as $filePath => $fileReport) {
            if (!is_array($fileReport) || !isset($fileReport['messages'])) {
                continue;
            }

            foreach ($fileReport['messages'] as $issueData) {
                $issue = [
                    'message' => $issueData['message'],
                    'line' => $issueData['line'] ?? 1,
                    'column' => $issueData['column'] ?? 1,
                    'path' => $filePath,
                    'code' => $issueData['source'] ?? Issue::UNKNOW_CODE,
                    'severity' => match ($issueData['type']) {
                        'ERROR' => Issue::SEVERITY_ERROR,
                        'WARNING' => Issue::SEVERITY_WARNING
                    }
                ];
                if ($this->options['parse-message'] ?? false) {
                    $parsed = $this->parseMessage($issue['message']);

                    $issue['message'] = $parsed['message'] ?? $issue['message'];
                    $issue['help'] = $parsed['help'] ?? null;
                    $issue['ref'] = $parsed['ref'] ?? null;
                }
                $flatIssues[] = $issue;
            }
        }



        $reportData = [
            'name' => $name,
            'issues' => $flatIssues,
            'basePath' => Path::findCommonBasePath(array_keys($data['files'] )),
            'subReports' => [],
            'timeStart' => 0,
            'timeEnd' => 0,
        ];

        return Report::fromJson($reportData);
    }

    static function getDesc(): string
    {
        return "Php Code Sniffer JSON representation";
    }


    public static function supports(): array
    {
        return [
            self::FEATURE_PARSABLE_MESSAGE,
            self::FEATURE_ISSUE_COLUMN,
            self::FEATURE_ISSUE_LINE,
            self::FEATURE_ISSUE_CODE
        ];
    }

    public static function supportsExtra(): array
    {
        return [
            self::FEATURE_ISSUE_HELP,
            self::FEATURE_ISSUE_REF
        ];
    }
}