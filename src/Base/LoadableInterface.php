<?php

namespace Tuchsoft\IssueReporter\Base;
use Symfony\Component\Console\Input\InputOption;


/**
 * Represents a loadable object, such as a Format or a Transformer.
 *
 * Loadable objects are components that can be dynamically loaded and configured
 * within the application. They define their own set of configurable options
 * using Symfony's `InputOption` component.
 *
 * An array of user-defined properties can be passed to the constructor, and
 * default values are set for all properties. Options can also be changed on an
 * instance using `setOptions()`.
 */
interface LoadableInterface {

    /**
     * @var int Constant to get options without any prefix.
     */
    const OPTIONS_NORMAL = 1;
    /**
     * @var int Constant to get options prefixed for use in commands.
     */
    const OPTIONS_PREFIX = 2;
    /**
     * @var int Constant to get both prefixed and non-prefixed options.
     */
    const OPTIONS_BOTH = 3;

    /**
     * Gets the unique, machine-readable name for the loadable object.
     *
     * This name is used to identify and load the object (e.g., 'checkstyle', 'json').
     *
     * @return string The unique name.
     */
    static function getName(): string;

    /**
     * Gets a short, human-readable description of the loadable object.
     *
     * This description is typically used in help messages or listings.
     *
     * @return string The description.
     */
    static function getDesc(): string;

    /**
     * Gets the definition of the configurable options for this object.
     *
     * The options are defined as an array of Symfony `InputOption` objects.
     * The `$returnType` parameter controls how the options are returned, which is useful
     * for integrating with different parts of an application (e.g., command-line interfaces).
     *
     * @param int $returnType Controls the format of the returned options.
     *                        Use one of the OPTIONS_* constants.
     * @return InputOption[] An array of option definitions.
     */
    static function getOptionsDefinition(int $returnType = self::OPTIONS_NORMAL): array;

    /**
     * Sets the configuration options for the object instance.
     *
     * This method applies the given options, overriding any default values.
     * Only options defined in `getOptionsDefinition()` will be processed.
     *
     * @param array<string, mixed> $options An associative array of option names and their values.
     * @return void
     */
    function setOptions(array $options);

}