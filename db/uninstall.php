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
 * This script removes plugin-specific user preferences.
 * Moodle automatically handles plugin configuration, scheduled tasks, and tables on uninstall.
 *
 * @package    local_ascend_rewards
 * @copyright 2026 Elantis (Pty) LTD
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
/**
 * Custom uninstall procedure
 *
 * @return bool true on success
 */
function xmldb_local_ascend_rewards_uninstall() {
    global $DB;

    // Delete user preferences created by this plugin (broad sweep).
    // Use a wide LIKE to ensure no traces remain (both legacy and current keys).
    $prefpatterns = ['ascend_%', 'ascendassets_%'];
    foreach ($prefpatterns as $pattern) {
        $like = str_replace('%', '%%', $pattern);
        $DB->delete_records_select('user_preferences', $DB->sql_like('name', '?'), [$like]);
    }

    return true;
}
