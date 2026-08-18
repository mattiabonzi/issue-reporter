<?php

namespace Tuchsoft\IssueReporter\Test\Base;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Richenzi\Pairwise\Pairwise;
use Tuchsoft\IssueReporter\Format\Base\ParsableFormatInterface;
use Tuchsoft\IssueReporter\Issue;
use Tuchsoft\IssueReporter\Report;
use Tuchsoft\IssueReporter\Test\Base\AbstractParsableFormatTest;
use Tuchsoft\IssueReporter\Test\Base\Provider\JsonOptionsProvider;
use Tuchsoft\IssueReporter\Test\Base\Provider\OptionsMatrixProvider;
use Tuchsoft\IssueReporter\Test\Base\Provider\ReportProvider;

/**
 * An abstract test class for formats that implement ParsableFormatInterface.
 *
 * This class provides a standardized way to test the round-trip integrity
 * of a format's generation and parsing methods.
 */
abstract class AbstractJsonFormatTest extends AbstractParsableFormatTest
{

    use JsonOptionsProvider;

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
        $report = $this->getFixedTestReport();
        $jsonOutput = $this->formatter->generate($report);

        $this->assertJson($jsonOutput);

        // Test slash escaping by checking the file path key
        $this->assertStringContainsString($expectedSlash, $jsonOutput);

        // Test unicode escaping by checking the message content
        $this->assertStringContainsString($expectedUnicode, $jsonOutput);

        // Test pretty printing
        if ($isPretty) {
            // Pretty-printed JSON start with {\n or [\n
            $this->assertMatchesRegularExpression("/^[\{\[]\s*\n/", trim($jsonOutput));
        } else {
            // Non-pretty JSON does not start with {\n or [\n
            $this->assertDoesNotMatchRegularExpression("/^[\{\[]\s*\n/", trim($jsonOutput));
        }
    }
}