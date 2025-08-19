<?php

namespace Tuchsoft\IssueReporter\Transformer\base;

use Tuchsoft\IssueReporter\Base\LoadableInterface;
use Tuchsoft\IssueReporter\Report;

interface TransformerInterface extends LoadableInterface
{

    function transform(Report &$report): void;

    static function isEnabled(array $options): bool;
}