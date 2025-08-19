<?php

namespace Tuchsoft\IssueReporter\Format;

use Symfony\Component\Console\Input\InputOption;
use Tuchsoft\IssueReporter\Format\Base\AbstractFormat;
use Tuchsoft\IssueReporter\Format\Base\JsonFormatTrait;
use Tuchsoft\IssueReporter\Format\Base\ParsableFormatInterface;
use Tuchsoft\IssueReporter\Issue;
use Tuchsoft\IssueReporter\Report;
use Tuchsoft\IssueReporter\Utils\Path;

class PhpCs extends AbstractFormat implements ParsableFormatInterface
{
    use JsonFormatTrait;

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
                if ($issue->getSeverity() === Report::SEVERITY_ERROR) {
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
                ];

                // 2. Correctly add 'source' to the individual message array.
                if ($this->options['show-code']) {
                    $messageData['source'] = $issue->getCode();
                }

                // 3. Correctly append help and ref info from the $issue object.
                $messageSuffix = '';
                if ($this->options['show-help'] && $issue->getHelp()) {
                    $messageSuffix .= " ({$issue->getHelp()})";
                }

                if ($this->options['show-ref'] && $issue->getRef()) {
                    // 4. Fixed bug: Was using getHelp() instead of getRef().
                    $messageSuffix .= " [{$issue->getRef()}]";
                }

                if ($messageSuffix !== '') {
                    $messageData['message'] .= $messageSuffix;
                }

                $messages[] = $messageData;
                // --- End of Fixes ---
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
                        'ERROR' => Report::SEVERITY_ERROR,
                        'WARNING' => Report::SEVERITY_WARNING
                    }
                ];
                if ($this->options['parse-message']) {
                    $parsed = [];
                    preg_match( "/(?<message>.+?(?=[\(\[]))(?:\s?\((?<help>.+)\))?(?:\s?\[(?<ref>.+)\])?/", $issue['message'], $parsed);
                    $issue['message'] = trim($parsed['message']);
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

    public static function getOptionsDefinition(int $returnType = self::OPTIONS_NORMAL): array
    {
        return [
            ...parent::getOptionsDefinition($returnType),
            ...self::getJsonOptions($returnType),
            ...self::newOption('parse-message', InputOption::VALUE_NEGATABLE, 'try (or don\'t try --no-show-ref) to parse the message for help and ref field', false, $returnType)
            ];
    }

    public static function supports(): array
    {
        return [];
    }

    public static function supportsExtra(): array
    {
        return [];
    }
}