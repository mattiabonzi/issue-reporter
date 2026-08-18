<?php

namespace Tuchsoft\IssueReporter\Test\Base\Provider;


use Tuchsoft\IssueReporter\Issue;
use Tuchsoft\IssueReporter\Report;

define('FIXED_TEST_DATA', [
    0 =>
        [
            'code' => '0error.rule',
            'severity' => 5, //error
            'message' => 'This is a critical error.',
            'path' => '/project/base/src/File1.php',
            'inputPath' => '/project/base/src/File1.php',
            'line' => 10,
            'col' => 5,
            'ref' => 'https://example.com/error-example',
            'help' => 'Help message for a critical error',
        ],
    1 =>
        [
            'code' => '1warning.rule',
            'severity' => 3, // warning
            'message' => 'This is a warning.',
            'path' => '/project/base/src/File1.php',
            'inputPath' => '/project/base/src/File1.php',
            'line' => 25,
            'col' => 15,
            'ref' => '',
            'help' => 'Help message for a warning',
        ],
    2 =>
        [
            'code' => '2tip.rule',
            'severity' => 0 , // tip
            'message' => 'This is just a helpful tip.',
            'path' => '/project/base/src/File2.php',
            'inputPath' => '/project/base/src/File2.php',
            'line' => 50,
            'col' => NULL,
            'ref' => 'https://example.com/tip-example',
            'help' => NULL,
        ],
    3 =>
        [
            'code' => '3noline.rule',
            'severity' => 3, // warning
            'message' => 'This issue has no line number.',
            'path' => '/project/base/src/File3.php',
            'inputPath' => '/project/base/src/File3.php',
            'line' => NULL,
            'col' => NULL,
            'ref' => NULL,
            'help' => NULL,
        ],
    4 =>
        [
            'code' => '4unicode.test',
            'severity' => 5, // error
            'message' => 'Unicode char: é',
            'path' => '/project/base/src/File1.php',
            'inputPath' => 'src/File1.php',
            'line' => NULL,
            'col' => NULL,
            'ref' => 'https://example.com/',
            'help' => 'Unicode help',
        ],
    5 =>
        [
            'code' => '5outside.src',
            'severity' => 3, // warning
            'message' => 'This warning is outside src',
            'path' => '/project/base/xyz/File.php',
            'inputPath' => 'xyz/File.php',
            'line' => NULL,
            'col' => NULL,
            'ref' => NULL,
            'help' => 'So the computed basepath should be equal to /project/base',
        ],
    6 =>
        [
            'code' => '6outside2.src',
            'severity' => 5, // error
            'message' => 'This error is outside src',
            'path' => '/project/base/xyz/File.php',
            'inputPath' => 'xyz/File.php',
            'line' => NULL,
            'col' => NULL,
            'ref' => 'https://example.com/random',
            'help' => 'Random help',
        ],
]);


/**
 * Oveerided to ensure the return order of the issue match the one defined in the test data, to make testing simplier
 */
class TestReport extends Report {

    public function getIssues(bool $byFile = true, bool $recursive = true): array
    {
        $issue = parent::getIssues($byFile, $recursive);
        if ($byFile) {
            return $issue;
        }


        $order_map = [];
        foreach (FIXED_TEST_DATA as $index => $item) {
            $order_map[$item['code']] = $index;
        }

        usort($issue, function($a, $b) use ($order_map) {
            $order_a = $order_map[$a->getCode()] ?? PHP_INT_MAX;
            $order_b = $order_map[$b->getCode()] ?? PHP_INT_MAX;

            return $order_a <=> $order_b;
        });


        return $issue;
    }
}


/**
 * Trait to provide a data matrix of comprehensive Report objects for testing.
 */
trait ReportProvider
{



