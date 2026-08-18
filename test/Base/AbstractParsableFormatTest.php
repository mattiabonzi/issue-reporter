<?php

namespace Tuchsoft\IssueReporter\Test\Base;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Richenzi\Pairwise\Pairwise;
use Tuchsoft\IssueReporter\Format\Base\AbstractFormat;
use Tuchsoft\IssueReporter\Format\Base\ParsableFormatInterface;
use Tuchsoft\IssueReporter\Issue;
use Tuchsoft\IssueReporter\Report;
use Tuchsoft\IssueReporter\Test\Base\Provider\OptionsMatrixProvider;
use Tuchsoft\IssueReporter\Test\Base\Provider\ReportProvider;

/**
 * An abstract test class for formats that implement ParsableFormatInterface.
 *
 * This class provides a standardized way to test the round-trip integrity
 * of a format's generation and parsing methods.
 */
abstract class AbstractParsableFormatTest extends AbstractFormatTest
{
    use ReportProvider;
    use OptionsMatrixProvider;

    protected static string $formatterClass;

    /**
     * Combines data from all necessary providers into a single test matrix.
     *
     * @return array
     */
    public static function getCombinedMatrix(): array
    {
        $options = self::optionsMatrixProvider(static::$formatterClass);

        $matrix =  self::reportMatrixProvider();
        $matrixKey = array_keys($matrix);
        $matrix = array_map(fn ($e) => array_combine($matrixKey, $e),Pairwise::fromData($matrix)->generate());

        $combinations = [[]];

        foreach (['matrix' => $matrix, ...$options] as $optionName => $optionValues) {
            $newCombinations = [];
            foreach ($combinations as $combo) {
                foreach ($optionValues as $value) {
                    $newCombinations[] = array_merge($combo, [$optionName => $value]);
                }
            }
            $combinations = $newCombinations;
        }

        $result = [];
        foreach ($combinations as $i => $comb) {
            $matrix = $comb['matrix'];
            unset($comb['matrix']);
            $matrix = array_merge($matrix, $comb);
            $result["[$i] ".json_encode($matrix)] = ['options' => $matrix];
        }

        return $result;
    }

    public function testSupport() {
        self::assertNotEmpty($this->formatter::supports());
    }


    /**
     * The main round-trip test method. It runs automatically with a data matrix
     * containing every combination of a comprehensive report and all format options.
     *
     * @param array $issueOptions
     * @param array $complexOptions
     * @param array $options
     */
    #[DataProvider('getCombinedMatrix')]
    public function testRoundtrip(array $options): void
    {
        $originalReport = $this->createTestReport($options);
        $this->formatter->setOptions($options);

        $generatedString = $this->formatter->generate($originalReport);

        if (!empty($originalReport->getIssues(false))) {
            $this->assertNotEmpty($generatedString, 'The generated string should not be empty.');
        }

        $parsedReport =  $this->formatter->parse($generatedString, $originalReport->getName());

        $supports = $this->formatter::supports();
        if ($options['parse-message']) {
            $supports = array_merge($supports, $this->formatter::supportsExtra());
        }
        $this->assertFeatures($originalReport, $parsedReport,  $supports, $options);


        $this->assertCustomRoundtrip($originalReport, $parsedReport, $options);
    }

