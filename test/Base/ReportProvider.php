<?php

namespace Tuchsoft\IssueReporter\Test\Base;

use Tuchsoft\IssueReporter\Issue;
use Tuchsoft\IssueReporter\Report;

/**
 * Trait ProvidesReportWithIssues
 *
 * A test helper trait that supplies a pre-configured Report object
 * with a variety of issues. This ensures that format tests can be
 * run against a consistent and comprehensive data set.
 */
trait ReportProvider
{
    /**
     * Creates a standardized Report object for testing.
     *
     * The report contains issues of different severities across multiple files,
     * allowing for thorough testing of format generation and parsing.
     *
     * @return Report
     */
    protected function createTestReport(): Report
    {
        // The Report class requires start() to be called before adding issues.
        // We also set a base path to test relative path generation.
        $report = new Report('Test Report', '/project/base');
        $report->start();

        // Add multiple issues using the public addIssues method.
        $report->addIssues(
        // An error issue
            new Issue(
                'error.rule', // The code will become 'Test Report.Error.Rule'
                Report::SEVERITY_ERROR,
                'This is a critical error.',
                '/project/base/src/File1.php',
                10,
                5,
                'https://example.com/error-example',
                'Help message for a critical error'
            ),
            // A warning issue in the same file
            new Issue(
                'warning.rule',
                Report::SEVERITY_WARNING,
                'This is a warning.',
                '/project/base/src/File1.php',
                25,
                15,
                'https://example.com/warning-example',
                'Help message for a warning'
            ),
            // A tip in a different file
            new Issue(
                'tip.rule',
                Report::SEVERITY_TIP,
                'This is just a helpful tip.',
                '/project/base/src/File2.php',
                50,
                1,
                'https://example.com/tip-example',
                'Help message for a useful tip'
            ),
            // An issue with no line/column to test defaults
            new Issue(
                'noline.rule',
                Report::SEVERITY_WARNING,
                'This issue has no line number.',
                '/project/base/src/File3.php',
                ref:'https://example.com/warning-example',
                help:'Help message for another warning'
            ),
           new Issue(
               'unicode.test',
               Report::SEVERITY_ERROR,
               'Unicode char: é',
               'src/File1.php',
               help: 'Unicode help',
               ref: 'https://example.com/'),
            new Issue(
                'outside.src',
                Report::SEVERITY_WARNING,
                'Thi error is outside src ',
                'xyz/File.php',
                help: 'So the computed basepath should be equal to /project/base',
                ref: 'https://example.com/zzz')
        );


        $report->complete();

        $now = microtime(true);
        $report->setTimeStart($now-10);
        $report->setTimeEnd($now);

        return $report;
    }
}