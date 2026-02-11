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
 * Privacy provider for Ascend Rewards.
 *
 * @package   local_ascend_rewards
 * @copyright 2026 Elantis (Pty) LTD
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_ascend_rewards\privacy;

use context;
use context_course;
use context_system;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\core_userlist_provider;
use core_privacy\local\request\plugin\provider as plugin_provider;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

// phpcs:disable moodle.Files.MoodleInternal.MoodleInternalNotNeeded
defined('MOODLE_INTERNAL') || die();

/**
 * Privacy provider implementation.
 */
class provider implements \core_privacy\local\metadata\provider, core_userlist_provider, plugin_provider {
    /**
     * Describe the stored user data.
     *
     * @param collection $items The collection to add items to.
     * @return collection
     */
    public static function get_metadata(collection $items): collection {
        $items->add_database_table('local_ascend_rewards_coins', [
            'userid' => 'privacy:metadata:userid',
            'badgeid' => 'privacy:metadata:badgeid',
            'coins' => 'privacy:metadata:coins',
            'courseid' => 'privacy:metadata:courseid',
            'timecreated' => 'privacy:metadata:timecreated',
        ], 'privacy:metadata:local_ascend_rewards_coins');

        $items->add_database_table('local_ascend_rewards_badgerlog', [
            'userid' => 'privacy:metadata:userid',
            'badgeid' => 'privacy:metadata:badgeid',
            'status' => 'privacy:metadata:status',
            'message' => 'privacy:metadata:message',
            'timecreated' => 'privacy:metadata:timecreated',
        ], 'privacy:metadata:local_ascend_rewards_badgerlog');

        $items->add_database_table('local_ascend_rewards_gameboard', [
            'userid' => 'privacy:metadata:userid',
            'badgeid' => 'privacy:metadata:badgeid',
            'courseid' => 'privacy:metadata:courseid',
            'timecreated' => 'privacy:metadata:timecreated',
        ], 'privacy:metadata:local_ascend_rewards_coins');

        $items->add_database_table('local_ascend_rewards_badge_cache', [
            'userid' => 'privacy:metadata:userid',
            'courseid' => 'privacy:metadata:courseid',
            'badgeid' => 'privacy:metadata:badgeid',
            'timecreated' => 'privacy:metadata:timecreated',
        ], 'privacy:metadata:local_ascend_rewards_coins');

        $items->add_database_table('local_ascend_rewards_mysterybox', [
            'userid' => 'privacy:metadata:userid',
            'timecreated' => 'privacy:metadata:timecreated',
        ], 'privacy:metadata:local_ascend_rewards_coins');

        $items->add_database_table('local_ascend_rewards_avatar_unlocks', [
            'userid' => 'privacy:metadata:userid',
            'timecreated' => 'privacy:metadata:timecreated',
        ], 'privacy:metadata:local_ascend_rewards_coins');

        $items->add_database_table('local_ascend_rewards_level_tokens', [
            'userid' => 'privacy:metadata:userid',
            'timecreated' => 'privacy:metadata:timecreated',
        ], 'privacy:metadata:local_ascend_rewards_coins');

        $items->add_database_table('local_ascend_rewards_xp', [
            'userid' => 'privacy:metadata:userid',
            'courseid' => 'privacy:metadata:courseid',
            'timecreated' => 'privacy:metadata:timecreated',
        ], 'privacy:metadata:local_ascend_rewards_coins');

        return $items;
    }

