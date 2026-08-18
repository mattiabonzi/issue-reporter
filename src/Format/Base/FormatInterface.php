<?php

namespace Tuchsoft\IssueReporter\Format\Base;
use Tuchsoft\IssueReporter\Base\LoadableInterface;
use Tuchsoft\IssueReporter\Report;


/**
 * Defines the contract for a report formatter.
 *
 * A class implementing this interface is responsible for converting a Report object
 * into a specific string format (e.g., XML, JSON, text). It extends the
 * LoadableInterface to integrate with the application's loading and option
 * handling mechanisms.
 */
interface FormatInterface extends LoadableInterface {

    /**
     * @var string Constant for text format.
     */
    const FORMAT_TXT = 'txt';
    /**
     * @var string Constant for HTML format.
     */
    const FORMAT_HTML = 'html';
    /**
     * @var string Constant for XML format.
     */
    const FORMAT_XML = 'xml';
    /**
     * @var string Constant for JSON format.
     */
    const FORMAT_JSON = 'json';
    /**
     * @var string Constant for Markdown format.
     */
    const FORMAT_MD = 'markdown';
    /**
     * @var string Constant for YAML format.
     */
    const FORMAT_YML = 'yaml';

    /**
     * Gets the unique identifier for the format.
     *
     * This should typically return one of the FORMAT_* constants defined in this interface.
     *
     * @return string The format identifier (e.g., 'xml', 'json').
     */
    static function getFormat(): string;

    /**
     * Gets the default name for a report generated in this format.
     *
     * This is used, for example, when parsing a report from this format without
     * an explicit name being provided.
     *
     * @return string The default report name.
     */
    static function getDefaultReportName(): string;

    /**
     * Generates a string representation of the report in the specific format.
     *
     * @param Report $report The report object to serialize.
     * @return string The formatted report as a string.
     * @throws \Exception If an error occurs during generation (e.g., XML formatting error).
     */
    function generate(Report $report): string;

}