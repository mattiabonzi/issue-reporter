<?php

namespace Tuchsoft\IssueReporter\Format;

use DOMDocument;
use Tuchsoft\IssueReporter\Format\Base\AbstractFormat;
use Tuchsoft\IssueReporter\Format\Base\ParsableFormatInterface;
use Tuchsoft\IssueReporter\Format\Base\XmlFormatTrait;
use Tuchsoft\IssueReporter\Report;


class RawXml extends AbstractFormat implements ParsableFormatInterface
{

    use XmlFormatTrait;

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
                $currentKeyName = $key;
                // Check for numeric keys and the specified array names
                if (is_numeric($key) && $keyName === 'subReports') {
                    $currentKeyName = 'report';
                } elseif (is_numeric($key) && $keyName === 'issues') {
                    $currentKeyName = 'issue';
                }

                if (is_array($value)) {
                    $childNode = $dom->createElement($currentKeyName);
                    $node->appendChild($childNode);
                    $recursiveHelper($value, $childNode, $currentKeyName);
                } else {
                    $childNode = $dom->createElement($currentKeyName, htmlspecialchars((string)$value));
                    $node->appendChild($childNode);
                }
            }
        };

        $recursiveHelper($report->jsonSerialize(), $root);

        return $this->saveXML($dom);
    }

    public function parse(string $input, ?string $name = null): Report
    {
        if (!$name) {
            $name = static::getDefaultReportName();
        }
        $dom = new DOMDocument();
        $dom->loadXML($input);
        $root = $dom->documentElement;

        if ($root->nodeName !== 'report') {
            throw new \InvalidArgumentException("Invalid XML format. Root element must be 'report'.");
        }

        // Initialize the root report data structure.
        $reportData = [
            'name' => $name,
            'issues' => [],
            'subReports' => [],
            'timeStart' => (float) $root->getAttribute('timeStart'),
            'timeEnd' => (float) $root->getAttribute('timeEnd'),
            // Note: fromJson doesn't use these attributes, so they're not strictly necessary here,
            // but it's good practice to capture them if available.
            'totalErrors' => (int) $root->getAttribute('errors'),
            'totalWarnings' => (int) $root->getAttribute('warnings'),
            'totalTips' => (int) $root->getAttribute('tips'),
            'totalFiles' => (int) $root->getAttribute('files'),
            'totalTime' => (float) $root->getAttribute('time'),
        ];

        $recursiveHelper = function (\DOMElement $node) use (&$recursiveHelper) {
            $result = ['issues' => [], 'subReports' => []];

            foreach ($node->childNodes as $childNode) {
                if ($childNode->nodeType !== XML_ELEMENT_NODE) {
                    continue;
                }

                if ($childNode->nodeName === 'subReports') {
                    foreach ($childNode->childNodes as $subReportNode) {
                        if ($subReportNode->nodeType === XML_ELEMENT_NODE && $subReportNode->nodeName === 'report') {
                            $subReportArray = [
                                'name' => $subReportNode->getAttribute('name'),
                                'timeStart' => (float) $subReportNode->getAttribute('timeStart'),
                                'timeEnd' => (float) $subReportNode->getAttribute('timeEnd'),
                                'totalErrors' => (int) $subReportNode->getAttribute('errors'),
                                'totalWarnings' => (int) $subReportNode->getAttribute('warnings'),
                                'totalTips' => (int) $subReportNode->getAttribute('tips'),
                                'totalFiles' => (int) $subReportNode->getAttribute('files'),
                                'totalTime' => (float) $subReportNode->getAttribute('time'),
                            ];
                            // Merge recursive call result for issues and subReports
                            $parsedChildren = $recursiveHelper($subReportNode);
                            $subReportArray['issues'] = $parsedChildren['issues'];
                            $subReportArray['subReports'] = $parsedChildren['subReports'];
                            $result['subReports'][] = $subReportArray;
                        }
                    }
                } elseif ($childNode->nodeName === 'issues') {
                    foreach ($childNode->childNodes as $issueNode) {
                        if ($issueNode->nodeType === XML_ELEMENT_NODE && $issueNode->nodeName === 'issue') {
                            $issueData = [
                                'message' => $this->getXmlNodeValue($issueNode, 'message'),
                                'line' => (int) $this->getXmlNodeValue($issueNode, 'line'),
                                'column' => (int) $this->getXmlNodeValue($issueNode, 'column'),
                                'path' => $this->getXmlNodeValue($issueNode, 'path'),
                                'code' => $this->getXmlNodeValue($issueNode, 'code'),
                                'severity' => (int) $this->getXmlNodeValue($issueNode, 'severity')
                            ];
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
     * @param \DOMElement $parent
     * @param string $tagName
     * @return string
     */
    private function getXmlNodeValue(\DOMElement $parent, string $tagName): string
    {
        $node = $parent->getElementsByTagName($tagName)->item(0);
        return $node ? $node->nodeValue : '';
    }

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


}