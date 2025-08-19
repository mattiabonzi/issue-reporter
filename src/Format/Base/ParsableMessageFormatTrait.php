<?php

namespace Tuchsoft\IssueReporter\Format\Base;


use Tuchsoft\IssueReporter\Issue;

/**
 * Trait to provide common functionality for formats that need to build and parse
 * messages containing optional help and reference information.
 *
 * This is useful for formats that serialize all issue information into a single
 * string and need to deserialize it back into structured data.
 */
trait ParsableMessageFormatTrait {


    private string $PARSABLE_EMACCS_REGEX = "^(?<path>[^:]+):(?<line>\d+):(?<col>\d+):\s(?<severity>[a-z]+)\s-\s(?<message>.+?)\s*(?:\(#(?<code>.+?)\))?";
    private string  $PARSABLE_MSG_REGEX = "(?<message>.+?(?=[\(\[]))";
    private string $PARSABLE_EXTRA_REGEX = "(?:\s*\((?<help>.+)\))?(?:\s*\[(?<ref>.+)\])?$";
    /**
     * @var array<string, mixed> Formatting options.
     * Expected keys: 'show-help' (bool), 'show-ref' (bool).
     */
    protected array $options = [];

    /**
     * Constructs a message string from an Issue, optionally appending help and reference info.
     *
     * The format is "message (help) [ref]".
     *
     * @param Issue $issue The issue to get the message from.
     * @return string The formatted message string.
     */
    protected function getParsableMessage(Issue $issue, bool $emacs = false, ?string $severity = null): string {
        if ($emacs) {
            if (!$severity) {
                $severity = $issue->getSeverityString();
            }
            $message = sprintf(
                "%s:%d:%d: %s - %s (#%s)",
                $issue->getPath(),
                $issue->getLine(),
                $issue->getColumn(),
                $severity,
                $issue->getMessage(),
                $issue->getCode()
            );
        } else {
            $message = $issue->getMessage();
        }

        if ($this->options['show-help'] && $issue->getHelp()) {
            $message .= " ({$issue->getHelp()})";
        }
        if ($this->options['show-ref'] && $issue->getRef()) {
            $message .= " [{$issue->getRef()}]";
        }
        return $message;
    }

    /**
     * Parses a message string to extract the core message, help text, and reference.
     *
     * It expects the format "message (help) [ref]".
     *
     * @param string $message The message string to parse.
     * @return array{message: string, help: string|null, ref: string|null} An associative array containing the parsed parts.
     */
    protected function parseMessage(string $message, bool $emacs = false): array
    {
        $parsed = [];
        preg_match( '/'.($emacs ? $this->PARSABLE_EMACCS_REGEX : $this->PARSABLE_MSG_REGEX)."$this->PARSABLE_EXTRA_REGEX/", $message, $parsed);
        return array_map('trim', $parsed);
    }
}