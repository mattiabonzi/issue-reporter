<?php

namespace Tuchsoft\IssueReporter\Test\Format;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Tuchsoft\IssueReporter\Format\Base\FormatInterface;
use Tuchsoft\IssueReporter\Format\Emacs;
use Tuchsoft\IssueReporter\Issue;
use Tuchsoft\IssueReporter\Report;
use Tuchsoft\IssueReporter\Test\Base\AbstractTestFormat;
use Tuchsoft\IssueReporter\Test\Base\ReportProvider;

#[CoversClass(\Tuchsoft\IssueReporter\Format\Emacs::class)]
#[Group('Emacs')]
class EmacsTest extends AbstractTestFormat
{
    use ReportProvider;

    /** @var Emacs $formatter */
    protected FormatInterface $formatter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->formatter = new Emacs();
    }

    /**
     * Verifies that the generate method produces a correct Emacs-style string.
     */
    public function testGenerateProducesCorrectFormat(): void
    {
        $report = $this->createTestReport();
        $generatedOutput = $this->formatter->generate($report);

        $lines = explode("\n", $generatedOutput);
        $originalIssues = $report->getIssues(false, true); // Get flat list of all issues

        $this->assertCount(count($originalIssues), $lines, "The number of output lines should match the number of issues.");

        // Sort both arrays to ensure a consistent order for comparison, as generate() doesn't guarantee order.
        usort($originalIssues, fn(Issue $a, Issue $b) => strcmp($a->getPath() . $a->getLine(), $b->getPath() . $b->getLine()));
        sort($lines);

        foreach ($originalIssues as $index => $issue) {
            $expectedSeverity = match ($issue->getSeverity()) {
                Report::SEVERITY_ERROR => 'error',
                default => 'warning', // WARNING and TIP map to 'warning'
            };

            $expectedLine = sprintf(
                "%s:%d:%d: %s - %s (#%s) (%s)",
                $issue->getPath(),
                $issue->getLine(),
                $issue->getColumn(),
                $expectedSeverity,
                $issue->getMessage(),
                $issue->getCode(),
                $issue->getHelp()
            );

            $this->assertEquals($expectedLine, $lines[$index]);
        }
    }

    /**
     * Verifies that the parse method correctly constructs a Report object.
     */
    public function testParseCreatesCorrectReportObject(): void
    {
        $input = <<<TEXT
src/File1.php:10:5: error - This is an error. (#Some.Error.Rule)
src/File1.php:20:15: warning - This is a warning. (#Some.Warning.Rule)
src/File2.php:30:1: warning - This is a tip. (#Some.Tip.Rule)
TEXT;

        $report = $this->formatter->parse($input, 'Parsed Emacs Report');
        $this->assertInstanceOf(Report::class, $report);
        $this->assertEquals('Parsed Emacs Report', $report->getName());

        $issues = $report->getIssues(false, false);
        $this->assertCount(3, $issues);

        // Sort issues by line number for predictable testing
        usort($issues, fn(Issue $a, Issue $b) => $a->getLine() <=> $b->getLine());

        // Check the error issue
        $errorIssue = $issues[0];
        $this->assertEquals('src/File1.php', $errorIssue->getPath());
        $this->assertEquals(10, $errorIssue->getLine());
        $this->assertEquals(5, $errorIssue->getColumn());
        $this->assertEquals('This is an error.', $errorIssue->getMessage());
        $this->assertEquals('Some.Error.Rule', $errorIssue->getCode());
        $this->assertEquals(Report::SEVERITY_ERROR, $errorIssue->getSeverity());

        // Check the warning issue
        $warningIssue = $issues[1];
        $this->assertEquals('src/File1.php', $warningIssue->getPath());
        $this->assertEquals(20, $warningIssue->getLine());
        $this->assertEquals(15, $warningIssue->getColumn());
        $this->assertEquals('This is a warning.', $warningIssue->getMessage());
        $this->assertEquals('Some.Warning.Rule', $warningIssue->getCode());
        $this->assertEquals(Report::SEVERITY_WARNING, $warningIssue->getSeverity());

        // Check the tip (parsed as warning) issue
        $tipIssue = $issues[2];
        $this->assertEquals('src/File2.php', $tipIssue->getPath());
        $this->assertEquals(30, $tipIssue->getLine());
        $this->assertEquals(1, $tipIssue->getColumn());
        $this->assertEquals('This is a tip.', $tipIssue->getMessage());
        $this->assertEquals('Some.Tip.Rule', $tipIssue->getCode());
        $this->assertEquals(Report::SEVERITY_WARNING, $tipIssue->getSeverity());
    }

    /**
     * Verifies that the parse method correctly handles empty lines and invalid lines.
     */
    public function testParseHandlesEmptyAndInvalidLines(): void
    {
        $input = <<<TEXT
src/File1.php:10:5: error - This is a valid line. (#Valid.Rule)

This is an invalid line that should be ignored.
src/File2.php:20:15: warning - Another valid line. (#Another.Rule)

TEXT;

        $report = $this->formatter->parse($input);
        $issues = $report->getIssues(false, false);

        $this->assertCount(2, $issues, "Should only parse the two valid lines and ignore others.");

        $this->assertEquals('src/File1.php', $issues[0]->getPath());
        $this->assertEquals('src/File2.php', $issues[1]->getPath());
    }

    /**
     * Tests the full cycle of generating a report and then parsing it back.
     * This test highlights inconsistencies between the generate and parse methods.
     */
    public function testGenerateAndParseRoundTrip(): void
    {
        // 1. Create an initial report
        $originalReport = $this->createTestReport();

        // 2. Generate the text string from it and parse it back
        $textOutput = $this->formatter->generate($originalReport);
        $parsedReport = $this->formatter->parse($textOutput);

        // 3. Compare the meaningful data of the reports
        self::assertEqualReport(
            $originalReport,
            $parsedReport,
            name: $this->formatter->getDefaultReportName(),
            warnings: $originalReport->getTotalWarnings()+$originalReport->getTotalTips(),
            tips: 0
        );




        // Sort issues to ensure consistent order for comparison
        $originalIssues = $originalReport->getIssues(false);
        $parsedIssues = $parsedReport->getIssues(false);
        $sortFunc = fn(Issue $a, Issue $b) => strcmp($a->getPath() . $a->getLine(), $b->getPath() . $b->getLine());
        usort($originalIssues, $sortFunc);
        usort($parsedIssues, $sortFunc);

        foreach ($originalIssues as $i => $originalIssue) {
            $parsedIssue = $parsedIssues[$i];

            $this->assertEquals($originalIssue->getPath(), $parsedIssue->getPath());
            $this->assertEquals($originalIssue->getLine(), $parsedIssue->getLine());
            $this->assertEquals($originalIssue->getColumn(), $parsedIssue->getColumn());
            $this->assertEquals($originalIssue->getCode(), $parsedIssue->getCode());
            $this->assertEquals($originalIssue->getMessage(), $parsedIssue->getMessage());

            // KNOWN INCONSISTENCY: `generate` maps TIP to 'warning'.
            // `parse` maps 'warning' back to SEVERITY_WARNING.
            // So, an original TIP becomes a WARNING after the round trip.
            $expectedSeverity = ($originalIssue->getSeverity() === Report::SEVERITY_TIP)
                ? Report::SEVERITY_WARNING
                : $originalIssue->getSeverity();

            $this->assertEquals($expectedSeverity, $parsedIssue->getSeverity());
        }
    }
}