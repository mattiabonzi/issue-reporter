<?php

namespace Tuchsoft\IssueReporter\Utils;
class Path extends \Riimu\Kit\PathJoin\Path {
    /**
     * Removes a base path from the front of a file path.
     *
     * @param string $path     The path of the file.
     * @param string $basepath The base path to remove. This should not end
     *                         with a directory separator.
     *
     * @return string
     */
    public static function stripBasepath($path, $basepath): string
    {
        // ... (this method is fine, no changes needed)
        if (empty($basepath) === true) {
            return $path;
        }

        $basepathLen = strlen($basepath);
        if (substr($path, 0, $basepathLen) === $basepath) {
            $path = substr($path, $basepathLen);
        }

        $path = ltrim($path, DIRECTORY_SEPARATOR);
        if ($path === '') {
            $path = '.';
        }

        return $path;

    }


    public static function findCommonBasePath(array $paths): string {
        if (empty($paths)) {
            return '';
        }


        // Normalize all paths
        $normalizedPaths = array_map(function($p) {
            if (preg_match("/.+?\.[^\/]+?$/", $p)) {
                $p = dirname($p);
            }
            return Path::normalize(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $p));
        }, $paths);

        $first_path_parts = explode(DIRECTORY_SEPARATOR, $normalizedPaths[0]);
        $common_parts = [];

        foreach ($first_path_parts as $i => $part) {
            $is_common = true;
            foreach ($normalizedPaths as $path) {
                $current_path_parts = explode(DIRECTORY_SEPARATOR, $path);
                if (!isset($current_path_parts[$i]) || $current_path_parts[$i] !== $part) {
                    $is_common = false;
                    break;
                }
            }
            if ($is_common) {
                $common_parts[] = $part;
            } else {
                break; // Stop at the first non-common part
            }
        }

        $result = implode(DIRECTORY_SEPARATOR, $common_parts);

        // BUGFIX: If the only common part of absolute paths is the root, implode returns an empty string.
        // This corrects it to return the directory separator.
        if (count($common_parts) === 1 && $common_parts[0] === '' && str_starts_with($normalizedPaths[0], DIRECTORY_SEPARATOR)) {
            return DIRECTORY_SEPARATOR;
        }

        if (!str_ends_with($result, DIRECTORY_SEPARATOR)) {
            $result .= DIRECTORY_SEPARATOR;
        }

        if (!str_starts_with($result, DIRECTORY_SEPARATOR)
        && !preg_match("/^[A-Z]:\/.+$/", $result)) {
            $result = '.'.DIRECTORY_SEPARATOR.$result;
        }

        return $result;
    }
}