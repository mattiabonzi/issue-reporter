<?php

namespace Tuchsoft\IssueReporter\Test\Format;

use Tuchsoft\IssueReporter\Test\Base\AbstractTestFormat;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tuchsoft\IssueReporter\Format\Base\FormatInterface;
use Tuchsoft\IssueReporter\Format\PhpCs;
use Tuchsoft\IssueReporter\Issue;
use Tuchsoft\IssueReporter\Report;
use Tuchsoft\IssueReporter\Test\Base\JsonOptionsProvider;
use Tuchsoft\IssueReporter\Test\Base\ReportProvider;

#[CoversClass(\Tuchsoft\IssueReporter\Format\PhpCs::class)]
#[Group('PhpCs')]
class PhpCsTest extends AbstractTestFormat
{
    use ReportProvider;
    use JsonOptionsProvider;

    /** @var PhpCs $formatter */
    protected FormatInterface $formatter;


    protected function setUp(): void
    {
        parent::setUp();
        // Initialize with default options for tests that don't specify their own.
        $this->formatter = new PhpCs();
    }

    /**
     * Verifies that the generate method produces a valid JSON string
     * in the expected PHP_CodeSniffer format from a given Report.
     */
    public function testGenerateProducesCorrectJsonFormat(): void
    {
        $report = $this->createTestReport();
        $jsonOutput = $this->formatter->generate($report);

        $data = json_decode($jsonOutput, true);

        $this->assertIsArray($data);
        $this->assertJson($jsonOutput);

        // Check top-level totals from the source report
        $this->assertEquals($report->getTotalErrors(), $data['totals']['errors']);
        $this->assertEquals($report->getTotalWarnings() + $report->getTotalTips(), $data['totals']['warnings']);

        // Check files structure from the source report
        $originalFilePaths = array_keys($report->getIssues());
        $this->assertCount(count($originalFilePaths), $data['files']);
        foreach ($originalFilePaths as $path) {
            $this->assertArrayHasKey($path, $data['files']);
        }

        // Check details for File1.php
        $file1Data = $data['files'][$report->getBasePath().'/src/File1.php'];
        $issuesInFile1 = $report->getIssues()[$report->getBasePath().'/src/File1.php'];
        $this->assertCount(count($issuesInFile1), $file1Data['messages']);

        // Find the original error issue to compare against
        /** @var Issue $originalError */
        $originalError = current(array_filter($issuesInFile1, fn(Issue $i) => $i->getSeverity() === Report::SEVERITY_ERROR));
        // Find the corresponding message in the JSON output
        $jsonErrorMessage = current(array_filter($file1Data['messages'], fn(array $m) => $m['type'] === 'ERROR'));

        $this->assertEquals("{$originalError->getMessage()} ({$originalError->getHelp()})", $jsonErrorMessage['message']);
        $this->assertEquals($originalError->getSeverity(), $jsonErrorMessage['severity']);
        $this->assertEquals('ERROR', $jsonErrorMessage['type']);
        $this->assertEquals($originalError->getLine(), $jsonErrorMessage['line']);
        $this->assertEquals($originalError->getColumn(), $jsonErrorMessage['column']);
        $this->assertEquals($originalError->getColumn(), $jsonErrorMessage['column']);
        $this->assertEquals($originalError->getCode(), $jsonErrorMessage['source']);
    }

    /**
     * Tests the generate method with various option combinations.
     *
     * @param array<string, bool> $options The options to pass to the formatter.
     * @param string $expectedSourceAssertion 'present' or 'absent' for the 'source' key.
     * @param bool $withHelp Whether the help text should be in the message.
     * @param bool $withRef Whether the ref link should be in the message.
     */
    #[DataProvider('optionsProvider')]
    public function testGenerateWithOptions(array $options, string $expectedSourceAssertion, bool $withHelp, bool $withRef): void
    {
        // Create a new formatter instance with the specific options for this test
        $this->formatter->setOptions($options);
        $report = $this->createTestReport();

        $issues = $report->getIssues();
        /** @var Issue $testIssue */
        $testIssue = array_shift($issues)[0];

        $jsonOutput = $this->formatter->generate($report);
        $data = json_decode($jsonOutput, true);

        // Find the corresponding message in the JSON output
        $jsonMessage = $data['files'][$testIssue->getPath()]['messages'][0];

        // Assert 'source' key presence based on 'show-code' option
        if ($expectedSourceAssertion === 'present') {
            $this->assertArrayHasKey('source', $jsonMessage);
            $this->assertEquals($testIssue->getCode(), $jsonMessage['source']);
        } else {
            $this->assertArrayNotHasKey('source', $jsonMessage);
        }

        // Build the expected message dynamically from the source issue
        $expectedMessage = $testIssue->getMessage();
        if ($withHelp && $testIssue->getHelp()) {
            $expectedMessage .= " ({$testIssue->getHelp()})";
        }
        if ($withRef && $testIssue->getRef()) {
            $expectedMessage .= " [{$testIssue->getRef()}]";
        }

        // Assert message content based on 'show-help' and 'show-ref' options
        $this->assertEquals($expectedMessage, $jsonMessage['message']);
    }