    /**
     * Get the list of contexts containing user data.
     *
     * @param int $userid The user to search for.
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;

        $contextlist = new contextlist();
        $params = ['userid' => $userid, 'courselevel' => CONTEXT_COURSE];

        // Add course contexts where course-scoped data exists.
        $sql = "SELECT DISTINCT ctx.id
                  FROM {context} ctx
                  JOIN (
                        SELECT courseid
                          FROM {local_ascend_rewards_coins}
                         WHERE userid = :userid
                           AND courseid IS NOT NULL
                           AND courseid > 0
                        UNION
                        SELECT courseid
                          FROM {local_ascend_rewards_gameboard}
                         WHERE userid = :userid
                           AND courseid > 0
                        UNION
                        SELECT courseid
                          FROM {local_ascend_rewards_badge_cache}
                         WHERE userid = :userid
                           AND courseid > 0
                        UNION
                        SELECT courseid
                          FROM {local_ascend_rewards_xp}
                         WHERE userid = :userid
                           AND courseid > 0
                  ) c
                    ON c.courseid = ctx.instanceid
                 WHERE ctx.contextlevel = :courselevel";
        $contextlist->add_from_sql($sql, $params);

        // Add the system context when site-scoped data exists.
        if (self::user_has_system_data($userid)) {
            $contextlist->add_context(context_system::instance());
        }

        return $contextlist;
    }

    /**
     * Export user data for the approved contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts.
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        if (!$contextlist->count()) {
            return;
        }

        $userid = (int)$contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel === CONTEXT_SYSTEM) {
                $data = (object)[
                    'coins' => array_values($DB->get_records_select(
                        'local_ascend_rewards_coins',
                        'userid = :userid AND (courseid IS NULL OR courseid = 0)',
                        ['userid' => $userid],
                        'timecreated ASC',
                    )),
                    'gameboard' => array_values($DB->get_records_select(
                        'local_ascend_rewards_gameboard',
                        'userid = :userid AND courseid = 0',
                        ['userid' => $userid],
                        'timecreated ASC',
                    )),
                    'badgerlog' => array_values($DB->get_records(
                        'local_ascend_rewards_badgerlog',
                        ['userid' => $userid],
                        'timecreated ASC',
                    )),
                    'mysterybox' => array_values($DB->get_records(
                        'local_ascend_rewards_mysterybox',
                        ['userid' => $userid],
                        'timecreated ASC',
                    )),
                    'avatarunlock' => array_values($DB->get_records(
                        'local_ascend_rewards_avatar_unlocks',
                        ['userid' => $userid],
                        'timecreated ASC',
                    )),
                    'leveltokens' => array_values($DB->get_records(
                        'local_ascend_rewards_level_tokens',
                        ['userid' => $userid],
                        'timecreated ASC',
                    )),
                    'xp' => array_values($DB->get_records(
                        'local_ascend_rewards_xp',
                        ['userid' => $userid, 'courseid' => 0],
                        'timemodified ASC',
                    )),
                ];

                writer::with_context($context)->export_data(['ascend_rewards', 'site'], $data);
                continue;
            }

            if ($context->contextlevel !== CONTEXT_COURSE) {
                continue;
            }

            $courseid = (int)$context->instanceid;
            $data = (object)[
                'coins' => array_values($DB->get_records(
                    'local_ascend_rewards_coins',
                    ['userid' => $userid, 'courseid' => $courseid],
                    'timecreated ASC',
                )),
                'gameboard' => array_values($DB->get_records(
                    'local_ascend_rewards_gameboard',
                    ['userid' => $userid, 'courseid' => $courseid],
                    'timecreated ASC',
                )),
                'badgecache' => array_values($DB->get_records(
                    'local_ascend_rewards_badge_cache',
                    ['userid' => $userid, 'courseid' => $courseid],
                    'timemodified ASC',
                )),
                'xp' => array_values($DB->get_records(
                    'local_ascend_rewards_xp',
                    ['userid' => $userid, 'courseid' => $courseid],
                    'timemodified ASC',
                )),
            ];

            writer::with_context($context)->export_data(
                ['ascend_rewards', 'course_' . $courseid],
                $data,
            );
        }
    }

    /**
     * Delete all user data in the specified context.
     *
     * @param context $context The context to delete data from.
     */
    public static function delete_data_for_all_users_in_context(context $context): void {
        global $DB;

        if ($context->contextlevel === CONTEXT_SYSTEM) {
            $DB->delete_records('local_ascend_rewards_coins', ['courseid' => 0]);
            $DB->delete_records('local_ascend_rewards_gameboard', ['courseid' => 0]);
            $DB->delete_records('local_ascend_rewards_badge_cache', ['courseid' => 0]);
            $DB->delete_records('local_ascend_rewards_badgerlog');
            $DB->delete_records('local_ascend_rewards_mysterybox');
            $DB->delete_records('local_ascend_rewards_avatar_unlocks');
            $DB->delete_records('local_ascend_rewards_level_tokens');
            $DB->delete_records('local_ascend_rewards_xp', ['courseid' => 0]);
            return;
        }

        if ($context->contextlevel !== CONTEXT_COURSE) {
            return;
        }

        $courseid = (int)$context->instanceid;
        $DB->delete_records('local_ascend_rewards_coins', ['courseid' => $courseid]);
        $DB->delete_records('local_ascend_rewards_gameboard', ['courseid' => $courseid]);
        $DB->delete_records('local_ascend_rewards_badge_cache', ['courseid' => $courseid]);
        $DB->delete_records('local_ascend_rewards_xp', ['courseid' => $courseid]);
    }

