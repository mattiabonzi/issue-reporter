<?php

namespace Tuchsoft\IssueReporter\Format;

use DOMDocument;
use DOMException;
use Symfony\Component\Console\Input\InputOption;
use Tuchsoft\IssueReporter\Format\Base\AbstractFormat;
use Tuchsoft\IssueReporter\Format\Base\ParsableFormatInterface;
use Tuchsoft\IssueReporter\Format\Base\ParsableMessageFormatTrait;
use Tuchsoft\IssueReporter\Format\Base\XmlFormatTrait;
use Tuchsoft\IssueReporter\Issue;
use Tuchsoft\IssueReporter\Report;
use Tuchsoft\IssueReporter\Utils\Path;

/**
 * An implementation of a report format that serializes and deserializes
 * a Report object to and from the JUnit XML format.
 */
class Junit extends AbstractFormat implements ParsableFormatInterface
{
    use XmlFormatTrait;
    use ParsableMessageFormatTrait;

    /**
     * Generates a JUnit XML string from a Report object.
     *
     * @param Report $report The report object to serialize.
     * @return string The JUnit XML string.
     * @throws DOMException If there is an error creating the XML document.
     */
    public function generate(Report $report): string
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;


        $totalErrorCount = $report->getTotalErrors();
        $totalWarningCount = $report->getTotalWarnings() + $report->getTotalTips();

        // Create the root <testsuites> element
        $testSuites = $dom->createElement('testsuites');
        $testSuites->setAttribute('failures', (string)($totalErrorCount + $totalWarningCount));
        $testSuites->setAttribute('errors', (string)$totalErrorCount);
        $testSuites->setAttribute('name', $report->getName());
        $testSuites->setAttribute('time', $report->getTotalTime());
        $testSuites->setAttribute('timestamp', (string) round($report->getTimeEnd()));
        $dom->appendChild($testSuites);

        foreach ($report->getIssues() as $path => $issues) {
            $testSuite = $dom->createElement('testsuite');
            $testSuite->setAttribute('name', $path);
            $testSuite->setAttribute('tests', (string)count($issues));
            $testSuite->setAttribute('failures', (string)count($issues));
            $testSuite->setAttribute('file', $path);
            $testSuites->appendChild($testSuite);

            /** @var Issue $issue */
            foreach ($issues as $issue) {
                $props = [
                    'severity' => $this->getSeverityIcon($issue->getSeverity()),
                    'line' => $issue->getLine(),
                    'column' => $issue->getColumn(),
                    'extra' => json_encode($issue->getExtra())
                ];

                if ($this->options['show-help']) {
                    $props['help'] = $issue->getHelp();
                }

                if ($this->options['show-ref']) {
                    $props['ref'] = $issue->getRef();
                }


                $testCase = $dom->createElement('testcase');
                $testCase->setAttribute('name', $this->options['show-code'] ? $issue->getCode() : $path);
                $testCase->setAttribute('file', $path);
                $testCase->setAttribute('line', $issue->getLine());

                $failure = $dom->createElement('failure');

                if ($this->options['show-code']) {
                    $failure->setAttribute('type', $issue->getCode());
                }
                $failure->setAttribute('message', $issue->getMessage());
                $fullMessage = $this->getParsableMessage($issue, true);

                $failure->appendChild($dom->createTextNode($fullMessage));

                $properties =  $dom->createElement('properties');
                foreach ($props as $key => $value) {
                    $property = $dom->createElement('property');
                    $property->setAttribute('name', $key);
                    $property->setAttribute('value', $value);
                    $properties->appendChild($property);
                }


                $testCase->appendChild($failure);
                $testCase->appendChild($properties);
                $testSuite->appendChild($testCase);
            }
        }

