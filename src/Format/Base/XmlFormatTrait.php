<?php

namespace Tuchsoft\IssueReporter\Format\Base;

use DOMDocument;
use Symfony\Component\Console\Input\InputOption;

trait XmlFormatTrait {
    public static function getXmlOptions(int $returnType = self::OPTIONS_NORMAL):array {
        return [
            ...self::newOption('pretty', InputOption::VALUE_NEGATABLE, 'Force (or disable --no-color) prettied output', false, $returnType),
            ];
    }

    static public function getOptionsDefinition(int $returnType = self::OPTIONS_NORMAL):array {
        return[
            ...parent::getOptionsDefinition($returnType),
            ...self::getXmlOptions($returnType)
        ];
    }


    protected function saveXML(DOMDocument $doc):bool|string {
        $doc->formatOutput = $this->options['pretty'];
        return $doc->saveXML();
    }

    public static function getFormat(): string
    {
        return self::FORMAT_XML;
    }


}