<?php

namespace Tuchsoft\IssueReporter\Format;

use Tuchsoft\IssueReporter\Format\Base\AbstractFormat;
use Tuchsoft\IssueReporter\Format\Base\JsonFormatTrait;
use Tuchsoft\IssueReporter\Format\Base\ParsableFormatInterface;
use Tuchsoft\IssueReporter\Format\Base\ParsableMessageFormatTrait;
use Tuchsoft\IssueReporter\Issue;
use Tuchsoft\IssueReporter\Report;

/**
 * An implementation of a report format that serializes and deserializes
 * a Report object to and from the GitLab Code Quality JSON format.
 *
 * @see https://docs.gitlab.com/ci/testing/code_quality/#code-quality-report-format
 */
class GitLab extends AbstractFormat implements ParsableFormatInterface
{

    use JsonFormatTrait;
    use ParsableMessageFormatTrait;

    /**
     * {@inheritdoc}
     *
     * Maps the internal severity levels to GitLab's severity strings.
     */
    protected static function getSeverityMap(): array
    {
        return [
            Issue::SEVERITY_ERROR => 'major',
            Issue::SEVERITY_WARNING => 'minor',
            Issue::SEVERITY_TIP => 'info',
        ];
    }

    /**
     * Generates a GitLab Code Quality JSON string from a Report object.
     *
     * @param Report $report The report object to serialize.
     * @return string The JSON string representing the GitLab Code Quality report.
     */
    public function generate(Report $report): string
    {
        $issuesByPath = $report->getIssues();
        $gitlabIssues = [];

        foreach ($issuesByPath as $path => $issues) {
            /** @var Issue $issue */
            foreach ($issues as $issue) {

                // GitLab uses a fingerprint to identify unique issues across analysis runs.
                // It SHOULD be unique.
                $fingerprint = md5($path . $issue->getLine() . $issue->getColumn() . $issue->getCode() . $issue->getTime());

                // Build the GitLab issue object.
                $gitlabIssues[] = [
                    'description' => $this->getParsableMessage($issue),
                    'check_name' => $issue->getCode(),
                    'severity' => $this->getSeverity($issue->getSeverity()),
                    'location' => [
                        'path' => $issue->getRelativePath(),
                        'lines' => [
                            'begin' => $issue->getLine(),
                        ],
                    ],
                    'fingerprint' => $fingerprint,
                ];
            }
        }

        return $this->jsonEncode($gitlabIssues);
    }

    /**
     * Parses a GitLab Code Quality JSON string and returns a Report object.
     *
     * @param string      $input The JSON string to parse.
     * @param string|null $name  The name for the new Report object.
     * @return Report The parsed Report object.
     * @throws \InvalidArgumentException If the JSON is invalid or the structure is incorrect.
     */
    public function parse(string $input, ?string $name = null): Report
    {
        if (!$name) {
            $name = static::getDefaultReportName();
        }

        $data = $this->jsonDecode($input, true);

        if (!is_array($data)) {
            throw new \InvalidArgumentException('Invalid JSON input: Decoded JSON is not in the expected format (expected array of issues).');
        }

        $flatIssues = [];

        foreach ($data as $i => $gitlabIssue) {
            $i = $gitlabIssue['fingerprint'] ?? $i;

            if (empty($gitlabIssue['location'])) {
                throw new \InvalidArgumentException("Invalid JSON input: location array is empty for issue: $i");
            }

            if (empty($gitlabIssue['location']['path'])) {
                throw new \InvalidArgumentException("Invalid JSON input: location:path is empty for issue: $i");
            }

            if (empty($gitlabIssue['severity'])) {
                throw new \InvalidArgumentException("Invalid JSON input: severity is empty for issue: $i");
            }

            if (empty($gitlabIssue['description'])) {
                throw new \InvalidArgumentException("Invalid JSON input: description is empty for issue: $i");
            }


            $parsed = $this->parseMessage($gitlabIssue['description']);
            $flatIssues[] = [
                'message' => $parsed['message'],
                'line' => $gitlabIssue['location']['lines']['begin']['line'] ?? $gitlabIssue['location']['lines']['begin'] ??  0,
                'path' => $gitlabIssue['location']['path'],
                'code' => $gitlabIssue['check_name'] ?? Issue::UNKNOW_CODE,
                'severity' => $this->parseSeverity($gitlabIssue['severity']),
                'ref' => $parsed['ref'] ?? null,
                'help' => $parsed['help'] ?? null,
                'column' => (int)$parsed['col'] ?? null,
            ];
        }

        $reportData = [
            'name' => $name,
            'basePath' => '/', // GitLab reports don't have a base path, so we use a default.
            'issues' => $flatIssues,
            'subReports' => [],
            'timeStart' => 0,
            'timeEnd' => 0,
        ];

        return Report::fromJson($reportData);
    }

    /**
     * {@inheritdoc}
     */
    public static function getDesc(): string
    {
        return "GitLab Code Quality JSON (subset of CodeClimate report format) [https://docs.gitlab.com/ci/testing/code_quality/#code-quality-report-format]";
    }

    /**
     * {@inheritdoc}
     */
    public static function supports(): array
    {
        return [
            self::FEATURE_ISSUE_MESSAGE,
            self::FEATURE_ISSUE_CODE,
            self::FEATURE_PRESERVE_SEVERITY,
            self::FEATURE_ISSUE_LINE,
            self::FEATURE_PARSABLE_MESSAGE
        ];
    }



    /**
     * {@inheritdoc}
     */
    public static function supportsExtra(): array
    {
        return [
            self::FEATURE_ISSUE_COLUMN,
            self::FEATURE_ISSUE_HELP,
            self::FEATURE_ISSUE_REF,
        ];
    }


    /**
     * {@inheritdoc}
     */
    static public function getOptionsDefinition(int $returnType = self::OPTIONS_NORMAL):array {
        return[
            ...parent::getOptionsDefinition($returnType),
            ...static::getJsonOptions($returnType),
            ...static::getParsableMessageOptions($returnType)
        ];
    }
}