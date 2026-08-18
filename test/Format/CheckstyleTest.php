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
use Tuchsoft\IssueReporter\Test\Base\AbstractXmlFormatTest;
use Tuchsoft\IssueReporter\Test\Base\Provider\ReportProvider;

/**
 * Tests for the Checkstyle format.
 *
 * This class contains unit tests for both generating and parsing Checkstyle XML reports.
 * It verifies that the output is well-formed and respects various formatting options,
 * and that parsing correctly handles valid and invalid inputs.
 */
#[CoversClass(\Tuchsoft\IssueReporter\Format\Checkstyle::class)]
#[Group('Checkstyle')]
class CheckstyleTest extends AbstractXmlFormatTest
{
    use ReportProvider;

    /**
     * The Checkstyle formatter instance used for testing.
     * @var Checkstyle $formatter
     */
    protected FormatInterface $formatter;

    /**
     * The class name of the formatter under test.
     * @var string
     */
    protected static string $formatterClass = Checkstyle::class;

    /**
     * Sets up the test environment before each test.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->formatter = new Checkstyle();
    }

    /**
     * Data provider for testing generation with option combinations.
     * Tests combinations of 'parse-message', 'show-help', and 'show-ref'.
     *
     * @return array<string, array{options: array<string, bool>, expected: array<int, array<string, mixed>>}> Test cases.
     */
    public static function generateProvider(): array
    {

        $expected = [
            ['ERROR on line 10:5 - ', 'This is a critical error.', '  (Help message for a critical error)', '  [https://example.com/error-example]', ],
            ['WARNING on line 25:15 - ', 'This is a warning.', '  (Help message for a warning)', '', ],
            ['TIP on line 50 - ', 'This is just a helpful tip.', '', '  [https://example.com/tip-example]', ],
            [ 'WARNING - ', 'This issue has no line number.', '', '',],
            [],
            ['WARNING - ', 'This warning is outside src', '  (So the computed basepath should be equal to /project/base)', '', ],
            ['ERROR - ', 'This error is outside src', '  (Random help)', '  [https://example.com/random]',]
        ];

        return [
            'Default (no option)' => [
                'options' => [],
                'expected' => [
                    0 => [
                        'message' => $expected[0][0].$expected[0][1].$expected[0][2],
                        'line' => 10,
                        'column' => 5,
                        'source' => '0error.rule',
                        'severity' => 'error',
                        'path' => '/project/base/src/File1.php'
                    ],
                    1 => [
                        'message' => $expected[1][0].$expected[1][1].$expected[1][2],
                    ],
                    2 => [
                        'message' => $expected[2][0].$expected[2][1].$expected[2][2],
                        'line' => 50,
                        'column' => 0,
                        'source' => '2tip.rule',
                        'severity' => 'info',
                        'path' => '/project/base/src/File2.php'
                    ],
                    3 => [
                        'message' => $expected[3][0].$expected[3][1].$expected[3][2],
                        'line' => 0,
                        'column' => 0,
                        'source' => '3noline.rule',
                        'severity' => 'warning',
                        'path' => '/project/base/src/File3.php'
                    ],
                    5 => [
                        'message' => $expected[5][0].$expected[5][1].$expected[5][2],
                        'line' => 0,
                        'column' => 0,
                        'source' => '5outside.src',
                        'severity' => 'warning',
                        'path' => '/project/base/xyz/File.php'
                    ],
                    6 => [
                        'message' => $expected[6][0].$expected[6][1].$expected[6][2],
                    ]
                ],
            ],
            'No parse-message,  show-help & show-ref' => [
                'options' => ['parse-message' => false, 'show-help' => true, 'show-ref' => true],
                'expected' => [
                    0 => [
                        'message' => $expected[0][1]
                    ],
                    3 => [
                        'message' => $expected[3][1]
                    ]
                ],
            ],
            'Parse-message, show-help & show-ref' => [
                'options' => ['parse-message' => true, 'show-help' => true, 'show-ref' => true],
                'expected' => [
                    0 => [
                        'message' => $expected[0][0].$expected[0][1].$expected[0][2].$expected[0][3],
                    ],
                    3 => [
                        'message' => $expected[3][0].$expected[3][1].$expected[3][2].$expected[3][3],
                    ]
                ],
            ],
            'Parse-message, mo show-help, show-ref' => [
                'options' => ['parse-message' => true, 'show-help' => false, 'show-ref' => true],
                'expected' => [
                    0 => [
                        'message' => $expected[0][0].$expected[0][1].$expected[0][3],
                    ],
                    3 => [
                        'message' => $expected[3][0].$expected[3][1].$expected[3][3],
                    ]
                ],
            ],
            'Parse-message, show-help, no show-ref' => [
                'options' => ['parse-message' => true, 'show-help' => true, 'show-ref' => false],
                'expected' => [
                    0 => [
                        'message' => $expected[0][0].$expected[0][1].$expected[0][2],
                    ],
                    3 => [
                        'message' => $expected[3][0].$expected[3][1].$expected[3][2],
                    ]
                ],
            ],
            'Parse-message, no show-help, no show-ref' => [
                'options' => ['parse-message' => true, 'show-help' => false, 'show-ref' => false],
                'expected' => [
                    0 => [
                        'message' => $expected[0][0].$expected[0][1],
                    ],
                    3 => [
                        'message' => $expected[3][0].$expected[3][1],
                    ]
                ],
            ],
        ];
    }

