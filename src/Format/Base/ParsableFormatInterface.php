<?php

namespace Tuchsoft\IssueReporter\Format\Base;
use Tuchsoft\IssueReporter\Report;

/**
 * Defines the contract for report formats that can be parsed from a string.
 *
 * This interface extends the base FormatInterface by adding a `parse` method,
 * allowing a string representation of a report to be converted back into a
 * Report object. It also provides a set of constants to declare which
 * data fields are supported and preserved during the parsing process.
 */
interface ParsableFormatInterface extends FormatInterface {

    /**
     * @var string Constant indicating the format can parse a message to extract details.
     */
    const FEATURE_PARSABLE_MESSAGE = 'can-parse-the-report-message';

    /**
     * @var string Constant indicating the format preserves the original issue severity (error, warning, tip).
     */
    const FEATURE_PRESERVE_SEVERITY = 'preserves-the-severity-level-of-issues';

    /**
     * @var string Constant indicating the report name is stored and can be parsed.
     */
    const FEATURE_REPORT_NAME = 'includes-the-name-of-the-report';

    /**
     * @var string Constant indicating the report's base path is stored and can be parsed.
     */
    const FEATURE_REPORT_BASEPATH = 'includes-the-reports-base-path';

    /**
     * @var string Constant indicating the report's total execution time is stored and can be parsed.
     */
    const FEATURE_REPORT_TOTAL_TIME = 'includes-the-total-execution-time';

    /**
     * @var string Constant indicating the report's end time is stored and can be parsed.
     */
    const FEATURE_REPORT_TIME_END = 'includes-the-report-end-time';

    /**
     * @var string Constant indicating the report's start time is stored and can be parsed.
     */
    const FEATURE_REPORT_TIME_START = 'includes-the-report-start-time';

    /**
     * @var string Constant indicating the issue's line number is stored and can be parsed.
     */
    const FEATURE_ISSUE_LINE = 'includes-the-issues-line-number';

    /**
     * @var string Constant indicating the issue's column number is stored and can be parsed.
     */
    const FEATURE_ISSUE_COLUMN = 'includes-the-issues-column-number';

    /**
     * @var string Constant indicating the issue's help text is stored and can be parsed.
     */
    const FEATURE_ISSUE_HELP = 'includes-the-issues-help-text';

    /**
     * @var string Constant indicating the issue's reference link is stored and can be parsed.
     */
    const FEATURE_ISSUE_REF = 'includes-the-issues-reference-link';

    /**
     * @var string Constant indicating the issue's extra data is stored and can be parsed.
     */
    const FEATURE_ISSUE_EXTRA = 'includes-extra-issue-data';

    /**
     * @var string Constant indicating the issue's code is stored and can be parsed.
     */
    const FEATURE_ISSUE_CODE = 'includes-the-issues-code';

    /**
     * @var string[] An array of all feature constants, representing a fully-featured format.
     */
    const FEATURE_ALL = ["supports-all-features"];

    /**
     * Parses a string representation of a report into a Report object.
     *
     * @param string      $input The string data to parse.
     * @param string|null $name  An optional name for the report. If the format includes a report name,
     * this may be used as a fallback.
     * @return Report The parsed Report object.
     * @throws \InvalidArgumentException If the input string is malformed or cannot be parsed.
     */
    function parse(string $input, ?string $name = 'Parsed report'): Report;

    /**
     * Returns an array of supported feature strings for parsing.
     *
     * The returned value should be an array of the FEATURE_* string constants
     * defined in this interface. This indicates which data fields the format can
     * reliably natively parse from its string representation. These strings can
     * be converted to human-readable messages by replacing hyphens with spaces.
     *
     * @return string[] An array of supported feature strings.
     */
    static function supports(): array;

    /**
     * Returns an array of extra features supported by the format for parsing.
     *
     * This is similar to `supports()` but is intended for features that are
     * not supported natively or that may require additional parsing.
     * These features will likely not be supported by other tools, but the format
     * still has support for storing them in some way. These strings can also be
     * converted to human-readable messages.
     *
     * @return string[] An array of supported "extra" feature strings.
     */
    static function supportsExtra(): array;
}