    /**
     * Generates a single, standardized Report object for testing.
     *
     * @param array $issueOptions An array of key-value pairs representing issue properties to include.
     * @param bool $withSubReport Whether to include a subreport in the generated report.
     * @param bool $includeOutsidePath Whether to include issues outside the common base path.
     * @return Report
     */
    protected function createTestReport(array $options): Report {

        $withSubReport = $options['withSubReport'] ?? false;
        $includeOutsidePath = $options['includeOutsidePath'] ?? false;

        $basePath = '/project/base';
        $report = new Report('Test Report', $basePath);
        $report->start();
        $report->setTimeStart(microtime(true) - 1000);

        // A comprehensive issue
        $issue = new Issue(
            $issueOptions['code'] ?? 'standard.rule',
            $issueOptions['severity'] ?? Issue::SEVERITY_WARNING,
            $issueOptions['message'] ?? 'This is a standard test message.',
            $issueOptions['path'] ?? $basePath . '/src/file1.php',
            $issueOptions['line'] ?? 10,
            $issueOptions['column'] ?? 5
        );
        if (isset($issueOptions['help'])) {
            $issue->setHelp($issueOptions['help']);
        }
        if (isset($issueOptions['ref'])) {
            $issue->setRef($issueOptions['ref']);
        }
        if (isset($issueOptions['extra'])) {
            $issue->setExtra($issueOptions['extra']);
        }

        $report->addIssue($issue);

        // Additional issues with varying properties
        $report->addIssues(
            new Issue('no-help.rule', Issue::SEVERITY_ERROR, 'This issue has no help text.', $basePath . '/src/file2.php', 20, 1),
            new Issue('no-ref.rule', Issue::SEVERITY_TIP, 'This tip has no reference.', $basePath . '/src/file3.php', 30, 1),
            new Issue('no-line-col.rule', Issue::SEVERITY_WARNING, 'No line or column info.', $basePath . '/src/file4.php'),
            new Issue('another.onfile.4', Issue::SEVERITY_WARNING, 'Just another warning.', $basePath . '/src/file4.php', 13,12)
        );

        if ($includeOutsidePath) {
            $report->addIssue(new Issue('outside.rule', Issue::SEVERITY_ERROR, 'This is outside the base path.', '/temp/file.php', 1, 1));
        }

        $report->complete();
        $report->setTimeEnd(microtime(true) - 500);

        if ($withSubReport) {
            $subReport = new Report('Sub Report', $basePath);
            $subReport->start();
            $subReport->setTimeStart(microtime(true) - 50);
            $subReport->addIssues(
                new Issue('sub.report.rule', Issue::SEVERITY_WARNING, 'A warning in a subreport.', $basePath . '/src/sub/file.php', 5, 5)
            );
            $subReport->complete();
            $subReport->setTimeEnd(microtime(true) - 10);
            $report->mergeIn($subReport);
        }

        return $report;
    }

    /**
     * DO NOT CHANGE OR THE TEST WILL FAIL
     * @return Report
     */
    protected function getFixedTestReport(): Report
    {
        // The Report class requires start() to be called before adding issues.
        // We also set a base path to test relative path generation.
        $report = new TestReport('Test Report', '/project/base');
        $report->start();


        foreach (FIXED_TEST_DATA as $testData) {
            $report->issue(
                $testData['code'],
                $testData['severity'],
                $testData['message'],
                $testData['inputPath'],
                $testData['line'],
                $testData['col'],
                $testData['ref'],
                $testData['help']
            );
        }

        $report->complete();

        $now = microtime(true);
        $report->setTimeStart($now-10);
        $report->setTimeEnd($now);

        return $report;
    }

    /**
     * Data provider that creates a matrix of all possible issue property combinations using pairwise testing.
     *
     * @return array
     */
    public static function reportMatrixProvider(): array
    {
        return [
            'line' => [0, 10, null],
            'column' => [0, 5, null],
            'code' => [null, 'Some.$formatterClassRule.Code', 'SomeRuleCode'],
            'message' => ['Simple message', 'This is a more detailed message with symbols ($).', ''],
            'severity' => [Issue::SEVERITY_ERROR, Issue::SEVERITY_WARNING, Issue::SEVERITY_TIP, null],
            'help' => [null, 'A helpful suggestion.'],
            'ref' => [null, 'https://example.com/ref'],
            'extra' => [null, ['hello' => 'word']],
            'withSubReport' => [true, false],
            'includeOutsidePath' => [true, false],
        ];
    }
}