    /**
     * Data provider for testing 'parse-message' option during parsing.
     * Note: 'show-help' and 'show-ref' are generation-only options and do not affect parsing.
     *
     * @return array<string, array{options: array<string, bool>, xmlInput: string, expectedIssues: array<int, array<string, mixed>>}> Test cases.
     */
    public static function parseProvider(): array
    {
        $msg1 = 'This is an error.';
        $msg2_parsable = 'Warning on line 13:12 - This is a warning. (still [message])  (This is help.)  [https://ref.com]';
        $msg3 = 'This is a tip.';

        $xmlTemplate = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<checkstyle version="3.13.3">
 <file name="src/File1.php">
  <error line="10" column="5" severity="error" message="%s" source="Some.Error.Rule"/>
  <error line="20" column="15" severity="warning" message="%s" source="Some.Warning.Rule"/>
  <error line="30" column="1" severity="info" message="%s" source="Some.Tip.Rule"/>
 </file>
</checkstyle>
XML;
        $xmlInput = sprintf($xmlTemplate, htmlspecialchars($msg1), htmlspecialchars($msg2_parsable), htmlspecialchars($msg3));

        return [
            'parse-message disabled' => [
                'options' => ['parse-message' => false],
                'xmlInput' => $xmlInput,
                'expectedIssues' => [
                    ['message' => $msg1, 'help' => '', 'ref' => '', 'severity' => Issue::SEVERITY_ERROR],
                    ['message' => $msg2_parsable, 'help' => '', 'ref' => '', 'severity' => Issue::SEVERITY_WARNING],
                    ['message' => $msg3, 'help' => '', 'ref' => '', 'severity' => Issue::SEVERITY_TIP],
                ],
            ],
            'parse-message enabled' => [
                'options' => ['parse-message' => true],
                'xmlInput' => $xmlInput,
                'expectedIssues' => [
                    ['message' => 'This is an error.', 'help' => '', 'ref' => '', 'severity' => Issue::SEVERITY_ERROR],
                    ['message' => 'This is a warning. (still [message])', 'help' => 'This is help.', 'ref' => 'https://ref.com', 'severity' => Issue::SEVERITY_WARNING],
                    ['message' => 'This is a tip.', 'help' => '', 'ref' => '', 'severity' => Issue::SEVERITY_TIP],
                ],
            ],
        ];
    }