        return $this->saveXML($dom);
    }

    /**
     * Parses a JUnit XML string and returns a Report object.
     *
     * @param string $input The XML string to parse.
     * @param string $name The name for the new Report object.
     * @return Report The parsed Report object.
     * @throws \InvalidArgumentException If the XML is invalid or the structure is incorrect.
     */
    public function parse(string $input, ?string $name = null): Report
    {
        if (!$name) {
            $name = static::getDefaultReportName();
        }

        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($input);

        if ($xml === false) {
            $errors = libxml_get_errors();
            $errorMessage = "Failed to parse XML: ";
            foreach ($errors as $error) {
                $errorMessage .= "{$error->message} ";
            }
            libxml_clear_errors();
            throw new \InvalidArgumentException($errorMessage);
        }

        $flatIssues = [];
        $reportName = (string)($xml['name'] ?? $name);
        $allPaths = [];

        foreach ($xml->testsuite as $testsuite) {
            $path = (string)$testsuite['name'];
            $allPaths[] = $path;

            foreach ($testsuite->testcase as $testcase) {
                if (!isset($testcase->failure) &&  !isset($testcase->error)) continue;
                // Check for failure/error elements
                $issueElement = $testcase->failure ?? $testcase->error;

                if ($issueElement) {

                    $severity = Report::SEVERITY_ERROR;
                    $message = trim((string) ($issueElement['message'] ?? $issueElement[0]));
                    $code = (string)$issueElement['type'];
                    $line = (int)$testcase['line'];
                    $column = 0;
                    $extra = [];
                    $ref = '';

                    if ($this->options['parse-message']) {
                        $parsed = $this->parseMessage($message, true);
                        if ($parsed['message']) {
                            $message = $parsed['message'];
                        }

                        if ($parsed['line']) {
                            $line = $parsed['line'];
                        }

                        if ($parsed['col']) {
                            $column = $parsed['col'];
                        }

                        if ($parsed['help']) {
                            $column = $parsed['help'];
                        }

                        if ($parsed['ref']) {
                            $column = $parsed['ref'];
                        }
                    }

                    // Extract properties from the new <properties> tag
                    if (isset($testcase->properties)) {
                        foreach ($testcase->properties->property as $property) {
                            $propName = (string)$property['name'];
                            $propValue = (string)$property['value'];
                            switch ($propName) {
                                case 'severity':
                                    if ($propValue === 'Error') {
                                        $severity = Report::SEVERITY_ERROR;
                                    }
                                    break;
                                case 'line':
                                    $line = (int)$propValue;
                                    break;
                                case 'column':
                                    $column = (int)$propValue;
                                    break;
                                case 'extra':
                                    $extra = json_decode($propValue, true);
                                    break;
                                case 'ref':
                                    $ref = $propValue;
                                    break;
                            }
                        }
                    }

                    $flatIssues[] = [
                        'message' => $message,
                        'line' => $line,
                        'column' => $column,
                        'path' => $path,
                        'code' => $code,
                        'severity' => $severity,
                        'ref' => $ref,
                        'extra' => $extra,
                    ];
                }
            }
        }

        $reportData = [
            'name' => $reportName,
            'issues' => $flatIssues,
            'subReports' => [],
            'timeStart' => (float) ($xml['timestamp'] ?? 0),
            'timeEnd' => (float) ($xml['timestamp'] ?? 0),
            'basePath' => Path::findCommonBasePath($allPaths)
        ];

        return Report::fromJson($reportData);
    }

    /**
     * @return string The description of the format.
     */
    public static function getDesc(): string
    {
        return "JUnit XML representation for static analysis reports";
    }

    public static function supports(): array
    {
        return [];
    }

    public static function supportsExtra(): array
    {
        return [];
    }


    public static function getOptionsDefinition(int $returnType = self::OPTIONS_NORMAL): array
    {
        return [
            ...parent::getOptionsDefinition($returnType),
            ...self::getXmlOptions($returnType),
            ...self::newOption('parse-message', InputOption::VALUE_NEGATABLE, 'try (or don\'t try --no-show-ref) to parse the message for help and ref field', true, $returnType)
        ];
    }
}
