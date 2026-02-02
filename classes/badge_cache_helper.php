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

// Preserve legacy naming and comment separators in this helper.
// phpcs:disable moodle.NamingConventions.ValidVariableName.VariableNameUnderscore
// phpcs:disable moodle.Commenting.InlineComment.InvalidEndChar,moodle.Commenting.InlineComment.NotCapital
// phpcs:disable moodle.WhiteSpace.WhiteSpaceInStrings.EndLine
// phpcs:disable moodle.Files.MoodleInternal.MoodleInternalNotNeeded

/**
 * Helper class for badge activity caching.
 *
 * @package   local_ascend_rewards
 * @copyright 2026 Elantis (Pty) LTD
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class badge_cache_helper {
    /**
     * Store activities directly in cache (called when badge is awarded)
     * This is faster than populate_cache() as it doesn't recalculate
     *
     * @param int $userid
     * @param int $courseid
     * @param int $badgeid
     * @param array $activities Array of activity names/descriptions
     * @param array $metadata Array of metadata (award_number, award_count, etc.)
     * @return bool Success
     */
    public static function store_cache($userid, $courseid, $badgeid, $activities, $metadata) {
        global $DB;

        if (empty($activities)) {
            return false;
        }

        $now = time();
        $cache_record = $DB->get_record('local_ascend_rewards_badge_cache', [
            'userid' => $userid,
            'courseid' => $courseid,
            'badgeid' => $badgeid,
        ]);

        if ($cache_record) {
            // Update existing cache
            $cache_record->activities = json_encode($activities);
            $cache_record->metadata = json_encode($metadata);
            $cache_record->timemodified = $now;
            $DB->update_record('local_ascend_rewards_badge_cache', $cache_record);
        } else {
            // Insert new cache
            $DB->insert_record('local_ascend_rewards_badge_cache', (object)[
                'userid' => $userid,
                'courseid' => $courseid,
                'badgeid' => $badgeid,
                'activities' => json_encode($activities),
                'metadata' => json_encode($metadata),
                'timecreated' => $now,
                'timemodified' => $now,
            ]);
        }

        return true;
    }

    /**
     * Populate cache for a badge award by recalculating
     * Only use this for legacy/repair operations - prefer store_cache() when awarding
     *
     * @param int $userid
     * @param int $courseid
     * @param int $badgeid
     * @return bool Success
     */
    public static function populate_cache($userid, $courseid, $badgeid) {
        global $DB;

        // Force recalculation by calling get_activities logic
        $result = self::calculate_activities($userid, $courseid, $badgeid);

        if (empty($result['activities'])) {
            return false;
        }

        return self::store_cache($userid, $courseid, $badgeid, $result['activities'], $result['metadata']);
    }

    /**
     * Calculate qualifying activities for a badge
     * This is the core logic extracted from get_activities.php
     *
     * @param int $userid
     * @param int $courseid
     * @param int $badgeid
     * @return array ['activities' => array, 'metadata' => array]
     */
    public static function calculate_activities($userid, $courseid, $badgeid) {
        global $DB;

        // Directly calculate instead of making HTTP request to avoid localhost blocking
        // This implements the core activity counting logic

        try {
            // Get the course
            $course = $DB->get_record('course', ['id' => $courseid]);
            if (!$course) {
                return ['activities' => [], 'metadata' => []];
            }

            // Get modules in this course
            $modules = $DB->get_records('course_modules', ['course' => $courseid]);
            if (empty($modules)) {
                return ['activities' => [], 'metadata' => []];
            }

            $activities = [];
            $activity_count = 0;

            // Count completions for each module
            foreach ($modules as $module) {
                $completion = $DB->get_record('course_modules_completion', [
                    'userid' => $userid,
                    'coursemoduleid' => $module->id,
                    'completionstate' => 1,
                ]);

                if ($completion) {
                    // Get the module name
                    $cm = $DB->get_record_sql(
                        "SELECT m.name FROM {modules} m
                         JOIN {course_modules} cm ON cm.module = m.id
                         WHERE cm.id = ?",
                        [$module->id]
                    );

                    if ($cm) {
                        $activities[] = $cm->name;
                        $activity_count++;
                    }
                }
            }

            // Build metadata
            $metadata = [
                'activity_count' => $activity_count,
                'badge_id' => $badgeid,
                'user_id' => $userid,
                'course_id' => $courseid,
                'timestamp' => time(),
            ];

            return ['activities' => $activities, 'metadata' => $metadata];
        } catch (\Exception $e) {
            // Log error and return empty.
            debugging('ascend_rewards badge cache error: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return ['activities' => [], 'metadata' => []];
        }
    }

    /**
     * Invalidate cache for a user's badge in a course
     * Call this when activities are added/removed/modified
     *
     * @param int $userid
     * @param int $courseid
     * @param int $badgeid (optional - if null, clear all badges in course)
     */
    public static function invalidate_cache($userid, $courseid, $badgeid = null) {
        global $DB;

        if ($badgeid) {
            $DB->delete_records('local_ascend_rewards_badge_cache', [
                'userid' => $userid,
                'courseid' => $courseid,
                'badgeid' => $badgeid,
            ]);
        } else {
            // Clear all badges for this user/course
            $DB->delete_records('local_ascend_rewards_badge_cache', [
                'userid' => $userid,
                'courseid' => $courseid,
            ]);
        }
    }

    /**
     * Rebuild cache for all users (for nightly task)
     *
     * @param int $limit Max records to process (default 1000 per run)
     * @return int Number of cache entries rebuilt
     */
    public static function rebuild_all_cache($limit = 1000) {
        global $DB;

        $count = 0;

        // Get all badge awards that need cache
        // Use badgeid as first column in SELECT DISTINCT for uniqueness
        $sql = "SELECT DISTINCT bc.id, c.userid, c.courseid, c.badgeid
                  FROM {local_ascend_rewards_coins} c
                  LEFT JOIN {local_ascend_rewards_badge_cache} bc
                    ON bc.userid = c.userid
                   AND bc.courseid = c.courseid
                   AND bc.badgeid = c.badgeid
                 WHERE bc.id IS NULL
              ORDER BY c.timecreated DESC";

        $awards = $DB->get_records_sql($sql, [], 0, $limit);

        foreach ($awards as $award) {
            if (self::populate_cache($award->userid, $award->courseid, $award->badgeid)) {
                $count++;
            }
        }

        return $count;
    }
}
