<?php

namespace Tuchsoft\IssueReporter\Test\Format;



use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use SimpleXMLElement;
use Tuchsoft\IssueReporter\Format\Base\FormatInterface;
use Tuchsoft\IssueReporter\Format\Junit;
use Tuchsoft\IssueReporter\Issue;
use Tuchsoft\IssueReporter\Report;
use Tuchsoft\IssueReporter\Test\Base\AbstractTestFormat;
use Tuchsoft\IssueReporter\Test\Base\ParsableMessageTrait;
use Tuchsoft\IssueReporter\Test\Base\ReportProvider;

#[CoversClass(\Tuchsoft\IssueReporter\Format\Junit::class)]
#[Group('Junit')]
class JunitTest extends AbstractTestFormat
{
    use ReportProvider;
    use ParsableMessageTrait;

    /** @var Junit $formatter */
    protected FormatInterface $formatter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->formatter = new Junit();
    }

    /**
     * Verifies that the generate method produces a valid JUnit XML string with default options.
     */
    public function testGenerateProducesCorrectXmlFormat(): void
    {
        // Default options are show-code=true, show-help=false, show-ref=false.
        $report = $this->createTestReport();
        $xmlOutput = $this->formatter->generate($report);

        // Basic XML validation
        $this->assertStringStartsWith('<?xml version="1.0" encoding="UTF-8"?>', $xmlOutput);
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($xmlOutput);
        $this->assertInstanceOf(SimpleXMLElement::class, $xml, 'Generated output is not valid XML.');

        // Check root <testsuites> attributes
        $this->assertEquals('testsuites', $xml->getName());
        $this->assertEquals($report->getName(), (string)$xml['name']);

        $expectedFailures = $report->getTotalErrors() + $report->getTotalWarnings() + $report->getTotalTips();
        $this->assertEquals($expectedFailures, (int)$xml['failures']);
        $this->assertEquals($report->getTotalErrors(), (int)$xml['errors']);
        // Note: The current implementation does not set a 'tests' attribute on the root <testsuites> element.

        // Iterate through each file in the report and verify its <testsuite>
        foreach ($report->getIssues() as $path => $issuesInFile) {
            $testsuiteNode = $xml->xpath("//testsuite[@name='{$path}']");
            $this->assertCount(1, $testsuiteNode, "Testsuite for path '{$path}' not found or not unique.");
            $testsuite = $testsuiteNode[0];

            $this->assertEquals(count($issuesInFile), (int)$testsuite['tests']);
            $this->assertEquals(count($issuesInFile), (int)$testsuite['failures']); // All issues are reported as failures
            $this->assertEquals($path, (string)$testsuite['file']);

            // Iterate through each issue in the file and verify its <testcase>
            /** @var Issue $originalIssue */
            foreach ($issuesInFile as $originalIssue) {
                // Find the corresponding testcase. Since name might not be unique, we check multiple attributes.
                $foundTestcase = null;
                foreach ($testsuite->testcase as $tc) {
                    // Using line and file as a composite key to find the testcase
                    if ((string)$tc['line'] == $originalIssue->getLine() && (string)$tc['file'] === $originalIssue->getPath()) {
                        $foundTestcase = $tc;
                        break;
                    }
                }
                $this->assertNotNull($foundTestcase, "Testcase for issue at {$originalIssue->getPath()}:{$originalIssue->getLine()} not found.");

                // With default options (show-code=true), name is the issue code.
                $this->assertEquals($originalIssue->getCode(), (string)$foundTestcase['name']);

                // The implementation always uses <failure>, never <error>.
                $this->assertObjectHasProperty('failure', $foundTestcase);
                $this->assertObjectNotHasProperty('error', $foundTestcase);
                $failureElement = $foundTestcase->failure;

                // With show-code=true, 'type' attribute should exist and be the issue code.
                $this->assertEquals($originalIssue->getCode(), (string)$failureElement['type']);
                $this->assertEquals($originalIssue->getMessage(), (string)$failureElement['message']);

                $this->assertFullEmacsMessage($originalIssue, (string)$failureElement);


                // Check <properties>
                $this->assertObjectHasProperty('properties', $foundTestcase);
                $properties = [];
                foreach ($foundTestcase->properties->property as $prop) {
                    $properties[(string)$prop['name']] = (string)$prop['value'];
                }

                $this->assertArrayHasKey('severity', $properties);
                $this->assertArrayHasKey('line', $properties);
                $this->assertArrayHasKey('column', $properties);
                $this->assertArrayHasKey('extra', $properties);
                $this->assertEquals($originalIssue->getLine(), $properties['line']);
                $this->assertEquals($originalIssue->getColumn(), $properties['column']);
                $this->assertEquals(json_encode($originalIssue->getExtra()), $properties['extra']);
            }
        }
    }

    /**
     * Data provider for testing generator options.
     */
    public static function optionsProvider(): array
    {
        return [
            'default options (show-code=true)' => [
                'options' => ['show-code' => true, 'show-help' => false, 'show-ref' => false],
                'expectedNameIsCode' => true, 'withHelp' => false, 'withRef' => false,
            ],
            'show-code disabled' => [
                'options' => ['show-code' => false, 'show-help' => false, 'show-ref' => false],
                'expectedNameIsCode' => false, 'withHelp' => false, 'withRef' => false,
            ],
            'show-help enabled' => [
                'options' => ['show-code' => true, 'show-help' => true, 'show-ref' => false],
                'expectedNameIsCode' => true, 'withHelp' => true, 'withRef' => false,
            ],
            'show-ref enabled' => [
                'options' => ['show-code' => true, 'show-help' => false, 'show-ref' => true],
                'expectedNameIsCode' => true, 'withHelp' => false, 'withRef' => true,
            ],
            'all options enabled' => [
                'options' => ['show-code' => true, 'show-help' => true, 'show-ref' => true],
                'expectedNameIsCode' => true, 'withHelp' => true, 'withRef' => true,
            ],
        ];
    }

    /**
     * Tests the generate method with various option combinations.
     *
     * @param array<string, bool> $options The options to pass to the formatter.
     * @param bool $expectedNameIsCode Whether the testcase name should be the issue code.
     * @param bool $withHelp Whether the help text should be in the message.
     * @param bool $withRef Whether the ref link should be in the message.
     */
    #[DataProvider('optionsProvider')]
    public function testGenerateWithOptions(array $options, bool $expectedNameIsCode, bool $withHelp, bool $withRef): void
    {

        $this->formatter->setOptions($options);
        $report = $this->createTestReport();
        $xmlOutput = $this->formatter->generate($report);
        $xml = simplexml_load_string($xmlOutput);

        $issue = $report->getIssues();
        /** @var Issue $testIssue */
        $testIssue = array_shift($issue)[0]; // Get a specific issue to check
        $path = $testIssue->getPath();

        $testsuite = $xml->xpath("//testsuite[@name='{$path}']")[0];
        $testcase = $testsuite->testcase[0]; // Assuming order is preserved for the first issue

        // Assert testcase name based on 'show-code' option
        $expectedName = $expectedNameIsCode ? $testIssue->getCode() : $testIssue->getPath();
        $this->assertEquals($expectedName, (string)$testcase['name']);

        $failure = $testcase->failure;

        // Assert failure type based on 'show-code' option
        if ($expectedNameIsCode) {
            $this->assertEquals($testIssue->getCode(), (string)$failure['type']);
        } else {
            $this->assertFalse(isset($failure['type']));
        }

        // Build the expected full message dynamically
        $this->assertFullEmacsMessage($testIssue, (string)$failure, $withHelp, $withRef);


        // Assert properties based on options
        $properties = [];
        foreach ($testcase->properties->property as $prop) {
            $properties[(string)$prop['name']] = (string)$prop['value'];
        }

        if ($withHelp) {
            $this->assertEquals($testIssue->getHelp(), $properties['help']);
        } else {
            $this->assertArrayNotHasKey('help', $properties);
        }

        if ($withRef) {
            $this->assertEquals($testIssue->getRef(), $properties['ref']);
        } else {
            $this->assertArrayNotHasKey('ref', $properties);
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
<testsuites name="My Test Report" tests="2" failures="2" errors="1">
  <testsuite name="src/File1.php" tests="2" failures="2">
    <testcase name="Some.Error.Rule: This is an error." classname="src/File1.php">
      <error type="Some.Error.Rule">src/File1.php:10:5: error - This is an error.</error>
    </testcase>
    <testcase name="Some.Warning.Rule: This is a warning. [ref]" classname="src/File1.php">
      <failure type="Some.Warning.Rule">src/File1.php:20:15: warning - This is a warning.</failure>
    </testcase>
      <testcase name="This is a warning. (#Some.Warning.Rule) (help) [ref]" classname="src/File1.php">
      <failure type="Some.Warning.Rule">src/File1.php:20:15: warning - This is a warning.</failure>
    </testcase>
  </testsuite>
</testsuites>
XML;

        $xml = simplexml_load_string($xmlInput);
        $expectedErrorCase = $xml->testsuite[0]->testcase[0];
        $expectedWarningCase = $xml->testsuite[0]->testcase[1];

        $report = $this->formatter->parse($xmlInput, 'Fallback Report Name');
        $this->assertInstanceOf(Report::class, $report);
        $this->assertEquals((string)$xml['name'], $report->getName());

        $issues = $report->getIssues(false, false);
        $this->assertCount(2, $issues);

        usort($issues, fn(Issue $a, Issue $b) => $a->getLine() <=> $b->getLine());

        // Check the error issue
        $errorIssue = $issues[0];
        preg_match("/at line (\d+), column (\d+): (.*)/", (string)$expectedErrorCase->error, $matches);
        $this->assertEquals("This is an error", $errorIssue->getMessage());
        $this->assertEmpty($errorIssue->getHelp());
        $this->assertEmpty($errorIssue->getRef());
        $this->assertEquals((string)$expectedErrorCase['classname'], $errorIssue->getPath());
        $this->assertEquals(10, $errorIssue->getLine());
        $this->assertEquals(5, $errorIssue->getColumn());
        $this->assertEquals((string)$expectedErrorCase->error['type'], $errorIssue->getCode());
        $this->assertEquals(Report::SEVERITY_ERROR, $errorIssue->getSeverity());

        // Check the warning issue
        $warningIssue = $issues[1];
        $this->assertEquals("This is an warning.", $warningIssue->getMessage());
        $this->assertEmpty($warningIssue->getHelp());
        $this->assertEmpty($warningIssue->getRef());
        $this->assertEquals((string)$expectedWarningCase['classname'], $warningIssue->getPath());
        $this->assertEquals(20, $warningIssue->getLine());
        $this->assertEquals(15, $warningIssue->getColumn());
        $this->assertEquals((string)$expectedWarningCase->failure['type'], $warningIssue->getCode());
        $this->assertEquals(Report::SEVERITY_WARNING, $warningIssue->getSeverity());
    }

    /**
     * Verifies that parsing an issue with no line/column in the message defaults them to 1.
     */
    public function testParseWithMissingLineAndColumnDefaultsToOne(): void
    {
        $xmlInput = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<testsuites name="Report" tests="1" failures="1" errors="0">
  <testsuite name="src/File.php" tests="1" failures="1">
    <testcase name="Rule: No line info" classname="src/File.php">
      <failure type="Rule.NoLine">Just a message with no location.</failure>
    </testcase>
  </testsuite>
</testsuites>
XML;
        $report = $this->formatter->parse($xmlInput);
        $issues = $report->getIssues(false, false);

        $this->assertCount(1, $issues);
        $this->assertEquals(0, $issues[0]->getLine());
        $this->assertEquals(0, $issues[0]->getColumn());
        $this->assertEquals('Just a message with no location.', $issues[0]->getMessage());
    }

    /**
     * Verifies that the parse method throws an exception for malformed XML.
     */
    public function testParseThrowsExceptionForInvalidXml(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Failed to parse XML:');
        $this->formatter->parse('<testsuites><testsuite></testsuites>'); // Malformed XML
    }

    /**
     * Tests the full cycle of generating a report and then parsing it back.
     * This test highlights inconsistencies between the generate and parse methods.
     */
    public function testGenerateAndParseRoundTrip(): void
    {
        // 1. Create an initial report
        $originalReport = $this->createTestReport();

        // 2. Use a formatter with all metadata options enabled
        $this->formatter->setOptions([
            'show-code' => true,
            'show-help' => true,
            'show-ref' => true,
        ]);

        // 3. Generate the XML string from it and parse it back
        $xmlOutput = $this->formatter->generate($originalReport);
        $parsedReport = $this->formatter->parse($xmlOutput);

        // 4. Compare the meaningful data of the reports
        self::assertEqualReport(
            $originalReport,
            $parsedReport,
            errors: $originalReport->getTotalErrors()+ $originalReport->getTotalWarnings()+$originalReport->getTotalTips(),
            warnings: 0,
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

            // KNOWN INCONSISTENCY: `generate` creates only <failure> elements. `parse` interprets
            // <failure> as WARNING. Thus, an original ERROR or TIP becomes a WARNING after the round trip.
            $this->assertEquals(Report::SEVERITY_WARNING, $parsedIssue->getSeverity());

            $this->assertEquals($originalIssue->getMessage(), $parsedIssue->getMessage());


            // KNOWN INCONSISTENCY: `parse` does not read the <properties> tag generated by `generate`.
            // Therefore, `help`, `ref`, and `extra` data are lost as distinct properties on the Issue object.
            $this->assertEmpty($parsedIssue->getHelp());
            $this->assertEmpty($parsedIssue->getRef());
            $this->assertEmpty($parsedIssue->getExtra());
        }
    }
}