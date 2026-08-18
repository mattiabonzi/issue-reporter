<?php

namespace Tuchsoft\IssueReporter\Test\Format;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tuchsoft\IssueReporter\Format\Base\FormatInterface;
use Tuchsoft\IssueReporter\Format\Emacs;
use Tuchsoft\IssueReporter\Issue;
use Tuchsoft\IssueReporter\Report;
use Tuchsoft\IssueReporter\Test\Base\AbstractParsableFormatTest;
use Tuchsoft\IssueReporter\Test\Base\Provider\ReportProvider;

/**
 * Tests for the Emacs format.
 *
 * This class contains unit tests for both generating and parsing Emacs-style reports.
 */
#[CoversClass(\Tuchsoft\IssueReporter\Format\Emacs::class)]
#[Group('Emacs')]
class EmacsTest extends AbstractParsableFormatTest
{
    use ReportProvider;

    /**
     * The formatter instance used for testing.
     * @var Emacs $formatter
     */
    protected FormatInterface $formatter;

    /**
     * The formatter class to test.
     * @var class-string<Emacs>
     */
    protected static string $formatterClass = Emacs::class;

    /**
     * Sets up the test environment before each test.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->formatter = new Emacs();
    }

    /**
     * Data provider for testing the generate method with various options.
     *
     * @return array<string, array{options: array<string, bool>, expectedLines: array<string>}>
     */
    public static function generateProvider(): array
    {

        $data = FIXED_TEST_DATA;
        $keys = array_keys($data);

        $severityData = ['error', 'warning', 'info', 'warning', 'error', 'warning', 'error',];

        $baseLines = array_map(fn($key) => sprintf(
            "%s:%d:%d: %s - %s (#%s)",
            $data[$key]['path'],
            $data[$key]['line'],
            $data[$key]['col'],
            $severityData[$key],
            $data[$key]['message'],
            $data[$key]['code']
        ), $keys);

        $withHelpOnly = array_map(fn($key) => trim("{$baseLines[$key]}" . ($data[$key]['help'] ? "  ({$data[$key]['help']})" : '')), $keys);
        $withRefAndHelp = array_map(fn($key) => trim("{$withHelpOnly[$key]}" . ($data[$key]['ref'] ? "  [{$data[$key]['ref']}]" : '')), $keys);
        $withRefOnly = array_map(fn($key) => trim("{$baseLines[$key]}" . ($data[$key]['ref'] ? "  [{$data[$key]['ref']}]" : '')), $keys);
        $withNeither = array_values($baseLines);

        return [
            'Default (show-ref=false, show-help=true)' => [
                'options' => [], // Default constructor should enable both
                'expectedLines' => $withHelpOnly,
            ],
            'Explicitly enabled (show-ref=true, show-help=true)' => [
                'options' => ['show-ref' => true, 'show-help' => true],
                'expectedLines' => $withRefAndHelp,
            ],
            'Help only (show-ref=false, show-help=true)' => [
                'options' => ['show-ref' => false, 'show-help' => true],
                'expectedLines' => $withHelpOnly,
            ],
            'Ref only (show-ref=true, show-help=false)' => [
                'options' => ['show-ref' => true, 'show-help' => false],
                'expectedLines' => $withRefOnly,
            ],
            'Neither (show-ref=false, show-help=false)' => [
                'options' => ['show-ref' => false, 'show-help' => false],
                'expectedLines' => $withNeither,
            ],
        ];
    }

    /**
     * Verifies that the generate method produces a correct Emacs-style string based on options.
     *
     * @param array<string, bool> $options The options to initialize the formatter with.
     * @param array<string> $expectedLines The expected lines of output.
     */
    #[DataProvider('generateProvider')]
    public function testGenerate(array $options, array $expectedLines): void
    {
        $formatter = new Emacs($options);
        $report = $this->getFixedTestReport();
        $generatedOutput = $formatter->generate($report);

        // Split into lines and remove any trailing empty lines
        $actualLines = array_filter(explode("\n", $generatedOutput));

        $this->assertCount(count($expectedLines), $actualLines, "The number of output lines should match the number of expected lines.");

        foreach ($actualLines as $i => $line) {
            $this->assertEquals($expectedLines[$i], $line);
        }


    }

