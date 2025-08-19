<?php

namespace Tuchsoft\IssueReporter\Format\Base;
use Tuchsoft\IssueReporter\Base\LoadableInterface;
use Tuchsoft\IssueReporter\Report;


interface FormatInterface extends LoadableInterface {

    const FORMAT_TXT = 'txt';
    const FORMAT_HTML = 'html';
    const FORMAT_XML = 'xml';
    const FORMAT_JSON = 'json';
    const FORMAT_MD = 'markdown';




    static function getFormat(): string;

    static function getDefaultReportName(): string;
    function generate(Report $report): string;

}