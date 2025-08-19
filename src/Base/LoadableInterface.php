<?php

namespace Tuchsoft\IssueReporter\Base;
use Symfony\Component\Console\Input\InputOption;


interface LoadableInterface {

    const OPTIONS_NORMAL = 1;
    const OPTIONS_PREFIX = 2;
    const OPTIONS_BOTH = 3;

    static function getName(): string;

    static function getDesc(): string;

    /**
     * @return InputOption[]
     */
    static function getOptionsDefinition(int $returnType = self::OPTIONS_NORMAL): array;
    function setOptions(array $options);

}