<?php

namespace Tuchsoft\IssueReporter\Transformer\base;

use Symfony\Component\Console\Input\InputOption;
use Tuchsoft\IssueReporter\Base\LoadableTrait;

abstract class AbstractTransformer implements TransformerInterface
{
    use LoadableTrait;

    protected static bool $enabledByDefault = true;

    public function __construct(array $options) {
        $this->setOptions($options);
    }

    static function getName(): string
    {
        $splitted = explode('\\', static::class);
        return strtolower(array_pop($splitted));
    }


    static public function getOptionsDefinition(int $returnType = self::OPTIONS_NORMAL):array {
        return[
            new InputOption(static::getName(), '',InputOption::VALUE_NEGATABLE, 'Enable (or don\'t enable --no-'.static::getName().') "'.static::getName().'" transformer', static::$enabledByDefault),
        ];
    }


    public static function isEnabled(array $options): bool
    {
       return $options[static::getName()] ?? static::$enabledByDefault;
    }


}