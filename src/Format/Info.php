<?php

namespace Tuchsoft\IssueReporter\Format;


use Tuchsoft\IssueReporter\Format\Base\AbstractFormat;
use Tuchsoft\IssueReporter\Format\Base\AnsiFormatTrait;
use Tuchsoft\IssueReporter\Format\Base\InfoFormatTrait;
use Tuchsoft\IssueReporter\Format\Base\NativeFormatInterface;
use Tuchsoft\IssueReporter\Report;

class Info extends AbstractFormat implements NativeFormatInterface
{

    use AnsiFormatTrait;
    use InfoFormatTrait;

    public function __construct(array $options = [])
    {
        parent::__construct($options);
        $this->initAnsi();
    }

    private int $maxWidth = 0;

    public function generate(Report $report): string
    {
        $this->maxWidth = $this->builder->getFormatter()->getLineWidth();
        $this->builder->getFormatter()->setLineWidth(0);
        $this->builder->title($report->getName());

        $this->generateSummary($report);
        $this->generateDetails($report);

        return $this->buffer->fetch();
    }

    protected function generateSummary(Report $report): void
    {
        list($headers, $rows) = $this->getSummary($report);
        $this->builder->table($headers, $rows);
    }

    protected function generateDetails(Report $report): void
    {
        if (!$report->hasIssues()) {
            $this->builder->success('No issues found. Everything looks good!');
            return;
        }

        $this->builder->section('Detailed Issues');
        $tables = $this->getDetails($report);
        foreach ($tables as $path => $table) {
            list($headers, $rows) = $table;
            $this->builder->text("File: <info>{$path}</info>");

            $table = $this->builder->createTable()
                ->setHeaders($headers)
                ->setRows($rows);

            if ($this->maxWidth) {
                foreach ($headers as $i => $col) {
                    $table->setColumnMaxWidth($i + 1, $this->maxWidth);
                }
            }

            $table->render();
            $this->builder->newLine();
        }

    }


    static function getName(): string
    {
        return 'info';
    }

    static function getDesc(): string
    {
       return 'Pretty formatted detailed info';
    }
}