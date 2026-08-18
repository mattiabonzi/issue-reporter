<?php

namespace Tuchsoft\IssueReporter\Test\Base;

use PHPUnit\Framework\Attributes\DataProvider;
use Tuchsoft\IssueReporter\Test\Base\Provider\XmlOptionsProvider;

/**
 * An abstract test class for XML-based formats.
 *
 * This class provides a standardized way to test XML generation options
 * and parsing of invalid XML data. It should be extended by concrete test
 * classes for specific XML formatters.
 */
abstract class AbstractXmlFormatTest extends AbstractParsableFormatTest
{
    use XmlOptionsProvider;

    /**
     * Verifies that the parse method throws an exception for malformed XML.
     */
    public function testParseThrowsExceptionForInvalidXml(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid XML input');
        $this->formatter->parse('<invalid xml>');
    }


    /**
     * Verifies that the parse method throws an exception for an empty string
     */
    public function testParseThrowsExceptionForEmptyString(): void
    {
        $this->expectExceptionMessage('Invalid XML input: empty string');
        $this->formatter->parse(' ');
    }

    /**
     * Tests the XML formatting options.
     *
     * @param array<string, bool> $options The XML formatting options.
     * @param bool $isPretty Whether the output should be pretty-printed.
     */
    #[DataProvider('xmlOptionsProvider')]
    public function testXmlFormattingOptions(array $options, bool $isPretty): void
    {
        $this->formatter->setOptions($options);
        $report = $this->getFixedTestReport();
        $xmlOutput = $this->formatter->generate($report);

        // A basic check to ensure it's not empty and looks like XML.
        $this->assertStringStartsWith('<?xml ', $xmlOutput);

        // A more robust check to ensure the generated string is valid XML.
        libxml_use_internal_errors(true);
        $this->assertNotFalse(simplexml_load_string($xmlOutput), 'Generated output is not valid XML.');
        libxml_clear_errors();
        libxml_use_internal_errors(false);

        // Test pretty printing
        if ($isPretty) {
            // Pretty-printed XML contains newlines followed by whitespace for indentation.
            // This regex checks for newlines that are part of the structure, ignoring newlines within element content.
            $this->assertMatchesRegularExpression('/\n\s*</', $xmlOutput, 'Pretty-printed XML should have newlines for indentation.');
        } else {
            // Non-pretty XML should not have structural newlines for indentation.
            // Newlines are still allowed within element content.
            $this->assertDoesNotMatchRegularExpression('/\n\s*</', $xmlOutput, 'Non-pretty XML should not have newlines for indentation.');
        }
    }
}