    /**
     * Data provider for testing generator options.
     * This provider now only describes the configuration. The test itself builds the expected data.
     */
    public static function optionsProvider(): array
    {
        return [
            // 'no options' means explicitly setting these boolean options to false.
            'no options' => [
                'options' => ['show-code' => false, 'show-help' => false, 'show-ref' => false],
                'expectedSourceAssertion' => 'absent',
                'withHelp' => false,
                'withRef' => false,
            ],
            'show-code only' => [
                'options' => ['show-code' => true, 'show-help' => false, 'show-ref' => false],
                'expectedSourceAssertion' => 'present',
                'withHelp' => false,
                'withRef' => false,
            ],
            'show-help only' => [
                'options' => ['show-code' => false, 'show-help' => true, 'show-ref' => false],
                'expectedSourceAssertion' => 'absent',
                'withHelp' => true,
                'withRef' => false,
            ],
            'show-ref only' => [
                'options' => ['show-code' => false, 'show-help' => false, 'show-ref' => true],
                'expectedSourceAssertion' => 'absent',
                'withHelp' => false,
                'withRef' => true,
            ],
            'show-help and show-ref' => [
                'options' => ['show-code' => false, 'show-help' => true, 'show-ref' => true],
                'expectedSourceAssertion' => 'absent',
                'withHelp' => true,
                'withRef' => true,
            ],
            'all options enabled' => [
                'options' => ['show-code' => true, 'show-help' => true, 'show-ref' => true],
                'expectedSourceAssertion' => 'present',
                'withHelp' => true,
                'withRef' => true,
            ],
        ];
    }

    /**
     * Tests the JSON encoding options from JsonFormatTrait.
     *
     * @param array<string, bool> $options The JSON formatting options.
     * @param string $expectedSlash Expected format for file path with slashes.
     * @param string $expectedUnicode Expected format for a message with unicode characters.
     * @param bool $isPretty Whether the output should be pretty-printed.
     */
    #[DataProvider('jsonOptionsProvider')]
    public function testGenerateWithJsonFormattingOptions(array $options, string $expectedSlash, string $expectedUnicode, bool $isPretty): void
    {
        // The PhpCs formatter uses JsonFormatTrait, so we can test JSON options here.
        // We merge with other options set to false to isolate the JSON formatting behavior.
        $this->formatter->setOptions(array_merge([
            'show-code' => false,
            'show-help' => false,
            'show-ref' => false,
        ], $options));

        $report = $this->createTestReport();

        $jsonOutput = $this->formatter->generate($report);

        // Test slash escaping by checking the file path key
        $this->assertStringContainsString($expectedSlash, $jsonOutput);

        // Test unicode escaping by checking the message content
        $this->assertStringContainsString($expectedUnicode, $jsonOutput);

        // Test pretty printing
        if ($isPretty) {
            // Pretty-printed JSON has newlines.
            $this->assertStringContainsString("\n", $jsonOutput);
        } else {
            // Non-pretty JSON should not have newlines.
            $this->assertStringNotContainsString("\n", $jsonOutput);
        }
    }



    /**
     * Verifies that the parse method correctly constructs a Report object from a known JSON structure.
     */
    public function testParseCreatesCorrectReportObject(): void
    {
        $jsonInput = <<<JSON
{
    "totals": {"errors": 1, "warnings": 1, "fixable": 0},
    "files": {
        "src/File1.php": {
            "errors": 1, "warnings": 1,
            "messages": [
                {"message": "This is an error.", "source": "Some.Error.Rule", "type": "ERROR", "line": 10, "column": 5},
                {"message": "This is a warning.", "source": "Some.Warning.Rule", "type": "WARNING", "line": 20, "column": 15}
            ]
        }
    }
}
JSON;
        // Decode the input to use it for assertions, making the test self-verifying.
        $inputData = json_decode($jsonInput, true);
        $expectedErrorData = $inputData['files']['src/File1.php']['messages'][0];
        $expectedWarningData = $inputData['files']['src/File1.php']['messages'][1];

        $report = $this->formatter->parse($jsonInput);
        $this->assertInstanceOf(Report::class, $report);

        $issues = $report->getIssues(false, false);
        $this->assertCount(2, $issues);

        // Sort issues by line number to ensure consistent order for testing
        usort($issues, fn(Issue $a, Issue $b) => $a->getLine() <=> $b->getLine());

        $errorIssue = $issues[0];
        $this->assertEquals($expectedErrorData['message'], $errorIssue->getMessage());
        $this->assertEquals('src/File1.php', $errorIssue->getPath());
        $this->assertEquals($expectedErrorData['line'], $errorIssue->getLine());
        $this->assertEquals($expectedErrorData['column'], $errorIssue->getColumn());
        $this->assertEquals($expectedErrorData['source'], $errorIssue->getCode());
        $this->assertEquals(Report::SEVERITY_ERROR, $errorIssue->getSeverity());

        $warningIssue = $issues[1];
        $this->assertEquals($expectedWarningData['message'], $warningIssue->getMessage());
        $this->assertEquals('src/File1.php', $warningIssue->getPath());
        $this->assertEquals($expectedWarningData['line'], $warningIssue->getLine());
        $this->assertEquals($expectedWarningData['column'], $warningIssue->getColumn());
        $this->assertEquals($expectedWarningData['source'], $warningIssue->getCode());
        $this->assertEquals(Report::SEVERITY_WARNING, $warningIssue->getSeverity());
    }

