<?php
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
 * Post-installation tasks for Ascend Rewards plugin.
 *
 * Tables are created via install.xml - this file handles post-install logic.
 *
 * @package   local_ascend_rewards
 * @copyright 2026 Elantis (Pty) LTD
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// phpcs:disable moodle.Files.LineLength.MaxExceeded,moodle.Files.LineLength.TooLong
// phpcs:disable moodle.Commenting.InlineComment.InvalidEndChar,moodle.Commenting.InlineComment.NotCapital

/**
 * Post-installation tasks for local_ascend_rewards.
 *
 * @return bool True on success
 */
function xmldb_local_ascend_rewards_install() {
    global $DB;

    $dbman = $DB->get_manager();

    if (!$dbman->table_exists('user_preferences') ||
            !$dbman->table_exists('local_ascend_rewards_level_tokens')) {
        return true;
    }

    // Backfill level tokens from stored user level preference on fresh install.
    $now = time();
    try {
        $levelprefs = $DB->get_records('user_preferences', ['name' => 'ascend_current_level'], '', 'id, userid, value');
    } catch (dml_exception $e) {
        // If a replica/read failure occurs during install, skip backfill to avoid blocking install.
        return true;
    }

    foreach ($levelprefs as $pref) {
        // Skip if value is not numeric or is zero.
        if (!is_numeric($pref->value) || (int)$pref->value <= 0) {
            continue;
        }
        $tokens = (int)$pref->value;
        $existing = $DB->get_record('local_ascend_rewards_level_tokens', ['userid' => $pref->userid]);

        if ($existing) {
            if ((int)$existing->tokens_available < $tokens) {
                $existing->tokens_available = $tokens;
                $existing->timemodified = $now;
                $DB->update_record('local_ascend_rewards_level_tokens', $existing);
            }
        } else {
            $DB->insert_record('local_ascend_rewards_level_tokens', (object) [
                'userid' => $pref->userid,
                'tokens_available' => $tokens,
                'tokens_used' => 0,
                'timemodified' => $now,
            ]);
        }
    }

    return true;
}
