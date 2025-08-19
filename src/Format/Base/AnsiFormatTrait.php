<?php

namespace Tuchsoft\IssueReporter\Format\Base;

use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tuchsoft\IssueReporter\Report;
use Tuchsoft\IssueReporter\Utils\Formatter;
use Tuchsoft\IssueReporter\Utils\NullInput;

trait AnsiFormatTrait
{
    use RichFormatTrait;

    protected BufferedOutput $buffer;
    protected SymfonyStyle $builder;

    public function initAnsi():void
    {
        $this->buffer = new BufferedOutput();
        $this->buffer->setVerbosity(OutputInterface::VERBOSITY_NORMAL);
        $this->buffer->setFormatter(new Formatter($this->options['color'], maxWidth: $this->options['max-width']));
        $this->builder = new SymfonyStyle(new NullInput(),  $this->buffer);
    }

    protected function getSeverityIcon(int $severity): string
    {
        return $this->getSeverityEmoji($severity) .  match ($severity) {
                Report::SEVERITY_ERROR => '<error>ERROR</error>',
                Report::SEVERITY_WARNING => '<warning>WARNING</warning>',
                Report::SEVERITY_TIP => '<tip>TIP</tip>'
            };
    }

    public static function getFormat(): string
    {
        return self::FORMAT_TXT;
    }

}