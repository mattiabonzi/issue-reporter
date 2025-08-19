<?php

namespace Tuchsoft\IssueReporter\Test\Format;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use SimpleXMLElement;
use Tuchsoft\IssueReporter\Format\Base\FormatInterface;
use Tuchsoft\IssueReporter\Format\Checkstyle;
use Tuchsoft\IssueReporter\Issue;
use Tuchsoft\IssueReporter\Report;
use Tuchsoft\IssueReporter\Test\Base\AbstractTestFormat;
use Tuchsoft\IssueReporter\Test\Base\ReportProvider;

#[CoversClass(\Tuchsoft\IssueReporter\Format\Checkstyle::class)]
#[Group('Checkstyle')]
class CheckstyleTest extends AbstractTestFormat
{
    use ReportProvider;

    /**
     * @var Checkstyle $formatter
     */
    protected FormatInterface $formatter;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->formatter = new Checkstyle();
    }

    /**
     * Verifies that the generate method produces a valid Checkstyle XML string.
     */
    public function testGenerateProducesCorrectXmlFormat(): void
    {
        $report = $this->createTestReport();
        $x = $report->getIssues();
        $xmlOutput = $this->formatter->generate($report);

        // Basic XML validation
        $this->assertStringStartsWith('<?xml version="1.0" encoding="UTF-8"?>', $xmlOutput);
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($xmlOutput);
        $this->assertInstanceOf(SimpleXMLElement::class, $xml, 'Generated output is not valid XML.');

        // Check root <checkstyle> element
        $this->assertEquals('checkstyle', $xml->getName());

        // Iterate through each file in the report and verify its <file> element
        foreach ($report->getIssues() as $path => $issuesInFile) {
            $fileNode = $xml->xpath("//file[@name='{$path}']");
            $this->assertCount(1, $fileNode, "File element for path '{$path}' not found or not unique.");
            $fileElement = $fileNode[0];

            $this->assertCount(count($issuesInFile), $fileElement->error, "Incorrect number of <error> elements for file '{$path}'.");

            // Sort issues to have a predictable order for comparison
            usort($issuesInFile, fn(Issue $a, Issue $b) => $a->getLine() <=> $b->getLine());
            $errorElements = [];
            foreach ($fileElement->error as $error) {
                $errorElements[] = $error;
            }
            usort($errorElements, fn($a, $b) => (int)$a['line'] <=> (int)$b['line']);

            /** @var Issue $originalIssue */
            foreach ($issuesInFile as $index => $originalIssue) {
                $errorElement = $errorElements[$index];

                $this->assertEquals((string)$originalIssue->getLine(), (string)$errorElement['line']);
                $this->assertEquals((string)$originalIssue->getColumn(), (string)$errorElement['column']);
                $this->assertEquals($originalIssue->getMessage(), (string)$errorElement['message']);
                $this->assertEquals($originalIssue->getCode(), (string)$errorElement['source']);

                // Check severity mapping
                $expectedSeverity = match ($originalIssue->getSeverity()) {
                    Report::SEVERITY_ERROR => 'error',
                    default => 'warning', // WARNING and TIP map to 'warning'
                };
                $this->assertEquals($expectedSeverity, (string)$errorElement['severity']);
            }
        }
    }

    /**
     * Data provider for testing XML formatting options.
     */
    public static function xmlOptionsProvider(): array
    {
        return [
            'pretty disabled (default)' => ['options' => ['pretty' => false], 'isPretty' => false],
            'pretty enabled' => ['options' => ['pretty' => true], 'isPretty' => true],
        ];
    }

    /**
     * Tests the XML encoding options from XmlFormatTrait.
     *
     * @param array<string, bool> $options The XML formatting options.
     * @param bool $isPretty Whether the output should be pretty-printed.
     */
    #[DataProvider('xmlOptionsProvider')]
    public function testGenerateWithXmlFormattingOptions(array $options, bool $isPretty): void
    {
        $this->formatter->setOptions($options);
        $report = $this->createTestReport();
        $xmlOutput = $this->formatter->generate($report);

        // A pretty-printed XML will contain newlines for indentation.
        // A non-pretty one will be a single line (after the XML declaration).
        $hasNewlines = str_contains(trim(substr($xmlOutput, strpos($xmlOutput, '?>') + 2)), "\n");
        $this->assertEquals($isPretty, $hasNewlines);
    }

    /**
     * Verifies that the parse method correctly constructs a Report object.
     */
    public function testParseCreatesCorrectReportObject(): void
    {
        $xmlInput = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<checkstyle version="3.13.3">
 <file name="src/File1.php">
  <error line="10" column="5" severity="error" message="This is an error." source="Some.Error.Rule"/>
  <error line="20" column="15" severity="warning" message="This is a warning." source="Some.Warning.Rule"/>
  <error line="30" column="1" severity="info" message="This is a tip." source="Some.Tip.Rule"/>
 </file>
</checkstyle>
XML;

        $report = $this->formatter->parse($xmlInput, 'Parsed Checkstyle Report');
        $this->assertInstanceOf(Report::class, $report);
        $this->assertEquals('Parsed Checkstyle Report', $report->getName());

        $issues = $report->getIssues(false, false);
        $this->assertCount(3, $issues);

        // Sort issues by line number to ensure consistent order for testing
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

        // Check the tip (info) issue
        $tipIssue = $issues[2];
        $this->assertEquals('src/File1.php', $tipIssue->getPath());
        $this->assertEquals(30, $tipIssue->getLine());
        $this->assertEquals(1, $tipIssue->getColumn());
        $this->assertEquals('This is a tip.', $tipIssue->getMessage());
        $this->assertEquals('Some.Tip.Rule', $tipIssue->getCode());
        $this->assertEquals(Report::SEVERITY_TIP, $tipIssue->getSeverity());
    }

    /**
     * Verifies that the parse method throws an exception for malformed XML.
     */
    public function testParseThrowsExceptionForInvalidXml(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Failed to parse XML:');
        $this->formatter->parse('<checkstyle><file></checkstyle>'); // Malformed XML
    }

    /**
     * Tests the full cycle of generating a report and then parsing it back.
     * This test highlights inconsistencies between the generate and parse methods.
     */
    public function testGenerateAndParseRoundTrip(): void
    {
        // 1. Create an initial report
        $originalReport = $this->createTestReport();

        // 2. Generate the XML string from it and parse it back
        $xmlOutput = $this->formatter->generate($originalReport);
        $parsedReport = $this->formatter->parse($xmlOutput);

        // 3. Compare the meaningful data of the reports
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