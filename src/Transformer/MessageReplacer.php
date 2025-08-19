<?php

namespace Tuchsoft\IssueReporter\Transformer;

use Mustache\Engine;
use Tuchsoft\IssueReporter\Report;

class MessageReplacer extends base\AbstractTransformer
{
    protected Engine $mustache;

    public function __construct(array $options)
    {
        parent::__construct($options);
        $this->mustache = new Engine();
    }

    static function getDesc(): string
    {
        return 'Replace mustache syntax in "message" and "help" field with data in issue->extra';
    }

    /**
     * @inheritDoc
     */
    function transform(Report &$report): void
    {
        $this->trasnformReport($report);
        foreach ($report->getSubReports() as $subReport) {
            $this->transform($subReport);
        }
    }

    private function trasnformReport(Report &$report): void {
        foreach ($report->getIssues(false, false) as $issue) {
            $issue
                ->setMessage($this->mustache->render($issue->getMessage(), $issue->getExtra()))
                ->setHelp($this->mustache->render($issue->getHelp(), $issue->getExtra()));
        }
    }
}