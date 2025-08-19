<?php


namespace Tuchsoft\IssueReporter\Test\Base;

use PHPUnit\Framework\TestCase;
use Tuchsoft\IssueReporter\Format\Base\FormatInterface;
use Tuchsoft\IssueReporter\Report;

/**
 * Trait ProvidesReportWithIssues
 *
 * A test helper trait that supplies a pre-configured Report object
 * with a variety of issues. This ensures that format tests can be
 * run against a consistent and comprehensive data set.
 */
abstract class AbstractTestFormat extends TestCase
{
    protected FormatInterface $formatter;

    public function testGetDesc() {
        $desc = $this->formatter->getDesc();

        self::assertIsString($desc);
        self::assertStringContainsString(' ', $desc, "The description must be more then 1 word");
    }

    public function testGetName() {
        $name = $this->formatter->getName();

        self::assertIsString($name);
        self::assertStringNotContainsString(' ', $name, 'The name must not contain spaces');
        self::assertEquals($name, strtolower($name), "The name must be lowercase");
    }

    public static function assertEqualReport(Report $expected, Report $report, ?string $basePath = null, ?string $name = null, ?int $errors = null, ?int $warnings = null, ?int $tips = null, ?int $files = null) {

        self::assertCount(count($expected->getIssues()), $report->getIssues(), 'The number of issues in the reports should match');
        self::assertEquals($basePath ?? $expected->getBasePath(), $report->getBasePath(), 'The base path of the reports should match');
        self::assertEquals($name ?? $expected->getName(), $report->getName(), 'The name of the reports should match');
        self::assertEquals($errors ?? $expected->getTotalErrors(), $report->getTotalErrors(), 'The number of errors in the reports should match');
        self::assertEquals($warnings ?? $expected->getTotalWarnings(), $report->getTotalWarnings(), 'The number of warnings in the reports should match');
        self::assertEquals($tips ?? $expected->getTotalTips(), $report->getTotalTips(), 'The number of tips in the reports should match');
        self::assertEquals($files ?? $expected->getTotalFiles(), $report->getTotalFiles(), 'The number of files in the reports should match');

    }

}