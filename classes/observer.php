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
 * Event observer for Ascend Rewards plugin.
 *
 * Observes activity completion events and triggers badge awarding logic.
 *
 * @package   local_ascend_rewards
 * @copyright 2026 Elantis (Pty) LTD
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_ascend_rewards;

// phpcs:disable moodle.Files.MoodleInternal.MoodleInternalNotNeeded
defined('MOODLE_INTERNAL') || die();

// This observer contains legacy naming and comment separators.
// phpcs:disable moodle.NamingConventions.ValidVariableName.VariableNameUnderscore
// phpcs:disable moodle.Commenting.InlineComment.InvalidEndChar,moodle.Commenting.InlineComment.NotCapital
// phpcs:disable moodle.Commenting.MissingDocblock.Function
// phpcs:disable moodle.Files.MoodleInternal.MoodleInternalNotNeeded
// phpcs:disable moodle.Files.LineLength.MaxExceeded,moodle.Files.LineLength.TooLong
// phpcs:disable moodle.WhiteSpace.WhiteSpaceInStrings.EndLine

/**
 * Event observer class for activity completion changes.
 */
class observer {
    /**
     * Triggered when course module completion is updated.
     *
     * @param \core\event\course_module_completion_updated $event The completion event
     */
    public static function course_module_completion_updated(\core\event\course_module_completion_updated $event) {
        global $DB;

        $userid = $event->relateduserid;
        $coursemoduleid = $event->contextinstanceid;
        $courseid = $event->courseid;
        $completionstate = $event->other['completionstate'] ?? null;

        // If completion state changed to incomplete (0 or not set), revoke relevant badges
        if ($completionstate === COMPLETION_INCOMPLETE || $completionstate === 0) {
            self::revoke_badges_for_incomplete_activity($userid, $coursemoduleid, $courseid);
        }
    }

    /**
     * Revoke badges and coins when an activity becomes incomplete
     * @param int $userid User ID
     * @param int $coursemoduleid Course module ID that became incomplete
     * @param int $courseid Course ID
     */
    private static function revoke_badges_for_incomplete_activity(int $userid, int $coursemoduleid, int $courseid) {
        global $DB;

        // Get all badges awarded to this user in this course
        $awarded_badges = $DB->get_records('local_ascend_rewards_coins', [
            'userid' => $userid,
            'courseid' => $courseid,
        ]);

        if (empty($awarded_badges)) {
            return;
        }

        $badges_revoked = false;

        // Re-check each badge to see if it still qualifies
        // If not, revoke it
        foreach ($awarded_badges as $award) {
            $badgeid = $award->badgeid;

            // Check if badge still qualifies using badge_awarder logic
            if (!self::badge_still_qualifies($userid, $badgeid, $courseid)) {
                // Revoke the badge and coins
                $DB->delete_records('local_ascend_rewards_coins', [
                    'userid' => $userid,
                    'badgeid' => $badgeid,
                    'courseid' => $courseid,
                ]);

                // Log the revocation
                $DB->insert_record('local_ascend_rewards_badgerlog', (object)[
                    'userid' => $userid,
                    'badgeid' => $badgeid,
                    'status' => 'revoked',
                    'message' => "Badge revoked due to activity completion change in course {$courseid}",
                    'timecreated' => time(),
                ], false);

                $badges_revoked = true;
            }
        }

        // Clear coaching insights cache for this course if any badges were revoked
        // This ensures the AI coaching regenerates with updated journey/badge data
        if ($badges_revoked) {
            $cachekey = 'ascend_coaching_' . $courseid;
            unset_user_preference($cachekey, $userid);
            unset_user_preference($cachekey . '_meta', $userid);
        }
    }

