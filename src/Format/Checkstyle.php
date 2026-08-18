<?php

namespace Tuchsoft\IssueReporter\Format;

use DOMDocument;
use DOMException;
use Tuchsoft\IssueReporter\Format\Base\AbstractFormat;
use Tuchsoft\IssueReporter\Format\Base\ParsableFormatInterface;
use Tuchsoft\IssueReporter\Format\Base\ParsableMessageFormatTrait;
use Tuchsoft\IssueReporter\Format\Base\XmlFormatTrait;
use Tuchsoft\IssueReporter\Issue;
use Tuchsoft\IssueReporter\Report;
use Tuchsoft\IssueReporter\Utils\Path;

/**
 * An implementation of a report format that serializes and deserializes
 * a Report object to and from the Checkstyle XML format.
 *
 * This format is widely used by static analysis tools and can be integrated
 * with various CI/CD systems and IDEs.
 */
class Checkstyle extends AbstractFormat implements ParsableFormatInterface
{

    use XmlFormatTrait;
    use ParsableMessageFormatTrait;

    /**
     * Generates a Checkstyle XML string from a Report object.
     *
     * @param Report $report The report object to serialize.
     * @return string The Checkstyle XML string.
     * @throws DOMException If there is an error creating the XML document.
     */
    public function generate(Report $report): string
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        $checkstyle = $dom->createElement('checkstyle');
        $checkstyle->setAttribute('version', '3.13.3');
        $dom->appendChild($checkstyle);

        $issuesByPath = $report->getIssues();

        foreach ($issuesByPath as $path => $issues) {
            $fileElement = $dom->createElement('file');
            $fileElement->setAttribute('name', $path);
            $checkstyle->appendChild($fileElement);

            /** @var Issue $issue */
            foreach ($issues as $issue) {
                $errorElement = $dom->createElement('error');
                $errorElement->setAttribute('line', (string)$issue->getLine());
                $errorElement->setAttribute('column', (string)$issue->getColumn());
                $errorElement->setAttribute('severity', $this->getSeverity($issue->getSeverity()));
                $errorElement->setAttribute('message', $this->getParsableMessage($issue));
                $errorElement->setAttribute('source', $issue->getCode());
                $fileElement->appendChild($errorElement);
            }
        }

        return $this->xmlEncode($dom);
    }

    /**
     * Parses a Checkstyle XML string into a Report object.
     *
     * @param string      $input The Checkstyle XML string to parse.
     * @param string|null $name  An optional name for the new Report object. If not provided,
     *                           a default name will be generated.
     * @return Report The parsed Report object.
     * @throws \InvalidArgumentException If the XML input is empty, malformed, or not a valid Checkstyle report.
     */
    public function parse(string $input, ?string $name = null): Report
    {
        if (!$name) {
            $name = static::getDefaultReportName();
        }

        $xml = $this->xmlDecode($input);

        if ($xml->documentElement->tagName != 'checkstyle') {
            throw new \InvalidArgumentException('Invalid XML input: root element should be "checkstyle"');
        }

        $flatIssues = [];
        $allPath = [];

        foreach ($xml->getElementsByTagName('file') as $fileElement) {
            $path = $fileElement->getAttribute('name');
            $allPath[] = $path;

            foreach ($fileElement->getElementsByTagName('error') as $errorElement) {

                $severity = match ($errorElement->getAttribute('severity')) {
                    'error' => Issue::SEVERITY_ERROR,
                    'warning' => Issue::SEVERITY_WARNING,
                    'info' => Issue::SEVERITY_TIP,
                    default => Issue::SEVERITY_DEFAULT,
                };

                $messageElement = $errorElement->getAttribute('message');
                $parsed = $this->options['parse-message'] ? $this->parseMessage($messageElement) : [];

                $flatIssues[] = [
                    'message' => $parsed['message'] ?? $messageElement,
                    'line' => (int)$errorElement->getAttribute('line') ?? 0,
                    'column' => (int)$errorElement->getAttribute('column') ?? 0,
                    'path' => $path,
                    'code' => $errorElement->getAttribute('source') ?? Issue::UNKNOW_CODE,
                    'severity' => $severity ?? Issue::SEVERITY_WARNING,
                    'help' => $parsed['help'] ?? '',
                    'ref' => $parsed['ref'] ?? '',
                ];
            }
        }


        $reportData = [
            'name' => $name,
            'issues' => $flatIssues,
            'subReports' => [],
            'timeStart' => 0,
            'basePath' => Path::findCommonBasePath($allPath),
            'timeEnd' => 0,
        ];

        return Report::fromJson($reportData);
    }

    /**
     * {@inheritdoc}
     */
    static public function getOptionsDefinition(int $returnType = self::OPTIONS_NORMAL):array {
        return[
            ...parent::getOptionsDefinition($returnType),
            ...static::getXmlOptions($returnType),
            ...static::getParsableMessageOptions($returnType)
        ];
    }

    /**
     * {@inheritdoc}
     */
    public static function getDesc(): string
    {
        return "Checkstyle XML representation";
    }

    /**
     * {@inheritdoc}
     */
    public static function supports(): array
    {
        return [
            ...self::FEATURE_ISSUE_STANDARD,
            self::FEATURE_PARSABLE_MESSAGE,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public static function supportsExtra(): array
    {
        return [
            self::FEATURE_ISSUE_REF,
            self::FEATURE_ISSUE_HELP
        ];
    }

    /**
     * {@inheritdoc}
     */
    protected static function getSeverityMap(): array
    {
        return [
            Issue::SEVERITY_ERROR => 'error',
            Issue::SEVERITY_WARNING => 'warning',
            Issue::SEVERITY_TIP => 'info'
        ];
    }
}