    /**
     * Delete all user data for the approved contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;

        if (!$contextlist->count()) {
            return;
        }

        $userid = (int)$contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel === CONTEXT_SYSTEM) {
                $DB->delete_records_select(
                    'local_ascend_rewards_coins',
                    'userid = :userid AND (courseid IS NULL OR courseid = 0)',
                    ['userid' => $userid],
                );
                $DB->delete_records('local_ascend_rewards_gameboard', [
                    'userid' => $userid,
                    'courseid' => 0,
                ]);
                $DB->delete_records('local_ascend_rewards_badge_cache', [
                    'userid' => $userid,
                    'courseid' => 0,
                ]);
                $DB->delete_records('local_ascend_rewards_badgerlog', ['userid' => $userid]);
                $DB->delete_records('local_ascend_rewards_mysterybox', ['userid' => $userid]);
                $DB->delete_records('local_ascend_rewards_avatar_unlocks', ['userid' => $userid]);
                $DB->delete_records('local_ascend_rewards_level_tokens', ['userid' => $userid]);
                $DB->delete_records('local_ascend_rewards_xp', ['userid' => $userid, 'courseid' => 0]);
                continue;
            }

            if ($context->contextlevel !== CONTEXT_COURSE) {
                continue;
            }

            $courseid = (int)$context->instanceid;
            $DB->delete_records('local_ascend_rewards_coins', [
                'userid' => $userid,
                'courseid' => $courseid,
            ]);
            $DB->delete_records('local_ascend_rewards_gameboard', [
                'userid' => $userid,
                'courseid' => $courseid,
            ]);
            $DB->delete_records('local_ascend_rewards_badge_cache', [
                'userid' => $userid,
                'courseid' => $courseid,
            ]);
            $DB->delete_records('local_ascend_rewards_xp', [
                'userid' => $userid,
                'courseid' => $courseid,
            ]);
        }
    }

    /**
     * Get the list of users who have data in the specified context.
     *
     * @param userlist $userlist The userlist to add users to.
     */
    public static function get_users_in_context(userlist $userlist): void {
        global $DB;

        $context = $userlist->get_context();

        if ($context->contextlevel === CONTEXT_SYSTEM) {
            $sql = "SELECT userid
                      FROM {local_ascend_rewards_badgerlog}
                    UNION
                    SELECT userid
                      FROM {local_ascend_rewards_mysterybox}
                    UNION
                    SELECT userid
                      FROM {local_ascend_rewards_avatar_unlocks}
                    UNION
                    SELECT userid
                      FROM {local_ascend_rewards_level_tokens}
                    UNION
                    SELECT userid
                      FROM {local_ascend_rewards_coins}
                     WHERE courseid IS NULL OR courseid = 0
                    UNION
                    SELECT userid
                      FROM {local_ascend_rewards_gameboard}
                     WHERE courseid = 0
                    UNION
                    SELECT userid
                      FROM {local_ascend_rewards_xp}
                     WHERE courseid = 0";
            $userlist->add_from_sql('userid', $sql, []);
            return;
        }

        if ($context->contextlevel !== CONTEXT_COURSE) {
            return;
        }

        $params = ['courseid' => (int)$context->instanceid];
        $sql = "SELECT userid
                  FROM {local_ascend_rewards_coins}
                 WHERE courseid = :courseid
                UNION
                SELECT userid
                  FROM {local_ascend_rewards_gameboard}
                 WHERE courseid = :courseid
                UNION
                SELECT userid
                  FROM {local_ascend_rewards_badge_cache}
                 WHERE courseid = :courseid
                UNION
                SELECT userid
                  FROM {local_ascend_rewards_xp}
                 WHERE courseid = :courseid";
        $userlist->add_from_sql('userid', $sql, $params);
    }

