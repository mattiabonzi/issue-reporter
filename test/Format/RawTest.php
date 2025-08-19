<?php

namespace Tuchsoft\IssueReporter\Test\Format;

use Tuchsoft\IssueReporter\Test\Base\AbstractTestFormat;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tuchsoft\IssueReporter\Format\Base\FormatInterface;
use Tuchsoft\IssueReporter\Format\Raw;
use Tuchsoft\IssueReporter\Test\Base\JsonOptionsProvider;
use Tuchsoft\IssueReporter\Test\Base\ReportProvider;


#[CoversClass(\Tuchsoft\IssueReporter\Format\Raw::class)]
#[Group('Raw')]
class RawTest extends AbstractTestFormat
{
    use ReportProvider;
    use JsonOptionsProvider;

    /**
     * @var Raw $formatter
     */
    protected FormatInterface $formatter;



    protected function setUp(): void
    {
        parent::setUp();
        $this->formatter = new Raw();
    }

    /**
     * Verifies that generate() produces a JSON string that matches the Report's serializable structure.
     */
    public function testGenerateProducesCorrectJson(): void
    {
        $report = $this->createTestReport();
        $jsonOutput = $this->formatter->generate($report);

        $this->assertJson($jsonOutput);

        $decodedData = json_decode($jsonOutput, true);
        $originalData = $report->jsonSerialize();

        // Compare the structure and top-level keys
        $this->assertEquals($originalData['name'], $decodedData['name']);
        $this->assertEquals($originalData['basePath'], $decodedData['basePath']);
        $this->assertArrayHasKey('issues', $decodedData);
        $this->assertArrayHasKey('subReports', $decodedData);
        $this->assertEquals($originalData['totalErrors'], $decodedData['totalErrors']);
        $this->assertEquals($originalData['totalWarnings'], $decodedData['totalWarnings']);
        $this->assertEquals($originalData['totalTips'], $decodedData['totalTips']);
    }

    /**
     * Tests the full cycle of generating a report and then parsing it back.
     * This is the most critical test for the Raw format, as it must be lossless.
     */
    public function testGenerateAndParseRoundTripIsLossless(): void
    {
        // 1. Create an initial report with diverse data
        $originalReport = $this->createTestReport();

        // 2. Generate the JSON string from it
        $jsonOutput = $this->formatter->generate($originalReport);

        // 3. Parse it back into a new Report object
        $parsedReport = $this->formatter->parse($jsonOutput);

        // 4. The most robust comparison is to re-serialize the parsed report
        // and compare it with the original JSON output.
        $this->assertJsonStringEqualsJsonString(
            $jsonOutput,
            json_encode($parsedReport->jsonSerialize())
        );

        // 5. For extra confidence, compare key properties directly.
        $this->assertEquals($originalReport->getName(), $parsedReport->getName());
        $this->assertEquals($originalReport->getTotalErrors(), $parsedReport->getTotalErrors());
        $this->assertEquals($originalReport->getTotalWarnings(), $parsedReport->getTotalWarnings());
        $this->assertEquals($originalReport->getTotalTips(), $parsedReport->getTotalTips());
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
        $this->formatter->setOptions($options);
        $report = $this->createTestReport();

        $jsonOutput = $this->formatter->generate($report);

        // Test slash escaping by checking a file path key in the 'issues' object
        $this->assertStringContainsString($expectedSlash, $jsonOutput);

        // Test unicode escaping by checking the message content
        $this->assertStringContainsString($expectedUnicode, $jsonOutput);

        // Test pretty printing
        if ($isPretty) {
            $this->assertStringContainsString("\n", $jsonOutput);
        } else {
            $this->assertStringNotContainsString("\n", $jsonOutput);
        }
    }

    /**
     * Verifies that the parse method throws an exception for malformed JSON.
     */
    public function testParseThrowsExceptionForInvalidJson(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid JSON input: Syntax error');
        $this->formatter->parse('{ "invalid": "json"');
    }

    /**
     * Verifies that the parse method throws an exception for data missing required keys.
     * This is handled by Report::fromJson and ensures data integrity.
     */
    public function testParseThrowsExceptionForInvalidStructure(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Missing required field: name');
        // A valid JSON, but not a valid Report structure
        $this->formatter->parse('{"issues": []}');
    }

    /**
     * Verifies the static description method.
     */
    public function testGetDesc(): void
    {
        // Assumes the typo in the method has been corrected
        $this->assertEquals('Complete JSON representation', Raw::getDesc());
    }
}