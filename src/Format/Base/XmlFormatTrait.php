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


    protected function xmlEncode(DOMDocument $doc):bool|string {
        $doc->formatOutput = $this->options['pretty'];
        $xml = $doc->saveXML();
        //remove the newline after the doc declaration
        if (!$this->options['pretty']) {
            $xml = preg_replace("/>\n/", '>', $xml,1);
        }
        return $xml;
    }


    protected function xmlDecode(string $input):DOMDocument {
        if (empty(trim($input))) {
            throw new \InvalidArgumentException('Invalid XML input: empty string');
        }
        libxml_use_internal_errors(true);
        libxml_clear_errors();
        $dom = new DOMDocument();
        $result = $dom->loadXML($input);
        if (!empty(libxml_get_errors())) {
            $errors = array_map(fn ($e) => $e->message, libxml_get_errors());
            libxml_clear_errors();
            throw new \InvalidArgumentException("Invalid XML input: " . join (' | ', $errors));
        }

        if (!$result || $dom->documentElement === null ) {
            throw new \InvalidArgumentException('Invalid XML input: unknow error');
        }

        return $dom;
    }

    public static function getFormat(): string
    {
        return self::FORMAT_XML;
    }


}