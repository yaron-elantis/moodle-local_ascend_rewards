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

namespace local_ascend_rewards;

// phpcs:disable moodle.Files.MoodleInternal.MoodleInternalNotNeeded
defined('MOODLE_INTERNAL') || die();

// This helper uses legacy naming and separator comments.
// phpcs:disable moodle.NamingConventions.ValidVariableName.VariableNameUnderscore
// phpcs:disable moodle.Commenting.InlineComment.InvalidEndChar,moodle.Commenting.InlineComment.NotCapital
// phpcs:disable moodle.Commenting.MissingDocblock.Function
// phpcs:disable moodle.Files.MoodleInternal.MoodleInternalNotNeeded
// phpcs:disable Generic.CodeAnalysis.EmptyStatement.DetectedCatch

/**
 * Cache warming helper.
 *
 * Pre-loads badge cache entries that a user is close to earning.
 *
 * @package   local_ascend_rewards
 * @copyright 2026 Elantis (Pty) LTD
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cache_warmer {
    /**
     * Warm cache for a user's badges they're close to earning
     * Call this on login or page load to improve perceived performance
     *
     * @param int $userid
     * @param int $courseid (optional - if provided, only warm for this course)
     * @return int Number of cache entries warmed
     */
    public static function warm_user_cache($userid, $courseid = null) {
        global $DB;

        $warmed = 0;

        // Get user's courses
        $courses = $courseid ? [$courseid] : self::get_user_courses($userid);

        foreach ($courses as $cid) {
            // Get badges user doesn't have yet in this course
            $missing_badges = self::get_missing_badges($userid, $cid);

            // Check if user is close to earning any of these
            $close_badges = self::get_close_badges($userid, $cid, $missing_badges);

            // Pre-warm cache for these badges
            foreach ($close_badges as $badgeid) {
                // Check if already cached
                if (
                    !$DB->record_exists('local_ascend_rewards_badge_cache', [
                    'userid' => $userid,
                    'courseid' => $cid,
                    'badgeid' => $badgeid,
                    ])
                ) {
                    // Not cached yet - trigger calculation asynchronously
                    self::async_populate_cache($userid, $cid, $badgeid);
                    $warmed++;
                }
            }
        }

        return $warmed;
    }

    /**
     * Get user's active courses
     */
    private static function get_user_courses($userid) {
        $courses = enrol_get_users_courses($userid, true, 'id, visible');
        $courseids = [];
        foreach ($courses as $course) {
            if ($course->id > 1 && $course->visible == 1) {
                $courseids[] = $course->id;
            }
        }
        return $courseids;
    }

    /**
     * Get badges user hasn't earned yet in a course
     */
    private static function get_missing_badges($userid, $courseid) {
        global $DB;

        $all_badges = [6, 4, 5, 8, 9, 11, 10, 12, 13, 15, 14, 16, 19, 17, 7, 20];
        $earned_badges = $DB->get_fieldset_select(
            'local_ascend_rewards_coins',
            'badgeid',
            'userid = :userid AND courseid = :courseid',
            ['userid' => $userid, 'courseid' => $courseid]
        );

        return array_diff($all_badges, $earned_badges);
    }

    /**
     * Determine which badges user is close to earning
     * Returns badges where user has completed 50%+ of requirements
     */
    private static function get_close_badges($userid, $courseid, $missing_badges) {
        global $DB;

        $close = [];

        // Get user's completion count
        $completion_count = $DB->count_records_sql(
            "SELECT COUNT(DISTINCT cmc.coursemoduleid)
               FROM {course_modules_completion} cmc
               JOIN {course_modules} cm ON cm.id = cmc.coursemoduleid
              WHERE cm.course = :courseid
                AND cmc.userid = :userid
                AND cmc.completionstate >= 1",
            ['courseid' => $courseid, 'userid' => $userid]
        );

        // Get total activities
        $total_activities = $DB->count_records_sql(
            "SELECT COUNT(cm.id)
               FROM {course_modules} cm
              WHERE cm.course = :courseid
                AND cm.completion > 0",
            ['courseid' => $courseid]
        );

        if ($total_activities == 0) {
            return [];
        }

        $completion_pct = ($completion_count / $total_activities) * 100;

        // Badge proximity logic
        foreach ($missing_badges as $badgeid) {
            switch ($badgeid) {
                case 6: // Getting Started - 1 completion
                    if ($completion_count >= 1) {
                        $close[] = $badgeid;
                    }
                    break;
                case 4: // On a Roll - 3 completions
                    if ($completion_count >= 2) {
                        $close[] = $badgeid;
                    }
                    break;
                case 5: // Halfway Hero - 50%
                    if ($completion_pct >= 40) {
                        $close[] = $badgeid;
                    }
                    break;
                case 7: // Mission Complete - 100%
                    if ($completion_pct >= 90) {
                        $close[] = $badgeid;
                    }
                    break;
                case 19: // High Flyer - 2+ passed
                    if ($completion_count >= 2) {
                        $close[] = $badgeid;
                    }
                    break;
                // Add more logic as needed
            }
        }

        return $close;
    }

    /**
     * Asynchronously populate cache (non-blocking)
     */
    private static function async_populate_cache($userid, $courseid, $badgeid) {
        // For now, just populate synchronously but limit time
        // In production, you could use adhoc tasks or background processes
        try {
            $helper = new \local_ascend_rewards\badge_cache_helper();
            $helper->populate_cache($userid, $courseid, $badgeid);
        } catch (\Exception $e) {
            // Silently fail - cache warming is not critical
        }
    }
}
