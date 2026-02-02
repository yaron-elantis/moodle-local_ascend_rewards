<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Uninstall script for local_ascend_rewards
 *
 * This script completely removes all plugin data including:
 * - Database tables (local_ascend_rewards_coins, local_ascend_rewards_gameboard,
 *   local_ascend_rewards_badge_cache, local_ascend_rewards_badges, local_ascend_rewards_badgerlog)
 * - User preferences (all apex_* and ascendassets_* preferences)
 * - Scheduled tasks (award_badges, rebuild_badge_cache)
 * - Any cached data
 *
 * @package    local_ascend_rewards
 * @copyright 2026 Elantis (Pty) LTD
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// phpcs:disable moodle.Files.MoodleInternal.MoodleInternalNotNeeded
defined('MOODLE_INTERNAL') || die();
// Preserve legacy inline comments and naming in the uninstall routine.
// phpcs:disable moodle.Commenting.InlineComment.InvalidEndChar,moodle.Commenting.InlineComment.NotCapital
// phpcs:disable moodle.NamingConventions.ValidVariableName.VariableNameUnderscore

/**
 * Custom uninstall procedure
 *
 * @return bool true on success
 */
function xmldb_local_ascend_rewards_uninstall() {
    global $DB;

    try {
        // 1. Delete all user preferences created by this plugin (broad sweep)
        // Use a wide LIKE to ensure no traces remain (both legacy and current keys).
        $prefpatterns = [ 'ascend_%', 'ascendassets_%' ];
        $prefdeleted = 0;
        foreach ($prefpatterns as $pattern) {
            $like = str_replace('%', '%%', $pattern);
            $count = $DB->count_records_select('user_preferences', $DB->sql_like('name', '?'), [$like]);
            if ($count > 0) {
                $DB->delete_records_select('user_preferences', $DB->sql_like('name', '?'), [$like]);
                $prefdeleted += $count;
                mtrace("  - Removed $count user preferences matching: $pattern");
            }
        }
        if ($prefdeleted > 0) {
            mtrace("  - Total user preferences removed: $prefdeleted");
        }

        // 2. Remove all plugin config records
            // 2. Remove all plugin config records (and config change history)
            $configdeleted = $DB->delete_records('config_plugins', ['plugin' => 'local_ascend_rewards']);
        if ($configdeleted) {
            mtrace("  - Removed $configdeleted config records for local_ascend_rewards");
        }
            $configlogdeleted = $DB->delete_records('config_log', ['plugin' => 'local_ascend_rewards']);
        if ($configlogdeleted) {
            mtrace("  - Removed $configlogdeleted config_log entries for local_ascend_rewards");
        }

        // 3. Drop custom tables (if they still exist)
        // Note: Moodle automatically drops tables defined in install.xml,
        // but we check explicitly to ensure clean uninstall

        $dbman = $DB->get_manager();

        // Drop all plugin tables in reverse dependency order
        $tables_to_drop = [
            'local_ascend_rewards_xp', // XP tracking (separate from coins)
            'local_ascend_rewards_level_tokens', // Level-up unlock tokens
            'local_ascend_rewards_avatar_unlocks', // Avatar and pet unlock tracking
            'local_ascend_rewards_mysterybox', // Mystery box openings and rewards
            'local_ascend_rewards_badgerlog', // Badge awarding audit log
            'local_ascend_rewards_badge_cache', // Badge activity cache
            'local_ascend_rewards_gameboard', // Gameboard picks and coins
            'local_ascend_rewards_coins', // Main coin ledger
            'local_ascend_rewards_badges', // Badge configuration
        ];

        foreach ($tables_to_drop as $table_name) {
            $table = new xmldb_table($table_name);
            if ($dbman->table_exists($table)) {
                $dbman->drop_table($table);
                mtrace("  - Dropped table: $table_name");
            }
        }

        // 4. Clear any cached data
        // Note: Skip cache definition purging as definitions may not exist during uninstall
        // Just purge all caches to ensure no stale data remains
        try {
            purge_all_caches();
            mtrace("  - Caches purged");
        } catch (Exception $cache_error) {
            mtrace("  - Warning: Could not purge caches: " . $cache_error->getMessage());
        }

        // 5. Clean up any orphaned records in other tables
        // Remove any scheduled tasks
        $DB->delete_records('task_scheduled', ['classname' => '\local_ascend_rewards\task\award_badges']);
        $DB->delete_records('task_scheduled', ['classname' => '\local_ascend_rewards\task\rebuild_badge_cache']);
        // Remove any adhoc tasks queued for this plugin
        $DB->delete_records_select('task_adhoc', $DB->sql_like('classname', '?'), ['%local_ascend_rewards%']);
           // Remove log entries generated by this component
           $DB->delete_records('logstore_standard_log', ['component' => 'local_ascend_rewards']);
           // Remove notifications/messages tied to this component to avoid stragglers
           $DB->delete_records('notifications', ['component' => 'local_ascend_rewards']);
           $DB->delete_records('message', ['component' => 'local_ascend_rewards']);
           $DB->delete_records('message_read', ['component' => 'local_ascend_rewards']);

            // 6. Log successful uninstall
        mtrace('local_ascend_rewards: All plugin data successfully removed');
        mtrace('  - User preferences cleaned');
        mtrace('  - Database tables dropped');
        mtrace('  - Scheduled tasks removed');
        mtrace('  - Caches purged');

        return true;
    } catch (Exception $e) {
        // Log error but don't fail uninstall
        mtrace('local_ascend_rewards: Error during uninstall: ' . $e->getMessage());
        mtrace('  Note: Some data may not have been cleaned up completely');

        // Return true anyway to allow uninstall to complete
        return true;
    }
}