    /**
     * Check if a badge still qualifies for the user
     * @param int $userid User ID
     * @param int $badgeid Badge ID
     * @param int $courseid Course ID
     * @return bool True if badge still qualifies
     */
    private static function badge_still_qualifies(int $userid, int $badgeid, int $courseid): bool {
        global $DB;

        // Repeatable badges can have multiple awards and should not be revoked
        // because they're earned progressively as users complete more activities
        $repeatable_badges = [4, 9, 11, 13, 14, 15, 19]; // On a Roll, Early Bird, Sharp Shooter, Feedback Follower, Tenacious Tiger, Steady Improver, High Flyer
        if (in_array($badgeid, $repeatable_badges)) {
            return true; // Never revoke repeatable badges
        }

        // Map badge IDs to their check methods
        $badges = [
            6  => 'getting_started',
            4  => 'on_a_roll',
            5  => 'halfway_hero',
            8  => 'master_navigator',
            9  => 'early_bird',
            11 => 'sharp_shooter',
            10 => 'deadline_burner',
            12 => 'time_tamer',
            13 => 'feedback_follower',
            15 => 'steady_improver',
            14 => 'tenacious_tiger',
            16 => 'glory_guide',
            19 => 'high_flyer',
            17 => 'activity_ace',
            7  => 'mission_complete',
            20 => 'learning_legend',
        ];

        // Meta badges that depend on other badges
        $meta_categories = [
            8  => [6, 4, 5], // Master Navigator depends on Getting Started, On a Roll, Halfway Hero
            12 => [9, 11, 10], // Time Tamer depends on Early Bird, Sharp Shooter, Deadline Burner
            16 => [13, 15, 14], // Glory Guide depends on Feedback Follower, Steady Improver, Tenacious Tiger
            20 => [19, 17, 7], // Learning Legend depends on High Flyer, Activity Ace, Mission Complete
        ];

        // Get course activities and completions
        $course_activities = $DB->get_records_sql("
            SELECT cm.id AS cmid, cm.module, m.name AS modname, cm.instance
              FROM {course_modules} cm
              JOIN {modules} m ON m.id = cm.module
             WHERE cm.course = :courseid
               AND cm.completion > 0
        ", ['courseid' => $courseid]) ?: [];

        $course_completions = $DB->get_records_sql("
            SELECT cmc.coursemoduleid AS cmid, cmc.timemodified
              FROM {course_modules_completion} cmc
              JOIN {course_modules} cm ON cm.id = cmc.coursemoduleid
             WHERE cm.course = :courseid
               AND cmc.userid = :userid
               AND cmc.completionstate >= 1
        ", ['courseid' => $courseid, 'userid' => $userid]) ?: [];

        $course_grades = $DB->get_records_sql("
            SELECT gi.id, gi.itemmodule, gi.iteminstance, gi.grademax, gi.gradepass
              FROM {grade_items} gi
             WHERE gi.courseid = :courseid
               AND gi.itemtype = 'mod'
        ", ['courseid' => $courseid]) ?: [];

        // Check if this is a meta badge
        if (isset($meta_categories[$badgeid])) {
            // Count how many base badges the user still has
            $base_badge_ids = $meta_categories[$badgeid];
            $earned = 0;
            foreach ($base_badge_ids as $base_id) {
                if (
                    $DB->record_exists('local_ascend_rewards_coins', [
                    'userid' => $userid,
                    'badgeid' => $base_id,
                    'courseid' => $courseid,
                    ])
                ) {
                    $earned++;
                }
            }
            return ($earned >= 2);
        }

        // For regular badges, call the appropriate check method
        if (!isset($badges[$badgeid])) {
            return false;
        }

        $method = $badges[$badgeid];
        $check_method = "check_{$method}";

        // Use badge_awarder to check if badge still qualifies
        // Pass skip_awarded_filter=true so we check raw qualification, not filtered by already-awarded activities
        if (method_exists('\local_ascend_rewards\badge_awarder', $check_method)) {
            $reflection = new \ReflectionMethod('\local_ascend_rewards\badge_awarder', $check_method);
            $reflection->setAccessible(true);
            $result = $reflection->invoke(null, $userid, $courseid, $course_activities, $course_completions, $course_grades, true);
            return $result['result'] ?? false;
        }

        return false;
    }

    /**
     * Clean up orphaned badges/coins for a user in a course
     * (badges that no longer qualify due to missing activity completions)
     * This should be called periodically or when course data might be out of sync
     *
     * @param int $userid User ID
     * @param int|null $courseid Course ID (null for all courses)
     * @return int Number of badges revoked
     */
    public static function cleanup_orphaned_badges(int $userid, ?int $courseid = null): int {
        global $DB;

        $params = ['userid' => $userid];
        $coursewhere = '';

        if ($courseid !== null) {
            $coursewhere = ' AND courseid = :courseid';
            $params['courseid'] = $courseid;
        }

        // Get all badges awarded to this user (optionally filtered by course)
        $awarded_badges = $DB->get_records_sql(
            "SELECT * FROM {local_ascend_rewards_coins}
             WHERE userid = :userid {$coursewhere}",
            $params
        );

        if (empty($awarded_badges)) {
            return 0;
        }

        $revoked_count = 0;

        // Group by course for efficiency
        $by_course = [];
        foreach ($awarded_badges as $award) {
            $cid = (int)$award->courseid;
            if (!isset($by_course[$cid])) {
                $by_course[$cid] = [];
            }
            $by_course[$cid][] = $award;
        }

        // Check each course
        foreach ($by_course as $cid => $awards) {
            foreach ($awards as $award) {
                $badgeid = (int)$award->badgeid;

                // Check if badge still qualifies
                if (!self::badge_still_qualifies($userid, $badgeid, $cid)) {
                    // Revoke the badge and coins
                    $DB->delete_records('local_ascend_rewards_coins', [
                        'userid' => $userid,
                        'badgeid' => $badgeid,
                        'courseid' => $cid,
                    ]);

                    // Log the revocation
                    $DB->insert_record('local_ascend_rewards_badgerlog', (object)[
                        'userid' => $userid,
                        'badgeid' => $badgeid,
                        'status' => 'revoked',
                        'message' => "Badge revoked during orphaned badge cleanup in course {$cid}",
                        'timecreated' => time(),
                    ], false);

                    $revoked_count++;
                }
            }

            // Clear coaching cache for this course if any badges were revoked
            if ($revoked_count > 0) {
                $cachekey = 'ascend_coaching_' . $cid;
                unset_user_preference($cachekey, $userid);
                unset_user_preference($cachekey . '_meta', $userid);
            }
        }

        return $revoked_count;
    }
}
