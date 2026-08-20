<?php

namespace Tuchsoft\IssueReporter\Format\Base;

use Tuchsoft\IssueReporter\Issue;

use Parsedown;

trait HtmlFormatTrait
{
    use MdFormatTrait;

    public function initHtml(array $options): void
    {
        $this->initMd($options);
    }

    protected function getSeverityIcon(int $severity): string
    {
        return $this->getSeverityEmoji($severity) .  match ($severity) {
            Issue::SEVERITY_ERROR => '<span style="color:red;">ERROR</span>',
            Issue::SEVERITY_WARNING => '<span style="color:orange;">WARNING</span>',
            Issue::SEVERITY_TIP => '<span style="color:yellowgreen;">TIP</span>',
        };
    }

    protected function writeHtml() {
        return (new Parsedown())->text($this->writeMd());
    }

    public static function getFormat(): string
    {
        return self::FORMAT_HTML;
    }

}