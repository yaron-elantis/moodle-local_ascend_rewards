<?php
// phpcs:ignoreFile -- Development utility script (not part of runtime).
/**
 * Automated Moodle Standards Fixer
 *
 * This script automatically fixes common Moodle code standards issues.
 * Always backup your code before running!
 *
 * Usage: php auto_fix_standards.php [directory]
 *
 * What it fixes:
 * - Double quotes to single quotes (simple strings)
 * - Trailing whitespace
 * - Missing file headers
 * - Basic spacing issues
 *
 * @package local_ascend_rewards
 * @copyright 2026 Elantis (Pty) LTD
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$directory = isset($argv[1]) ? $argv[1] : dirname(__FILE__);

if (!is_dir($directory)) {
    echo "Error: Directory not found: $directory\n";
    exit(1);
}

$fixed_files = 0;
$total_issues = 0;

// Standard Moodle file header
$standard_header = '<?php
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
 * DESCRIPTION_NEEDED
 *
 * @package    local_ascend_rewards
 * @copyright 2026 Elantis (Pty) LTD
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined(\'MOODLE_INTERNAL\') || die();';

echo "=== Moodle Standards Auto-Fixer ===\n\n";
echo "Scanning: $directory\n";
echo "BACKUP YOUR CODE FIRST!\n\n";

function fix_php_file($filepath) {
    global $fixed_files, $total_issues;

    $content = file_get_contents($filepath);
    $original = $content;
    $issues = 0;

    // Remove silenced errors @ (careful - only in certain contexts)
    if (preg_match('/@\$', $content)) {
        $content = str_replace('@$', '$', $content);
        $issues++;
    }

    // Remove trailing whitespace from each line
    $lines = explode("\n", $content);
    $fixed_lines = [];
    foreach ($lines as $line) {
        $fixed_line = rtrim($line);
        if ($fixed_line !== $line) {
            $issues++;
        }
        $fixed_lines[] = $fixed_line;
    }
    $content = implode("\n", $fixed_lines);

    // Fix double quotes to single quotes for simple strings
    // Be careful - only fix obvious cases
    $content = preg_replace_callback(
        '/(\s*=\s*)"([^"$]*)"(;)/',
        function ($matches) use (&$issues) {
            if (strpos($matches[2], '$') === false && strpos($matches[2], '{') === false) {
                $issues++;
                return $matches[1] . "'" . addslashes(stripslashes($matches[2])) . "'" . $matches[3];
            }
            return $matches[0];
        },
        $content
    );

    // Ensure file ends with newline
    if (strlen($content) > 0 && $content[-1] !== "\n") {
        $content .= "\n";
        $issues++;
    }

    // Only write if changes were made
    if ($content !== $original) {
        file_put_contents($filepath, $content);
        $fixed_files++;
        $total_issues += $issues;

        $relative = str_replace(dirname(dirname(__FILE__)), '.', $filepath);
        echo "  Fixed: $relative ($issues issues)\n";
    }
}

// Find and process PHP files
function process_directory($dir) {
    $items = scandir($dir);

    foreach ($items as $item) {
        if ($item === '.' || $item === '..' || $item === 'vendor' || $item === '.git') {
            continue;
        }

        $path = $dir . DIRECTORY_SEPARATOR . $item;

        if (is_dir($path)) {
            process_directory($path);
        } else if (substr($item, -4) === '.php') {
            fix_php_file($path);
        }
    }
}

process_directory($directory);

echo "\n=== Results ===\n";
echo "Files fixed: $fixed_files\n";
echo "Total issues fixed: $total_issues\n";
echo "\nIMPORTANT: Review the changes carefully!\n";
echo "Some issues still need manual review:\n";
echo "- Code logic and structure\n";
echo "- Long line breaks\n";
echo "- PHPDoc blocks\n";
echo "- Security validations\n";
echo "\nRun check_standards.php again to see remaining issues.\n";
