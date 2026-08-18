<?php


namespace Tuchsoft\IssueReporter\Test\Base\Provider;


trait XmlOptionsProvider
{

    /**
     * Data provider for testing XML formatting options from JsonFormatTrait.
     */
    public static function xmlOptionsProvider(): array
    {
        return [
            'pretty disabled (default)' => ['options' => ['pretty' => false], 'isPretty' => false],
            'pretty enabled' => ['options' => ['pretty' => true], 'isPretty' => true],
        ];
    }
}