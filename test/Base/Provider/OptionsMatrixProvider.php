<?php

namespace Tuchsoft\IssueReporter\Test\Base\Provider;

use Tuchsoft\IssueReporter\Format\Base\AbstractFormat;

/**
 * Trait to generate a data matrix of all possible option combinations for a format.
 */
trait OptionsMatrixProvider
{
    /**
     * Generates a matrix of all boolean option combinations for a given format class.
     *
     * @param string $formatterClass The FQN of the formatter class.
     * @return array
     * @throws \ReflectionException
     */
    public static function optionsMatrixProvider(string $formatterClass): array
    {
        $options = array_intersect_key(
            $formatterClass::getOptionsDefinition(),
            AbstractFormat::getOptionsDefinition());
        $result = [];
        foreach ($options as $option) {
            $result[$option->getName()] = [true, false];
        }

        return $result;
    }
}