    /**
     * Delete data for multiple users in the specified context.
     *
     * @param approved_userlist $userlist The approved userlist.
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;

        $userids = $userlist->get_userids();
        if (empty($userids)) {
            return;
        }

        [$insql, $inparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'uid');
        $context = $userlist->get_context();

        if ($context->contextlevel === CONTEXT_SYSTEM) {
            $DB->delete_records_select('local_ascend_rewards_badgerlog', "userid {$insql}", $inparams);
            $DB->delete_records_select('local_ascend_rewards_mysterybox', "userid {$insql}", $inparams);
            $DB->delete_records_select('local_ascend_rewards_avatar_unlocks', "userid {$insql}", $inparams);
            $DB->delete_records_select('local_ascend_rewards_level_tokens', "userid {$insql}", $inparams);
            $DB->delete_records_select(
                'local_ascend_rewards_coins',
                "userid {$insql} AND (courseid IS NULL OR courseid = 0)",
                $inparams,
            );
            $DB->delete_records_select(
                'local_ascend_rewards_gameboard',
                "userid {$insql} AND courseid = 0",
                $inparams,
            );
            $DB->delete_records_select(
                'local_ascend_rewards_xp',
                "userid {$insql} AND courseid = 0",
                $inparams,
            );
            return;
        }

        if ($context->contextlevel !== CONTEXT_COURSE) {
            return;
        }

        $courseid = (int)$context->instanceid;
        $params = $inparams + ['courseid' => $courseid];
        $DB->delete_records_select(
            'local_ascend_rewards_coins',
            "userid {$insql} AND courseid = :courseid",
            $params,
        );
        $DB->delete_records_select(
            'local_ascend_rewards_gameboard',
            "userid {$insql} AND courseid = :courseid",
            $params,
        );
        $DB->delete_records_select(
            'local_ascend_rewards_badge_cache',
            "userid {$insql} AND courseid = :courseid",
            $params,
        );
        $DB->delete_records_select(
            'local_ascend_rewards_xp',
            "userid {$insql} AND courseid = :courseid",
            $params,
        );
    }

    /**
     * Determine whether the user has site-scoped data.
     *
     * @param int $userid The user id.
     * @return bool
     */
    private static function user_has_system_data(int $userid): bool {
        global $DB;

        $params = ['userid' => $userid];
        $systemtables = [
            'local_ascend_rewards_badgerlog',
            'local_ascend_rewards_mysterybox',
            'local_ascend_rewards_avatar_unlocks',
            'local_ascend_rewards_level_tokens',
        ];

        foreach ($systemtables as $table) {
            if ($DB->record_exists($table, ['userid' => $userid])) {
                return true;
            }
        }

        if (
            $DB->record_exists_select(
                'local_ascend_rewards_coins',
                'userid = :userid AND (courseid IS NULL OR courseid = 0)',
                $params,
            )
        ) {
            return true;
        }

        if (
            $DB->record_exists('local_ascend_rewards_gameboard', [
            'userid' => $userid,
            'courseid' => 0,
            ])
        ) {
            return true;
        }

        return $DB->record_exists('local_ascend_rewards_xp', ['userid' => $userid, 'courseid' => 0]);
    }
}
