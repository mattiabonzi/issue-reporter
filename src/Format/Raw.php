<?php

namespace Tuchsoft\IssueReporter\Format;

use Tuchsoft\IssueReporter\Format\Base\AbstractFormat;
use Tuchsoft\IssueReporter\Format\Base\JsonFormatTrait;
use Tuchsoft\IssueReporter\Format\Base\ParsableFormatInterface;
use Tuchsoft\IssueReporter\Report;

class Raw extends AbstractFormat implements ParsableFormatInterface
{
    use JsonFormatTrait;

    public function generate(Report $report): string
    {
        return $this->jsonEncode($report);
    }

    public function parse(string $input, $name = null): Report
    {
        $data = json_decode($input, true);
        if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
            throw new \InvalidArgumentException('Invalid JSON input: ' . json_last_error_msg());
        }

        return Report::fromJson($data);
    }

    static function getDesc(): string
    {
        return 'Complete JSON representation';
    }

    public static function supports(): array
    {
        return self::FEATURE_ALL;
    }

    public static function supportsExtra(): array
    {
        return [];
    }
}