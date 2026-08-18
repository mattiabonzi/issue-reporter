<?php

namespace Tuchsoft\IssueReporter\Format\Base;


use Symfony\Component\Console\Input\InputOption;
use Tuchsoft\IssueReporter\Issue;

/**
 * Trait to provide common functionality for formats that need to build and parse
 * messages containing optional help and reference information.
 *
 * This is useful for formats that serialize all issue information into a single
 * string and need to deserialize it back into structured data.
 */
trait ParsableMessageFormatTrait {



    private static string $parsable_message_regex = "/(?<severity>[A-Za-z]+?)\s*(?:on\sline\s(?<line>\d+))?(?::(?<col>\d+))?\s*-\s*(?<message>.+?)(?:\s{2,}\((?<help>.+)\))?(?:\s{2,}\[(?<ref>.+)\])?$/";
    /**
     * @var array<string, mixed> Formatting options.
     * Expected keys: 'show-help' (bool), 'show-ref' (bool).
     */
    protected array $options = [];

    /**
     * Constructs a message string from an Issue, optionally appending help and reference info.
     *
     * The format is "Error/Warning/Tip on line x:x - msg (help) [ref]".
     *
     * @param Issue $issue The issue to get the message from.
     * @return string The formatted message string.
     */
    protected function getParsableMessage(Issue $issue): string {
        if (!$this->options['parse-message']) {
            return $issue->getMessage();
        }
        //Error/Warning/Tip on line x:x - msg (help) [ref]
        return ucfirst($issue->getSeverityString()).
            ($issue->getLine() ? (' on line '.$issue->getLine()) : '').
            ($issue->getLine() && $issue->getColumn() ? (':'.$issue->getColumn()) : '').
            ' - '.
            $issue->getMessage().
            ($this->options['show-help'] && $issue->getHelp() ? "  ({$issue->getHelp()})" : '').
            ($this->options['show-ref'] && $issue->getRef() ? "  [{$issue->getRef()}]" : '');
    }

    /**
     * Parses a message string to extract the core message, help text, and reference.
     *
     * It expects the format (inside [] are optional) "ERROR/WARNING/TIP[ on line x:x] - msg[ (help)] [\[ref\]]".
     *
     * @param string $message The message string to parse.
     * @return array{message: string, help: string|null, ref: string|null} An associative array containing the parsed parts.
     */
    protected function parseMessage(string $message): array
    {
        $original =  ['message' => trim($message)];
        if (!$this->options['parse-message']) {
            return $original;
        }
        $parsed = [];
        preg_match( static::$parsable_message_regex, $message, $parsed);
        $parsed = array_map('trim', $parsed);
        return empty($parsed) ? $original : $parsed;
    }


    protected static function getParsableMessageOptions($returnType = self::OPTIONS_NORMAL): array {
       return self::newOption('parse-message', InputOption::VALUE_NEGATABLE, 'try (or don\'t try --no-show-ref) to parse the message for additional field', true, $returnType);
    }
}