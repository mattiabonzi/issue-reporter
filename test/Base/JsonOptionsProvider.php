<?php


namespace Tuchsoft\IssueReporter\Test\Base;


trait JsonOptionsProvider
{

    /**
     * Data provider for testing JSON formatting options from JsonFormatTrait.
     */
    public static function jsonOptionsProvider(): array
    {
        return [
            'default (no pretty, escape slashes, escape unicode)' => [
                'options' => ['pretty' => false, 'escape-slash' => true, 'escape-unicode' => true],
                'expectedSlash' => 'src\\/File1.php',
                'expectedUnicode' => 'u00e9',
                'isPretty' => false,
            ],
            'pretty print enabled' => [
                'options' => ['pretty' => true, 'escape-slash' => true, 'escape-unicode' => true],
                'expectedSlash' => 'src\\/File1.php',
                'expectedUnicode' => 'u00e9',
                'isPretty' => true,
            ],
            'no escape slashes' => [
                'options' => ['pretty' => false, 'escape-slash' => false, 'escape-unicode' => true],
                'expectedSlash' => 'src/File1.php',
                'expectedUnicode' => 'u00e9',
                'isPretty' => false,
            ],
            'no escape unicode' => [
                'options' => ['pretty' => false, 'escape-slash' => true, 'escape-unicode' => false],
                'expectedSlash' => 'src\\/File1.php',
                'expectedUnicode' => 'é',
                'isPretty' => false,
            ],
            'all json options modified' => [
                'options' => ['pretty' => true, 'escape-slash' => false, 'escape-unicode' => false],
                'expectedSlash' => 'src/File1.php',
                'expectedUnicode' => 'é',
                'isPretty' => true,
            ],
        ];
    }
}