    /**
     * Asserts that report-level features are preserved.
     *
     * @param Report $original The original report.
     * @param Report $parsed   The parsed report.
     * @param array  $features An array of supported feature constants.
     */
    protected function assertFeatures(Report $original, Report $parsed, array $features, array $options): void
    {
        if (in_array(ParsableFormatInterface::FEATURE_REPORT_NAME, $features)) {
            $this->assertEquals($original->getName(), $parsed->getName(), 'Report name not preserved.');
        }
        if (in_array(ParsableFormatInterface::FEATURE_REPORT_BASEPATH, $features)) {
            $this->assertEquals($original->getBasePath(), $parsed->getBasePath(), 'Base path not preserved.');
        }
        if (in_array(ParsableFormatInterface::FEATURE_REPORT_TOTAL_TIME, $features)) {
            $this->assertGreaterThanOrEqual($original->getTotalTime(), $parsed->getTotalTime(), 'Total time is less than original.');
        }
        if (in_array(ParsableFormatInterface::FEATURE_REPORT_TIME_START, $features)) {
            //2 ms of margin because of round()
            $this->assertGreaterThanOrEqual($original->getTimeStart()-0.0002, $parsed->getTimeStart(), 'Start time is less than original.');
        }
        if (in_array(ParsableFormatInterface::FEATURE_REPORT_TIME_END, $features)) {
            //2 ms of margin because of round()
            $this->assertGreaterThanOrEqual($original->getTimeEnd()-0.0002, $parsed->getTimeEnd(), 'End time is less than original.');
        }
        if (in_array(ParsableFormatInterface::FEATURE_PRESERVE_SEVERITY, $features)) {
            $this->assertEquals($original->getTotalErrors(), $parsed->getTotalErrors(), 'Total errors do not match.');
            $this->assertEquals($original->getTotalWarnings(), $parsed->getTotalWarnings(), 'Total warnings do not match.');
            $this->assertEquals($original->getTotalTips(), $parsed->getTotalTips(), 'Total tips do not match.');
        }

        $this->assertEquals($original->getTotalFiles(), $parsed->getTotalFiles(), 'Total files do not match.');

        $originalIssues = $original->getIssues(false);
        $parsedIssues = $parsed->getIssues(false);

        $this->assertCount(count($originalIssues), $parsedIssues, 'The total number of issues should match.');

        usort($originalIssues, fn($a, $b) => strcmp($a->getPath() . $a->getLine(), $b->getPath() . $b->getLine()));
        usort($parsedIssues, fn($a, $b) => strcmp($a->getPath() . $a->getLine(), $b->getPath() . $b->getLine()));

        foreach ($originalIssues as $i => $originalIssue) {
            $parsedIssue = $parsedIssues[$i];

            if (in_array(ParsableFormatInterface::FEATURE_ISSUE_LINE, $features)) {
                $this->assertEquals($originalIssue->getLine(), $parsedIssue->getLine(), 'Line number not preserved.');
            }
            if (in_array(ParsableFormatInterface::FEATURE_ISSUE_COLUMN, $features)) {
                $this->assertEquals($originalIssue->getColumn(), $parsedIssue->getColumn(), 'Column number not preserved.');
            }
            if (in_array(ParsableFormatInterface::FEATURE_ISSUE_CODE, $features)) {
                $this->assertEquals($originalIssue->getCode(), $parsedIssue->getCode(), 'Code not preserved.');
            }
            if (in_array(ParsableFormatInterface::FEATURE_PRESERVE_SEVERITY, $features)) {
                $this->assertEquals($originalIssue->getSeverity(), $parsedIssue->getSeverity(), 'Severity not preserved.');
            }
            if (in_array(ParsableFormatInterface::FEATURE_ISSUE_HELP, $features)) {
                if (!isset($options['show-help']) || $options['show-help']) {
                    $this->assertEquals($originalIssue->getHelp(), $parsedIssue->getHelp(), 'Help not preserved.');
                } else {
                    $this->assertEmpty($parsedIssue->getHelp(), 'Help should be empty.');
                }
            }
            if (in_array(ParsableFormatInterface::FEATURE_ISSUE_REF, $features)) {
                if (!isset($options['show-ref']) || $options['show-ref']) {
                    $this->assertEquals($originalIssue->getRef(), $parsedIssue->getRef(), 'Ref not preserved.');
                } else {
                    $this->assertEmpty($parsedIssue->getRef(), 'Ref should be empty.');
                }
            }
            if (in_array(ParsableFormatInterface::FEATURE_ISSUE_EXTRA, $features)) {
                $this->assertEquals($originalIssue->getExtra(), $parsedIssue->getExtra(), 'Extra data not preserved.');
            }
            if (in_array(ParsableFormatInterface::FEATURE_ISSUE_MESSAGE, $features)) {
                $this->assertEquals($originalIssue->getMessage(), $parsedIssue->getMessage(), 'Message not preserved.');
            }
        }
    }



    /**
     * A hook for custom, format-specific assertions.
     *
     * @param Report $original The original report.
     * @param Report $parsed   The parsed report.
     * @param array  $options  The options used for this specific test case.
     */
    protected function assertCustomRoundtrip(Report $original, Report $parsed, array $options): void
    {
        // Default implementation does nothing.
    }
}