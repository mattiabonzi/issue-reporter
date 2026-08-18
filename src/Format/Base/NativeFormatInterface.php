<?php

namespace Tuchsoft\IssueReporter\Format\Base;

use Tuchsoft\IssueReporter\Base\LoadableInterface;

/**
 * A marker interface for report formats that are native to this library.
 *
 * This interface is used to identify formats that are custom-defined within this
 * library, as opposed to standard, third-party formats. Native
 * formats are generally expected to support all features of the Report object.
 *
 * Its primary purpose is to help in categorizing and displaying format options,
 * for example, in help messages or list commands. It's important to note that
 * not all native formats are necessarily parsable (i.e., they cannot always be
 * converted back into a Report object from their string representation).
 */
interface NativeFormatInterface extends LoadableInterface
{
    // This is a marker interface and does not define any additional methods.
}