    /**
     * Verifies that the generate method produces a valid Checkstyle XML string, respecting options.
     *
     * @param array<string, bool> $options The formatting options.
     * @param array<int, array<string, mixed>> $expected The expected data for assertions.
     */
    #[DataProvider('generateProvider')]
    public function testGenerate(array $options, array $expected): void
    {
        $this->formatter->setOptions($options);

        $report = self::getFixedTestReport();
        $xmlOutput = $this->formatter->generate($report);

        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($xmlOutput);
        $this->assertInstanceOf(SimpleXMLElement::class, $xml, 'Generated output is not valid XML.');

        // Get all file and error nodes in the correct order
        $xmlIssues = [];
        $xmlFiles = $xml->xpath('//file');
        foreach ($xmlFiles as $file) {
            foreach ($file->error as $error) {
                $xmlIssues[] = $error;
            }
        }

        usort($xmlIssues, fn(SimpleXMLElement $a, SimpleXMLElement $b) => strcmp((string)$a['source'], (string)$b['source']));

        // Check if the number of issues in the XML matches the expected count
        $this->assertCount(count($report->getIssues(false)), $xmlIssues, 'Generated XML contains an unexpected number of issues.');

        // Loop through the expected data and verify each issue.
        foreach ($expected as $index => $data) {
            $this->assertArrayHasKey($index, $xmlIssues, "Error element at index $index not found in XML output.");

            $xmlIssue = $xmlIssues[$index];

            // Verify each property that is explicitly provided in the data provider array
            foreach ($data as $key => $value) {
                switch ($key) {
                    case 'message':
                        $this->assertEquals($value, (string)$xmlIssue['message'], "Message mismatch for issue at index $index.");
                        break;
                    case 'line':
                        $this->assertEquals($value, (int)$xmlIssue['line'], "Line mismatch for issue at index $index.");
                        break;
                    case 'column':
                        $this->assertEquals($value, (int)$xmlIssue['column'], "Column mismatch for issue at index $index.");
                        break;
                    case 'source':
                        $this->assertEquals($value, (string)$xmlIssue['source'], "Source mismatch for issue at index $index.");
                        break;
                    case 'severity':
                        $this->assertEquals($value, (string)$xmlIssue['severity'], "Severity mismatch for issue at index $index.");
                        break;
                    case 'path':
                        $parentFile = $xmlIssue->xpath('parent::file');
                        $this->assertEquals($value, (string)$parentFile[0]['name'], "Path mismatch for issue at index $index.");
                        break;
                }
            }
        }
    }

    /**
     * Verifies that the parse method correctly constructs a Report object, respecting options.
     *
     * @param array<string, bool> $options The parsing options.
     * @param string $xmlInput The Checkstyle XML string to parse.
     * @param array<int, array<string, mixed>> $expectedIssues The expected issue data after parsing.
     */
    #[DataProvider('parseProvider')]
    public function testParse(array $options, string $xmlInput, array $expectedIssues): void
    {
        $this->formatter->setOptions($options);

        $report = $this->formatter->parse($xmlInput, 'Parsed Checkstyle Report');
        $this->assertInstanceOf(Report::class, $report);
        $this->assertEquals('Parsed Checkstyle Report', $report->getName());

        $issues = $report->getIssues(false, false);
        $this->assertCount(count($expectedIssues), $issues);

        // Sort issues by line number to ensure consistent order for testing
        usort($issues, fn(Issue $a, Issue $b) => $a->getLine() <=> $b->getLine());

        foreach ($issues as $index => $issue) {
            $expected = $expectedIssues[$index];
            $this->assertEquals($expected['message'], $issue->getMessage());
            $this->assertEquals($expected['help'], $issue->getHelp());
            $this->assertEquals($expected['ref'], $issue->getRef());
            $this->assertEquals($expected['severity'], $issue->getSeverity());
        }

        $this->assertEquals('src/File1.php', $issues[0]->getPath());
        $this->assertEquals(10, $issues[0]->getLine());
        $this->assertEquals(5, $issues[0]->getColumn());
        $this->assertEquals('Some.Error.Rule', $issues[0]->getCode());

        $this->assertEquals('src/File1.php', $issues[1]->getPath());
        $this->assertEquals(20, $issues[1]->getLine());
        $this->assertEquals(15, $issues[1]->getColumn());
        $this->assertEquals('Some.Warning.Rule', $issues[1]->getCode());
    }


    /**
     * Verifies that parsing an empty but valid Checkstyle report results in a Report object with no issues.
     */
    public function testParseEmptyReport(): void
    {
        $report = $this->formatter->parse('<?xml version="1.0" encoding="UTF-8"?><checkstyle version="3.13.3"></checkstyle>');
        $this->assertNotNull($report);
        $this->assertEmpty($report->getIssues(false));
    }

    /**
     * Verifies that non-standard tags within a Checkstyle report are ignored during parsing.
     */
    public function testParseReportWithNonStandardTag(): void
    {
        $report = $this->formatter->parse('<?xml version="1.0" encoding="UTF-8"?><checkstyle version="3.13.3"><hello>word</hello></checkstyle>');
        $this->assertNotNull($report);
        $this->assertEmpty($report->getIssues(false));
    }

    /**
     * Verifies that parsing an XML file that is not a valid Checkstyle report throws an exception.
     */
    public function testParseNonCheckStyleReport(): void
    {
        $this->expectExceptionMessageMatches("/Invalid XML input.*/");
        $this->formatter->parse('<?xml version="1.0" encoding="UTF-8"?><testsuites name="xxx"></testsuites>');
    }
}