    /**
     * Data provider for testing the parse method.
     *
     * @return array<string, array{input: string, expectedIssues: array<int, array<string, mixed>>}>
     */
    public static function parseProvider(): array
    {
        return [
            'Standard parsing with and without help text' => [
                'input' => implode("\n", [
                    'src/File1.php:10:5: error - This is an error. (#Some.Error.Rule)  (Some help text)',
                    'src/File1.php:20:15: warning - This is a warning. (#Some.Warning.Rule)',
                    'src/File2.php:30:1: warning - This is a tip. (#Some.Tip.Rule)  ()',
                ]),
                'expectedIssues' => [
                    [
                        'path' => 'src/File1.php',
                        'line' => 10,
                        'column' => 5,
                        'severity' => Issue::SEVERITY_ERROR,
                        'message' => 'This is an error.',
                        'code' => 'Some.Error.Rule',
                        'help' => 'Some help text',
                    ],
                    [
                        'path' => 'src/File1.php',
                        'line' => 20,
                        'column' => 15,
                        'severity' => Issue::SEVERITY_WARNING,
                        'message' => 'This is a warning.',
                        'code' => 'Some.Warning.Rule',
                        'help' => '',
                    ],
                    [
                        'path' => 'src/File2.php',
                        'line' => 30,
                        'column' => 1,
                        'severity' => Issue::SEVERITY_WARNING,
                        'message' => 'This is a tip.',
                        'code' => 'Some.Tip.Rule',
                        'help' => '',
                    ],
                ],
            ],
            'Comprehensive parsing with valid, invalid, and edge-case lines' => [
                'input' => <<<TEXT
# This is a report file.

src/File1.php:10:5: error - This is a standard valid error. (#Rule1)  (With help)
src/File2.php:20:15: warning - This is a standard valid warning. (#Rule2)

This is a completely invalid line that should be ignored.

src/File3.php:30:1: error - Message with malformed code #NoParen)
src/File4.php:40:1: warning - Message with malformed help (#Code) NoParen)
src/File5.php:50:not_a_line: error - Invalid line number, should be ignored.
src/File6.php:60:22: strikgnak - Unknown severity, should get default.  (#Rule3)
src/File7.php:70:1: error - Message with empty code/help (#)  ()
src/File8.php:80:1: warning - Message with spaces in code (#a b c)  (help)

// Another comment
src/File9.php:90:5: error - Another valid error. (#Rule9)  (Help for 9)
TEXT,
                'expectedIssues' => [
                    [
                        'path' => 'src/File1.php', 'line' => 10, 'column' => 5, 'severity' => Issue::SEVERITY_ERROR,
                        'message' => 'This is a standard valid error.', 'code' => 'Rule1', 'help' => 'With help',
                    ],
                    [
                        'path' => 'src/File2.php', 'line' => 20, 'column' => 15, 'severity' => Issue::SEVERITY_WARNING,
                        'message' => 'This is a standard valid warning.', 'code' => 'Rule2', 'help' => '',
                    ],
                    [
                        'path' => 'src/File3.php', 'line' => 30, 'column' => 1, 'severity' => Issue::SEVERITY_ERROR,
                        'message' => 'Message with malformed code #NoParen)', 'code' => Issue::UNKNOW_CODE, 'help' => '',
                    ],
                    [
                        'path' => 'src/File4.php', 'line' => 40, 'column' => 1, 'severity' => Issue::SEVERITY_WARNING,
                        'message' => 'Message with malformed help', 'code' => 'Code', 'help' => '',
                    ],
                    [
                        'path' => 'src/File6.php', 'line' => 60, 'column' => 22, 'severity' => Issue::SEVERITY_DEFAULT,
                        'message' => 'Unknown severity, should get default.', 'code' => 'Rule3', 'help' => '',
                    ],
                    [
                        'path' => 'src/File7.php', 'line' => 70, 'column' => 1, 'severity' => Issue::SEVERITY_ERROR,
                        'message' => 'Message with empty code/help (#)  ()', 'code' => Issue::UNKNOW_CODE, 'help' => '',
                    ],
                    [
                        'path' => 'src/File8.php', 'line' => 80, 'column' => 1, 'severity' => Issue::SEVERITY_WARNING,
                        'message' => 'Message with spaces in code (#a b c)  (help)', 'code' => Issue::UNKNOW_CODE, 'help' => '',
                    ],
                    [
                        'path' => 'src/File9.php', 'line' => 90, 'column' => 5, 'severity' => Issue::SEVERITY_ERROR,
                        'message' => 'Another valid error.', 'code' => 'Rule9', 'help' => 'Help for 9',
                    ],
                ],
            ],
            'Parsing empty input' => [
                'input' => '',
                'expectedIssues' => [],
            ],
        ];
    }

    /**
     * Verifies that the parse method correctly constructs a Report object from various inputs.
     *
     * @param string $input The Emacs-style string to parse.
     * @param array<int, array<string, mixed>> $expectedIssues The expected issue data after parsing.
     */
    #[DataProvider('parseProvider')]
    public function testParse(string $input, array $expectedIssues): void
    {
        $report = $this->formatter->parse($input, 'Parsed Emacs Report');
        $this->assertInstanceOf(Report::class, $report);
        $this->assertEquals('Parsed Emacs Report', $report->getName());

        $issues = $report->getIssues(false, false);
        $this->assertCount(count($expectedIssues), $issues, "The number of parsed issues should match the expected count.");

        if (empty($expectedIssues)) {
            return; // Nothing more to check
        }

        // Sort both actual and expected issues for predictable comparison
        usort($issues, fn(Issue $a, Issue $b) => ($a->getPath() . $a->getLine()) <=> ($b->getPath() . $b->getLine()));
        usort($expectedIssues, fn(array $a, array $b) => ($a['path'] . $a['line']) <=> ($b['path'] . $b['line']));

        foreach ($expectedIssues as $index => $expected) {
            $actualIssue = $issues[$index];
            $this->assertEquals($expected['path'], $actualIssue->getPath(), "Path mismatch for issue at index $index.");
            $this->assertEquals($expected['line'], $actualIssue->getLine(), "Line mismatch for issue at index $index.");
            $this->assertEquals($expected['column'], $actualIssue->getColumn(), "Column mismatch for issue at index $index.");
            $this->assertEquals($expected['severity'], $actualIssue->getSeverity(), "Severity mismatch for issue at index $index.");
            $this->assertEquals($expected['message'], $actualIssue->getMessage(), "Message mismatch for issue at index $index.");
            $this->assertEquals($expected['code'], $actualIssue->getCode(), "Code mismatch for issue at index $index.");
            $this->assertEquals($expected['help'], $actualIssue->getHelp(), "Help mismatch for issue at index $index.");
        }
    }
}