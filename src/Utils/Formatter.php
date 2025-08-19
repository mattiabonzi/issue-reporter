<?php

namespace Tuchsoft\IssueReporter\Utils;

use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;

class Formatter Extends OutputFormatter {

    public function __construct(bool $decorated = true, array $styles = [], private int $maxWidth = 0)
    {
        parent::__construct($decorated, []);
        $this->setStyle('info', new OutputFormatterStyle('blue'));
        $this->setStyle('error', new OutputFormatterStyle('bright-red'));
        $this->setStyle('warning', new OutputFormatterStyle('bright-yellow'));
        $this->setStyle('tip', new OutputFormatterStyle('bright-cyan'));

        foreach ($styles as $name => $style) {
            $this->setStyle($name, $style);
        }
    }

    public function format(?string $message)
    {
        return $this->formatAndWrap($message, $this->maxWidth);
    }

    public function setLineWidth($width): void {
        $this->maxWidth = $width;
    }

    public function getLineWidth(): int {
        return $this->maxWidth;
    }
}