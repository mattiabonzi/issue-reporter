<?php


namespace Tuchsoft\IssueReporter\Test\Base;


use Tuchsoft\IssueReporter\Issue;

trait ParsableMessageTrait
{

    public function assertFullEmacsMessage(Issue $originalIssue, $msg, $help = true, $ref = false)
    {
        $fullMsg = "{$originalIssue->getPath()}:{$originalIssue->getLine()}:{$originalIssue->getColumn()}: {$originalIssue->getSeverityString()} - {$originalIssue->getMessage()} (#{$originalIssue->getCode()})";
        if ($help && $originalIssue->getHelp()) $fullMsg .= " ({$originalIssue->getHelp()})";
        if ($ref && $originalIssue->getRef()) $fullMsg .= " [{$originalIssue->getRef()}]";
        $this->assertEquals($fullMsg, trim($msg));
    }
}



