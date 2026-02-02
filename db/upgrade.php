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
 * Upgrade tasks for Ascend Rewards plugin.
 *
 * Handles database schema changes and data migrations.
 *
 * @package   local_ascend_rewards
 * @copyright 2026 Elantis (Pty) LTD
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// phpcs:disable moodle.Files.MoodleInternal.MoodleInternalNotNeeded
defined('MOODLE_INTERNAL') || die();
// Preserve legacy upgrade comments and naming.
// phpcs:disable moodle.Commenting.InlineComment.InvalidEndChar,moodle.Commenting.InlineComment.NotCapital
// phpcs:disable moodle.NamingConventions.ValidVariableName.VariableNameUnderscore
// phpcs:disable moodle.Files.LineLength.MaxExceeded,moodle.Files.LineLength.TooLong
// phpcs:disable moodle.Commenting.MissingDocblock.Function,moodle.Commenting.MissingDocblock.Constant

/**
 * Execute upgrade steps for local_ascend_rewards.
 *
 * @param int $oldversion The old version of the plugin
 * @return bool True on success
 */
function xmldb_local_ascend_rewards_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2025102202) {
        // Create table programmatically (avoid XML parser issues entirely).
        $table = new xmldb_table('local_ascend_rewards_badges');

        if (!$dbman->table_exists($table)) {
            // Fields.
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('code', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL, null, '');
            $table->add_field('badgeid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('enabled', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '1');
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

            // Keys.
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('uniq_code_scope', XMLDB_KEY_UNIQUE, ['code', 'courseid']);

            // Indexes.
            $table->add_index('badgeid_idx', XMLDB_INDEX_NOTUNIQUE, ['badgeid']);

            // Create the table.
            $dbman->create_table($table);
        }

        // Seed issuer defaults.
        if (!get_config('local_ascend_rewards', 'issuername')) {
            set_config('issuername', 'Apex Rewards', 'local_ascend_rewards');
        }
        if (!get_config('local_ascend_rewards', 'issuercontact')) {
            set_config('issuercontact', 'noreply@example.com', 'local_ascend_rewards');
        }

        // Seed coin values for known codes.
        $defs = \local_ascend_rewards\badges::definitions();
        foreach ($defs as $code => $def) {
            $key = 'coins_' . $code;
            if (get_config('local_ascend_rewards', $key) === false) {
                set_config($key, (int)($def['defaultcoins'] ?? 0), 'local_ascend_rewards');
            }
        }

        upgrade_plugin_savepoint(true, 2025102202, 'local', 'ascend_rewards');
    }

    // Ensure `courseid` exists on local_ascend_rewards_coins and backfill from
    // core badge issuance records when possible. This addresses sites that
    // previously created the coins table without course scoping.
    if ($oldversion < 2025111500) {
        $coins = new xmldb_table('local_ascend_rewards_coins');
        // Add courseid field if missing.
        if (!$dbman->field_exists($coins, 'courseid')) {
            $field = new xmldb_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $dbman->add_field($coins, $field);
            // Add index on courseid for queries.
            $dbman->add_index($coins, new xmldb_index('courseid_idx', XMLDB_INDEX_NOTUNIQUE, ['courseid']));
        }

        // Attempt to backfill any missing coin rows from core badge issuance data.
        // This will insert one coin record per badge issuance if it does not already exist.
        try {
            // Only proceed if the core badge_issued table exists.
            $badgeissuedtable = new xmldb_table('badge_issued');
            if ($dbman->table_exists($badgeissuedtable)) {
                // Determine available time column on badge_issued.
                $cols = $DB->get_columns('badge_issued');
                $timecol = null;
                foreach (['timeissued', 'timecreated', 'dateissued', 'issued'] as $c) {
                    if (isset($cols[$c])) {
                        $timecol = $c;
                        break;
                    }
                }

                // Fetch issued badges joined to badge to obtain courseid.
                $timecolsql = $timecol ? 'bi.' . $timecol : '0 AS timeissued';
                $sql = "SELECT bi.badgeid, bi.userid, b.courseid, {$timecolsql} as timeissued
                          FROM {badge_issued} bi
                          JOIN {badge} b ON b.id = bi.badgeid";
                $issued = $DB->get_records_sql($sql);
                foreach ($issued as $rec) {
                    $exists = $DB->record_exists('local_ascend_rewards_coins', [
                        'userid' => $rec->userid,
                        'badgeid' => $rec->badgeid,
                        'courseid' => $rec->courseid,
                    ]);
                    if ($exists) {
                        continue;
                    }

                    // Compute coins from mapping if available, otherwise 0.
                    $coinsval = 0;
                    if (class_exists('\local_ascend_rewards\coin_map')) {
                        $coinsval = (int)\local_ascend_rewards\coin_map::coins_for_badge((int)$rec->badgeid);
                    }

                    $timecreated = (!empty($rec->timeissued) && is_numeric($rec->timeissued)) ? (int)$rec->timeissued : time();

                    $DB->insert_record('local_ascend_rewards_coins', (object)[
                        'userid' => $rec->userid,
                        'badgeid' => $rec->badgeid,
                        'coins' => $coinsval,
                        'courseid' => $rec->courseid,
                        'timecreated' => $timecreated,
                    ]);
                }
            }
        } catch (Exception $e) {
            // If anything goes wrong during backfill, continue without failing
            // the upgrade; admins can run a manual backfill later.
            mtrace('local_ascend_rewards: backfill skipped: ' . $e->getMessage());
        }

        upgrade_plugin_savepoint(true, 2025111500, 'local', 'ascend_rewards');
    }

    if ($oldversion < 2025112200) {
        // Create gameboard coins table
        $table = new xmldb_table('local_ascend_rewards_gameboard');

        if (!$dbman->table_exists($table)) {
            // Fields
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('badgeid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('week', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, '');
            $table->add_field('position', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('coins', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

            // Keys
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);

            // Indexes
            $table->add_index('userid_week', XMLDB_INDEX_NOTUNIQUE, ['userid', 'week']);
            $table->add_index('userid_badge', XMLDB_INDEX_NOTUNIQUE, ['userid', 'badgeid', 'week']);

            // Create table
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2025112200, 'local', 'ascend_rewards');
    }

    if ($oldversion < 2025120100) {
        // Create badge activity cache table for fast modal loading
        $table = new xmldb_table('local_ascend_rewards_badge_cache');

        if (!$dbman->table_exists($table)) {
            // Fields
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('badgeid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('activities', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
            $table->add_field('metadata', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

            // Keys
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

            // Indexes (avoid naming conflicts by not using foreign keys)
            $table->add_index('userid_courseid_badgeid', XMLDB_INDEX_UNIQUE, ['userid', 'courseid', 'badgeid']);
            $table->add_index('idx_userid', XMLDB_INDEX_NOTUNIQUE, ['userid']);
            $table->add_index('idx_courseid', XMLDB_INDEX_NOTUNIQUE, ['courseid']);
            $table->add_index('idx_badgeid', XMLDB_INDEX_NOTUNIQUE, ['badgeid']);

            // Create table
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2025120100, 'local', 'ascend_rewards');
    }

    if ($oldversion < 2025120300) {
        // Add villain_id column to avatar_unlocks table and update index
        $table = new xmldb_table('local_ascend_rewards_avatar_unlocks');

        if ($dbman->table_exists($table)) {
            // Add villain_id field if missing
            $field = new xmldb_field('villain_id', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'pet_id');
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }

            // Drop dependent index before altering avatar_level precision to avoid dependency errors
            $levelindex = new xmldb_index('user_level_idx', XMLDB_INDEX_NOTUNIQUE, ['userid', 'avatar_level']);
            if ($dbman->index_exists($table, $levelindex)) {
                $dbman->drop_index($table, $levelindex);
            }

            // Update avatar_level precision (still small int, but re-assert definition)
            $levelfield = new xmldb_field('avatar_level', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, null);
            $dbman->change_field_precision($table, $levelfield);

            // Drop old unique index
            $oldindex = new xmldb_index('user_avatar_idx', XMLDB_INDEX_UNIQUE, ['userid', 'avatar_name', 'pet_id']);
            if ($dbman->index_exists($table, $oldindex)) {
                $dbman->drop_index($table, $oldindex);
            }

            // Add new unique index including villain_id
            $newindex = new xmldb_index('user_avatar_pet_villain_idx', XMLDB_INDEX_UNIQUE, ['userid', 'avatar_name', 'pet_id', 'villain_id']);
            if (!$dbman->index_exists($table, $newindex)) {
                $dbman->add_index($table, $newindex);
            }

            // Recreate level index after precision change
            if (!$dbman->index_exists($table, $levelindex)) {
                $dbman->add_index($table, $levelindex);
            }
        }

        upgrade_plugin_savepoint(true, 2025120300, 'local', 'ascend_rewards');
    }

    if ($oldversion < 2025120700) {
        // Add XP field to local_ascend_rewards_coins table
        $table = new xmldb_table('local_ascend_rewards_coins');
        $field = new xmldb_field('xp', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'coins');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Backfill XP for existing records (XP = coins / 2)
        $DB->execute("UPDATE {local_ascend_rewards_coins} SET xp = FLOOR(coins / 2) WHERE badgeid > 0 AND xp = 0");

        upgrade_plugin_savepoint(true, 2025120700, 'local', 'ascend_rewards');
    }

    if ($oldversion < 2025120800) {
        // Create new local_ascend_rewards_xp table for SEPARATE XP tracking
        // XP is PERMANENT and never decreases. Used for all rankings.
        // Coins can be spent. Rankings NEVER use coins.
        $table = new xmldb_table('local_ascend_rewards_xp');

        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('xp', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);

            $table->add_index('userid_courseid', XMLDB_INDEX_UNIQUE, ['userid', 'courseid']);
            $table->add_index('xp_idx', XMLDB_INDEX_NOTUNIQUE, ['xp']);

            $dbman->create_table($table);
        }

        // Migrate XP data from coins table to new XP table
        // Course-specific XP
        $course_xp = $DB->get_records_sql(
            "SELECT userid, courseid, FLOOR(SUM(coins) / 2) as xp
             FROM {local_ascend_rewards_coins}
             WHERE badgeid > 0 AND coins > 0
             GROUP BY userid, courseid"
        );

        foreach ($course_xp as $record) {
            if (!$DB->record_exists('local_ascend_rewards_xp', ['userid' => $record->userid, 'courseid' => $record->courseid])) {
                $DB->insert_record('local_ascend_rewards_xp', (object)[
                    'userid' => $record->userid,
                    'courseid' => $record->courseid,
                    'xp' => $record->xp,
                    'timemodified' => time(),
                ]);
            }
        }

        // Site-wide XP (courseid = 0)
        $site_xp = $DB->get_records_sql(
            "SELECT userid, FLOOR(SUM(coins) / 2) as xp
             FROM {local_ascend_rewards_coins}
             WHERE badgeid > 0 AND coins > 0
             GROUP BY userid"
        );

        foreach ($site_xp as $record) {
            if (!$DB->record_exists('local_ascend_rewards_xp', ['userid' => $record->userid, 'courseid' => 0])) {
                $DB->insert_record('local_ascend_rewards_xp', (object)[
                    'userid' => $record->userid,
                    'courseid' => 0,
                    'xp' => $record->xp,
                    'timemodified' => time(),
                ]);
            }
        }

        upgrade_plugin_savepoint(true, 2025120800, 'local', 'ascend_rewards');
    }

    if ($oldversion < 2025121000) {
        // Version 2025-12-10: Fix installation error when tables already exist
        // If plugin was not properly uninstalled before reinstall, tables remain.
        // This version gracefully handles that scenario.
        // No action needed - Moodle's database manager automatically checks for existing tables
        // before creating them, so the install.xml will succeed even if tables exist.

        upgrade_plugin_savepoint(true, 2025121000, 'local', 'ascend_rewards');
    }

    if ($oldversion < 2025121001) {
        // Backfill level tokens from stored user level preference (apex_current_level)
        // in case a reinstall leaves preferences but no token records.
        $now = time();
        $levelprefs = $DB->get_records_select('user_preferences', "name = :name AND CAST(value AS SIGNED) > 0", ['name' => 'ascend_current_level']);

        foreach ($levelprefs as $pref) {
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

        upgrade_plugin_savepoint(true, 2025121001, 'local', 'ascend_rewards');
    }

    return true;
}
