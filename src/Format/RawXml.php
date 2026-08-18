<?php

namespace Tuchsoft\IssueReporter\Format;

use DOMDocument;
use DOMElement;
use InvalidArgumentException;
use Tuchsoft\IssueReporter\Format\Base\AbstractFormat;
use Tuchsoft\IssueReporter\Format\Base\ParsableFormatInterface;
use Tuchsoft\IssueReporter\Format\Base\XmlFormatTrait;
use Tuchsoft\IssueReporter\Issue;
use Tuchsoft\IssueReporter\Report;


class RawXml extends AbstractFormat implements ParsableFormatInterface
{

    use XmlFormatTrait;

    static function getDesc(): string
    {
        return 'Complete JSON rappresetation';
    }

    public static function supports(): array
    {
        return self::FEATURE_ALL;
    }

    public static function supportsExtra(): array
    {
        return [];
    }

    public function generate(Report $report): string
        {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $root = $dom->createElement('report');
        $root->setAttribute('name', $report->getName());
        $root->setAttribute('errors', $report->getTotalErrors());
        $root->setAttribute('warnings', $report->getTotalWarnings());
        $root->setAttribute('tips', $report->getTotalTips());
        $root->setAttribute('files', $report->getTotalFiles());
        $root->setAttribute('time', $report->getTotalTime());
        $dom->appendChild($root);

        $recursiveHelper = function ($array, $node, $keyName = null) use (&$recursiveHelper, $dom) {
            foreach ($array as $key => $value) {
                $filename = '';
                // Check for numeric keys and the specified array names
                if (is_numeric($key) && $keyName === 'subReports') {
                    $key = 'report';
                } else if (str_starts_with($key, '/') || str_starts_with($key, '.')) {
                    $filename = $key;
                    $key = 'file';

                }

                if (is_array($value)) {
                    $childNode = $dom->createElement(strtolower($key));
                    if ($key == 'file') {
                        $childNode->setAttribute('path', $filename);
                    }
                    $node->appendChild($childNode);
                    $recursiveHelper($value, $childNode, $key);
                } else if (is_a($value, Issue::class)) {
                    $childNode = $dom->createElement('issue');
                    foreach ($value->jsonSerialize() as $name => $val) {
                        if (is_array($val)) {
                            $recursiveHelper($val, $childNode);
                        } else {
                            $childNode->setAttribute($name, $val);
                        }
                    }
                    $node->appendChild($childNode);
                } else {
                    $node->setAttribute($key, $value);
                }
            }
        };

        $recursiveHelper($report->jsonSerialize(), $root);

        $x = $this->xmlEncode($dom);
        return $x;
    }

    public function parse(string $input, ?string $name = null): Report
    {
        if (!$name) {
            $name = static::getDefaultReportName();
        }
        $dom = $this->xmlDecode($input);
        $root = $dom->documentElement;

        if ($root->nodeName !== 'report') {
            throw new InvalidArgumentException("Invalid XML input: Root element must be 'report'.");
        }

        // Initialize the root report data structure.
        $reportData = ['name' => $name, 'issues' => [], 'subReports' => [], 'timeStart' => (float)$root->getAttribute('timeStart'), 'timeEnd' => (float)$root->getAttribute('timeEnd'), 'totalTime' => (float)$root->getAttribute('time'), 'basePath' => (string)$root->getAttribute('basePath'),];

        $recursiveHelper = function (DOMElement $node) use (&$recursiveHelper) {
            $result = ['issues' => [], 'subReports' => []];

            foreach ($node->childNodes as $childNode) {
                if ($childNode->nodeType !== XML_ELEMENT_NODE) {
                    continue;
                }

                if ($childNode->nodeName === 'subreports') {
                    foreach ($childNode->childNodes as $subReportNode) {
                        if ($subReportNode->nodeType === XML_ELEMENT_NODE && $subReportNode->nodeName === 'report') {
                            $subReportArray = ['name' => $subReportNode->getAttribute('name'), 'timeStart' => (float)$subReportNode->getAttribute('timeStart'), 'timeEnd' => (float)$subReportNode->getAttribute('timeEnd'), 'totalErrors' => (int)$subReportNode->getAttribute('errors'), 'totalWarnings' => (int)$subReportNode->getAttribute('warnings'), 'totalTips' => (int)$subReportNode->getAttribute('tips'), 'totalFiles' => (int)$subReportNode->getAttribute('files'), 'totalTime' => (float)$subReportNode->getAttribute('time'),];
                            // Merge recursive call result for issues and subReports
                            $parsedChildren = $recursiveHelper($subReportNode);
                            $subReportArray['issues'] = $parsedChildren['issues'];
                            $subReportArray['subReports'] = $parsedChildren['subReports'];
                            $result['subReports'][] = $subReportArray;
                        }
                    }
                } elseif ($childNode->nodeName === 'issues') {
                    foreach ($childNode->childNodes as $file) {
                        if ($file->nodeType === XML_ELEMENT_NODE && $file->nodeName === 'file') {
                            foreach ($file->childNodes as $issueNode) {
                                /** @var DOMElement $issueNode
                                 */
                                if ($issueNode->nodeType === XML_ELEMENT_NODE && $issueNode->nodeName === 'issue') {
                                    $issueData = [
                                        'message' => $issueNode->getAttribute('message'),
                                        'line' => $issueNode->getAttribute( 'line'),
                                        'column' => $issueNode->getAttribute('column'),
                                        'path' => $issueNode->getAttribute('path'),
                                        'code' => $issueNode->getAttribute( 'code'),
                                        'severity' => $issueNode->getAttribute('severity')];
                                    // The fromJson method expects a file-based structure for issues.
                                    // We must format it correctly here.
                                    $path = $issueData['path'];
                                    if (!isset($result['issues'][$path])) {
                                        $result['issues'][$path] = [];
                                    }
                                    $result['issues'][$path][] = $issueData;
                                }
                            }
                        }
                    }
                }
            }
            return $result;
        };

        // Populate the main report data with the results from the recursive helper.
        $parsedChildren = $recursiveHelper($root);
        $reportData['issues'] = $parsedChildren['issues'];
        $reportData['subReports'] = $parsedChildren['subReports'];

        // Use the existing fromJson method to create the Report object.
        return Report::fromJson($reportData);
    }

    /**
     * Helper function to safely get a node's value.
     *
     * @param DOMElement $parent
     * @param string $tagName
     * @return string
     */
    private function getXmlNodeValue(DOMElement $parent, string $tagName): string
    {
        $node = $parent->getElementsByTagName($tagName)->item(0);
        return $node ? $node->nodeValue : '';
    }


}