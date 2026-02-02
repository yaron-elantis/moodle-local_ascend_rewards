<?php
// phpcs:ignoreFile -- Development utility script (not part of runtime).
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Simple Moodle Code Standards Checker
 *
 * This script checks PHP files for common Moodle standards violations.
 * Run from your plugin directory: php check_standards.php
 *
 * @package local_ascend_rewards
 * @copyright 2026 Elantis (Pty) LTD
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Directory to scan (current plugin directory)
$plugindir = dirname(__FILE__);
$errors = [];
$warnings = [];
$files_checked = 0;

// Recursively get all PHP files
function get_php_files($dir) {
    $files = [];
    $items = scandir($dir);

    foreach ($items as $item) {
        if ($item === '.' || $item === '..' || $item === 'vendor') {
            continue;
        }

        $path = $dir . DIRECTORY_SEPARATOR . $item;

        if (is_dir($path)) {
            $files = array_merge($files, get_php_files($path));
        } else if (substr($item, -4) === '.php') {
            $files[] = $path;
        }
    }

    return $files;
}

// Check for common issues
function check_file($filepath) {
    global $errors, $warnings, $files_checked;
    $files_checked++;

    $content = file_get_contents($filepath);
    $lines = explode("\n", $content);
    $linenum = 0;

    $relative = str_replace(dirname(__FILE__), '.', $filepath);

    foreach ($lines as $line) {
        $linenum++;

        // Check for bare die() or exit()
        if (preg_match('/^\s*(die|exit)\s*\(/', $line) && !preg_match('/moodle_exception|throw/', $line)) {
            $errors[] = "$relative:$linenum - Bare die()/exit() found - use throw new moodle_exception()";
        }

        // Check for silenced errors @
        if (strpos($line, '@') !== false && !preg_match('/@package|@copyright|@license/', $line)) {
            $warnings[] = "$relative:$linenum - Silenced error operator (@) found - should be avoided";
        }

        // Check for double quotes in simple strings
        if (preg_match('/=\s*"[^${}]*"/', $line) && !preg_match('/\\\\/', $line)) {
            $warnings[] = "$relative:$linenum - Use single quotes for simple strings";
        }

        // Check for missing space before {
        if (preg_match('/function\s+\w+\(.*\){/', $line)) {
            $warnings[] = "$relative:$linenum - Missing space before opening brace in function";
        }

        // Check for tabs
        if (strpos($line, "\t") !== false) {
            $errors[] = "$relative:$linenum - Tab character found - use 4 spaces";
        }

        // Check for trailing whitespace
        if (strlen($line) > 0 && substr($line, -1) === ' ') {
            $warnings[] = "$relative:$linenum - Trailing whitespace";
        }

        // Check for long lines (>120 chars)
        if (strlen($line) > 120 && strlen($line) < 200) {
            $warnings[] = "$relative:$linenum - Line is " . strlen($line) . " chars (soft limit 120)";
        } else if (strlen($line) > 200) {
            $errors[] = "$relative:$linenum - Line is " . strlen($line) . " chars (hard limit 200)";
        }

        // Check for mysql functions (deprecated)
        if (preg_match('/mysql_|mysqli_/', $line) && !preg_match('/\/\/', $line)) {
            $errors[] = "$relative:$linenum - Deprecated MySQL function found - use \$DB->get_*";
        }

        // Check for global keyword syntax
        if (preg_match('/function\s+\w+\([^)]*\s+global/', $line)) {
            $errors[] = "$relative:$linenum - 'global' keyword shouldn't be in function parameters";
        }
    }

    // Check file headers
    if (!preg_match('/This file is part of Moodle/', $content)) {
        $warnings[] = "$relative - Missing standard Moodle file header";
    }

    if (strpos($filepath, 'version.php') === false && !preg_match('/defined\([\'"]MOODLE_INTERNAL/', $content)) {
        $warnings[] = "$relative - Missing MOODLE_INTERNAL check";
    }
}

// Main execution
echo "=== Moodle Code Standards Checker ===\n\n";
echo "Scanning files in: $plugindir\n\n";

$php_files = get_php_files($plugindir);

foreach ($php_files as $file) {
    check_file($file);
}

echo "Checked $files_checked PHP files\n\n";

if (count($errors) > 0) {
    echo "ERRORS (" . count($errors) . "):\n";
    echo str_repeat("=", 60) . "\n";
    foreach ($errors as $error) {
        echo "  $error\n";
    }
    echo "\n";
}

if (count($warnings) > 0) {
    echo "WARNINGS (" . count($warnings) . "):\n";
    echo str_repeat("=", 60) . "\n";
    foreach ($warnings as $warning) {
        echo "  $warning\n";
    }
    echo "\n";
}

if (count($errors) === 0 && count($warnings) === 0) {
    echo "No issues found!\n";
} else {
    echo "\nTotal issues: " . (count($errors) + count($warnings)) . "\n";
    echo "Critical errors: " . count($errors) . "\n";
}