    /**
     * Verifies that parsing an issue with no line, column, or source works correctly.
     */
    public function testParseWithMissingFields(): void
    {
        $jsonInput = <<<JSON
{
    "files": {
        "src/File1.php": {
            "messages": [
                {"message": "No line, column, or source here.", "type": "ERROR"}
            ]
        }
    }
}
JSON;
        $report = $this->formatter->parse($jsonInput);
        $issues = $report->getIssues(false, false);

        $this->assertCount(1, $issues);
        $this->assertEquals(1, $issues[0]->getLine(), 'Line should default to 1.');
        $this->assertEquals(1, $issues[0]->getColumn(), 'Column should default to 1.');
        $this->assertEquals(Issue::UNKNOW_CODE, $issues[0]->getCode(), 'Code should be '.Issue::UNKNOW_CODE.' when source is missing.');
    }

    /**
     * Verifies that the parse method throws an exception for malformed JSON.
     */
    public function testParseThrowsExceptionForInvalidJson(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid JSON input: Syntax error');
        $this->formatter->parse('this is not json');
    }

    /**
     * Verifies that the parse method throws an exception for JSON with a missing 'files' key.
     */
    public function testParseThrowsExceptionForMissingFilesKey(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Decoded JSON is not valid.');
        $this->formatter->parse('{"totals": {"errors": 1}}');
    }

    /**
     * Tests the full cycle of generating a report with full metadata and then parsing it back.
     * This ensures that all data (code, help, ref) can be preserved across generation and parsing.
     */
    public function testGenerateAndParseRoundTripWithFullMetadata(): void
    {
        // 1. Create an initial report
        $originalReport = $this->createTestReport();

        // 2. Create a formatter with all metadata options enabled
        $this->formatter->setOptions([
            'show-code' => true,
            'show-help' => true,
            'show-ref' => true,
            'parse-message' => true,
        ]);

        // 3. Generate the JSON string from it and parse it back
        $jsonOutput = $this->formatter->generate($originalReport);
        $parsedReport = $this->formatter->parse($jsonOutput);

        // 4. Compare the meaningful data of the reports
        self::assertEqualReport(
            $originalReport,
            $parsedReport,
            name: $this->formatter->getDefaultReportName(),
            warnings: $originalReport->getTotalWarnings()+$originalReport->getTotalTips(),
            tips: 0
        );

        $originalIssues = $originalReport->getIssues(false, true);
        $parsedIssues = $parsedReport->getIssues(false, false);

        $this->assertCount(count($originalIssues), $parsedIssues);

        // Sort issues to ensure consistent order for comparison
        $sortFunc = fn(Issue $a, Issue $b) => strcmp($a->getPath() . $a->getLine(), $b->getPath() . $b->getLine());
        usort($originalIssues, $sortFunc);
        usort($parsedIssues, $sortFunc);

        foreach ($originalIssues as $i => $originalIssue) {
            $parsedIssue = $parsedIssues[$i];

            $this->assertEquals($originalIssue->getMessage(), $parsedIssue->getMessage());
            $this->assertEquals($originalIssue->getHelp(), $parsedIssue->getHelp());
            $this->assertEquals($originalIssue->getRef(), $parsedIssue->getRef());
            $this->assertEquals($originalIssue->getPath(), $parsedIssue->getPath());
            $this->assertEquals($originalIssue->getLine(), $parsedIssue->getLine());
            $this->assertEquals($originalIssue->getColumn(), $parsedIssue->getColumn());
            $this->assertEquals($originalIssue->getCode(), $parsedIssue->getCode());

            // The PhpCs format maps TIP to WARNING. When parsing, it comes back as SEVERITY_WARNING.
            $expectedSeverity = ($originalIssue->getSeverity() === Report::SEVERITY_TIP)
                ? Report::SEVERITY_WARNING
                : $originalIssue->getSeverity();

            $this->assertEquals($expectedSeverity, $parsedIssue->getSeverity());
        }
    }
}