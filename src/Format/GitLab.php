<?php

namespace Tuchsoft\IssueReporter\Format;

use Tuchsoft\IssueReporter\Format\Base\AbstractFormat;
use Tuchsoft\IssueReporter\Format\Base\JsonFormatTrait;
use Tuchsoft\IssueReporter\Format\Base\ParsableFormatInterface;
use Tuchsoft\IssueReporter\Issue;
use Tuchsoft\IssueReporter\Report;

/**
 * An implementation of a report format that serializes and deserializes
 * a Report object to and from the GitLab Code Quality JSON format.
 */
class GitLab extends AbstractFormat implements ParsableFormatInterface
{

    use JsonFormatTrait;
    /**
     * Maps internal severity constants to GitLab's severity levels.
     * @see https://docs.gitlab.com/ee/user/project/merge_requests/code_quality.html#implementing-a-custom-tool
     */
    const SEVERITY_TO_GITLAB_SEVERITY_MAP = [
        Report::SEVERITY_ERROR => 'critical',
        Report::SEVERITY_WARNING => 'major',
        Report::SEVERITY_TIP => 'minor',
    ];

    /**
     * Generates a GitLab Code Quality JSON string from a Report object.
     *
     * @param Report $report The report object to serialize.
     * @return string The JSON string.
     */
    public function generate(Report $report): string
    {
        $issuesByPath = $report->getIssues();
        $gitlabIssues = [];

        foreach ($issuesByPath as $path => $issues) {
            /** @var Issue $issue */
            foreach ($issues as $issue) {
                // Map internal severity to GitLab's levels
                $severityString = self::SEVERITY_TO_GITLAB_SEVERITY_MAP[$issue->getSeverity()] ?? 'minor';

                // GitLab uses a fingerprint to identify unique issues across analysis runs.
                // It should be a unique hash of the path, line, and check name.
                $fingerprint = md5($path . $issue->getLine() . $issue->getCode() . $issue->getMessage());

                // The GitLab format expects a simple description and check_name.
                $description = $issue->getMessage();

                // Build the GitLab issue object.
                $gitlabIssues[] = [
                    'description' => $description,
                    'check_name' => $issue->getCode(),
                    'severity' => $severityString,
                    'location' => [
                        'path' => $path,
                        'lines' => [
                            'begin' => $issue->getLine(),
                        ],
                    ],
                    'fingerprint' => $fingerprint,
                ];
            }
        }

        return $this->jsonEncode($gitlabIssues, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Parses a GitLab Code Quality JSON string and returns a Report object.
     *
     * @param string $input The JSON string to parse.
     * @param string $name The name for the new Report object.
     * @return Report The parsed Report object.
     * @throws \InvalidArgumentException If the JSON is invalid or the structure is incorrect.
     */
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
            throw new \InvalidArgumentException('Decoded JSON is not in the expected format (expected array of issues).');
        }

        $flatIssues = [];

        // Invert the severity map for parsing
        $gitlabSeverityToInternal = array_flip(self::SEVERITY_TO_GITLAB_SEVERITY_MAP);

        foreach ($data as $gitlabIssue) {
            $location = $gitlabIssue['location'] ?? null;
            if (!$location) {
                continue;
            }

            $severityString = $gitlabIssue['severity'] ?? 'minor';
            $severity = $gitlabSeverityToInternal[$severityString] ?? Report::SEVERITY_WARNING;

            $flatIssues[] = [
                'message' => $gitlabIssue['description'] ?? 'No message provided',
                'line' => $location['lines']['begin'] ?? 1,
                'column' => 1, // GitLab format doesn't explicitly support a column number
                'path' => $location['path'] ?? 'unknown_file',
                'code' => $gitlabIssue['check_name'] ?? 'Unknown',
                'severity' => $severity,
            ];
        }

        $reportData = [
            'name' => $name,
            'issues' => $flatIssues,
            'subReports' => [],
            'timeStart' => 0,
            'timeEnd' => 0,
        ];

        return Report::fromJson($reportData);
    }

    /**
     * @return string The description of the format.
     */
    public static function getDesc(): string
    {
        return "GitLab Code Quality JSON";
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
