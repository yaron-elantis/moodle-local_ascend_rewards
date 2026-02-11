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
 * Badge awarder class for Ascend Rewards plugin.
 *
 * Handles automatic badge awarding logic based on student activities and achievements.
 * Manages coin and XP rewards, badge checking, and badge awarding.
 *
 * @package   local_ascend_rewards
 * @copyright 2026 Elantis (Pty) LTD
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_ascend_rewards;

defined('MOODLE_INTERNAL') || die();

// This file contains a large amount of legacy logic and separator comments.
// Suppress style sniffs that would require broad renaming/reflow.
// phpcs:disable moodle.NamingConventions.ValidVariableName.VariableNameUnderscore
// phpcs:disable moodle.Commenting.InlineComment.InvalidEndChar
// phpcs:disable moodle.Commenting.MissingDocblock.Function,moodle.Commenting.MissingDocblock.Constant
// phpcs:disable moodle.Files.LineLength.MaxExceeded,moodle.Files.LineLength.TooLong
// phpcs:disable moodle.WhiteSpace.WhiteSpaceInStrings.EndLine
// phpcs:disable moodle.NamingConventions.ValidVariableName.VariableNameLowerCase
// phpcs:disable moodle.NamingConventions.ValidVariableName.MemberNameUnderscore
// phpcs:disable moodle.Commenting.InlineComment.NotCapital,moodle.Commenting.DocblockDescription.Missing
// phpcs:disable moodle.ControlStructures.ControlSignature.Found,Squiz.ControlStructures.ControlSignature.SpaceAfterCloseBrace

require_once($CFG->dirroot . '/grade/lib.php');
require_once($CFG->dirroot . '/mod/assign/locallib.php');
require_once($CFG->dirroot . '/lib/accesslib.php');
require_once($CFG->dirroot . '/lib/enrollib.php');

/**
 * Badge awarder class for Ascend Rewards.
 *
 * Awards badges recorded in local_ascend_rewards_coins and local_ascend_rewards_badgerlog.
 * Handles automatic badge awarding based on activity completion and student achievements.
 *
 * @package   local_ascend_rewards
 * @copyright 2026 Elantis (Pty) LTD
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class badge_awarder {
    /** @var array<int,array{grademax:float, pass:float}> Grade item cache */
    private static $gi_cache = [];

    /**
     * Safe counter for arrays / Countable / stdClass / recordsets.
     *
     * @param mixed $value Value to count
     * @return int Count of items
     */
    private static function safe_count($value): int {
        if (is_array($value) || $value instanceof \Countable) {
            return count($value);
        }
        if ($value instanceof \moodle_recordset) {
            $n = 0;
            foreach ($value as $_) {
                $n++;
            }
            $value->close();
            return $n;
        }
        if (is_object($value)) {
            foreach (['items', 'results', 'records', 'rows'] as $prop) {
                if (property_exists($value, $prop) && (is_array($value->$prop) || $value->$prop instanceof \Countable)) {
                    return count($value->$prop);
                }
            }
            return 1;
        }
        return empty($value) ? 0 : 1;
    }

    /**
     * Plugin-internal logger to local_ascend_rewards_badgerlog.
     */
    private static function log(int $userid, int $badgeid, string $status, string $message): void {
        global $DB;
        $DB->insert_record('local_ascend_rewards_badgerlog', (object)[
            'userid'      => $userid,
            'badgeid'     => $badgeid,
            'status'      => $status,
            'message'     => $message,
            'timecreated' => time(),
        ], false);
    }

    /**
     * Map badge IDs to coin values.
     * DEMO VERSION: Only 7 badges enabled
     */
    public static function coins_for_badge(int $badgeid): int {
        // DEMO: Only these 7 badges award coins
        $demo_badges = [6, 5, 8, 13, 15, 14, 16];
        if (!in_array($badgeid, $demo_badges, true)) {
            return 0;
        }

        $override = get_config('local_ascend_rewards', "coins_badge_{$badgeid}");
        if ($override !== false) {
            return (int)$override;
        }

        return coin_map::coins_for_badge($badgeid);
    }

    /**
     * Map badge IDs to XP values (independent of coins).
     * DEMO VERSION: Only 7 badges enabled
     */
    public static function xp_for_badge(int $badgeid): int {
        // DEMO: Only these 7 badges award XP
        $xp_map = [
            6  => 50,
            5  => 250,
            8  => 600,
            13 => 100,
            15 => 200,
            14 => 250,
            16 => 600,
        ];
        return $xp_map[$badgeid] ?? 0;
    }

    /**
     * Update the XP table when badges are awarded.
     * XP is PERMANENT - it accumulates and NEVER decreases.
     * This is SEPARATE from coins which can be spent.
     * Rankings are ALWAYS based on XP from this table.
     *
     * @param int $userid User ID
     * @param int $courseid Course ID
     * @param int $xp_earned XP to add
     */
    private static function update_xp_table(int $userid, int $courseid, int $xp_earned): void {
        global $DB;

        if ($xp_earned <= 0) {
            return;
        }

        try {
            // Update COURSE-specific XP
            $course_record = $DB->get_record('local_ascend_rewards_xp', [
                'userid' => $userid,
                'courseid' => $courseid,
            ]);

            if ($course_record) {
                $course_record->xp += $xp_earned;
                $course_record->timemodified = time();
                $DB->update_record('local_ascend_rewards_xp', $course_record);
            } else {
                $DB->insert_record('local_ascend_rewards_xp', (object)[
                    'userid' => $userid,
                    'courseid' => $courseid,
                    'xp' => $xp_earned,
                    'timemodified' => time(),
                ]);
            }

            // Update SITE-WIDE XP (courseid = 0)
            $site_record = $DB->get_record('local_ascend_rewards_xp', [
                'userid' => $userid,
                'courseid' => 0,
            ]);

            if ($site_record) {
                $site_record->xp += $xp_earned;
                $site_record->timemodified = time();
                $DB->update_record('local_ascend_rewards_xp', $site_record);
            } else {
                $DB->insert_record('local_ascend_rewards_xp', (object)[
                    'userid' => $userid,
                    'courseid' => 0,
                    'xp' => $xp_earned,
                    'timemodified' => time(),
                ]);
            }
        } catch (\Exception $e) {
            self::log($userid, 0, 'warning', 'XP table update failed: ' . $e->getMessage());
        }
    }

    /**
     * Main entry point for scheduled task.
     */
    public static function run() {
        global $DB;

        $users = $DB->get_records_sql("SELECT u.id FROM {user} u WHERE u.deleted = 0 AND u.suspended = 0");

        $results = [];
        foreach ($users as $user) {
            $results = array_merge($results, self::check_all_badges((int)$user->id));
        }
        return $results;
    }

    /** Check all badges (meta first, then course-scoped unless throttled) */
    public static function check_all_badges(int $userid) {
        global $DB;

        $user = $DB->get_record('user', ['id' => $userid], '*', IGNORE_MISSING);
        if (!$user) {
            return ["User $userid not found."];
        }
        /* Exclusions temporarily disabled so all users can be evaluated. */

        $results = [];
        $results[] = "Checking all badges for user ID: $userid";

        $lastcheck = 0;
        $skipcourses = false; // throttle disabled for now

        $courses = enrol_get_users_courses($userid, true, 'id, visible');
        $eligible_courseids = [];
        foreach ($courses as $cid => $course) {
            if ((int)$cid > 1 && (int)$course->visible === 1) {
                $eligible_courseids[] = (int)$cid;
            }
        }

        $course_activities = [];
        $course_completions = [];
        $course_grades = [];
        if (!$skipcourses && !empty($eligible_courseids)) {
            foreach ($eligible_courseids as $courseid) {
                $course_activities[$courseid] = $DB->get_records_sql("
                    SELECT cm.id AS cmid, cm.module, m.name AS modname, cm.instance
                      FROM {course_modules} cm
                      JOIN {modules} m ON m.id = cm.module
                     WHERE cm.course = :courseid
                       AND cm.completion > 0
                ", ['courseid' => $courseid]) ?: [];
                $course_completions[$courseid] = $DB->get_records_sql("
                    SELECT cmc.coursemoduleid AS cmid, cmc.timemodified
                      FROM {course_modules_completion} cmc
                      JOIN {course_modules} cm ON cm.id = cmc.coursemoduleid
                     WHERE cm.course = :courseid
                       AND cmc.userid = :userid
                       AND cmc.completionstate >= 1
                ", ['courseid' => $courseid, 'userid' => $userid]) ?: [];
                $course_grades[$courseid] = $DB->get_records_sql("
                    SELECT gi.id, gi.itemmodule, gi.iteminstance, gi.grademax, gi.gradepass
                      FROM {grade_items} gi
                     WHERE gi.courseid = :courseid
                       AND gi.itemtype = 'mod'
                ", ['courseid' => $courseid]) ?: [];
            }
        }

        // DEMO VERSION: Only 7 badges enabled (kept badges only)
        $badges = [
            'getting_started'  => 6,
            'halfway_hero'     => 5,
            'master_navigator' => 8,
            'feedback_follower' => 13,
            'steady_improver'  => 15,
            'tenacious_tiger'  => 14,
            'glory_guide'      => 16,
        ];

        $meta_categories = [
            'master_navigator' => [6, 5], // Progress (Getting Started, Halfway Hero)
            'glory_guide'      => [13, 15, 14], // Quality & Growth (Feedback Follower, Steady Improver, Tenacious Tiger)
        ];

        if ($skipcourses) {
            $results[] = "Course-scoped checks skipped (throttle) for user $userid.";
            return $results;
        }
        if (empty($eligible_courseids)) {
            $results[] = "User $userid has no eligible visible courses.";
            set_user_preferences(['ascend_last_badge_check' => time()], $userid);
            return $results;
        }

        foreach ($eligible_courseids as $courseid) {
            foreach ($badges as $method => $badgeid) {
                if (array_key_exists($method, $meta_categories)) {
                    continue; // Skip metas, handled after base badges per course
                }
                $badgename = str_replace('_', ' ', ucwords($method));
                $check_fn = "check_$method";

                // SMART SKIP: Skip badges that are impossible to earn based on current progress
                $completions = $course_completions[$courseid] ?? [];
                $completion_count = count($completions);

                // Skip Getting Started if already have 1+ completions
                if ($method === 'getting_started' && $completion_count > 0 && self::coin_already_awarded($userid, $badgeid, $courseid)) {
                    continue;
                }

                // Skip Halfway Hero based on completion %
                if (in_array($method, ['halfway_hero', 'mission_complete'], true)) {
                    $total_activities = count($course_activities[$courseid] ?? []);
                    if ($total_activities > 0) {
                        $completion_pct = ($completion_count / $total_activities) * 100;
                        if ($method === 'halfway_hero' && $completion_pct < 50) {
                            continue; // Not 50% yet
                        }
                        if ($method === 'mission_complete' && $completion_pct < 100) {
                            continue; // Not 100% yet
                        }
                    }
                }

                // Skip grade-dependent badges if no grades
                if (
                    in_array($method, ['tenacious_tiger', 'steady_improver', 'feedback_follower'], true)
                    && empty($course_grades[$courseid])
                ) {
                    $results[] = "No grades for user $userid in course $courseid, skipping $badgename.";
                    self::log($userid, $badgeid, 'debug', "course=$courseid :: $badgename: no grades");
                    continue;
                }

                // Skip the "already awarded" check for repeatable badges
                if (!self::is_repeatable_badge($badgeid) && self::coin_already_awarded($userid, $badgeid, $courseid)) {
                    $results[] = "$badgename: coins already awarded in course $courseid.";
                    self::log($userid, $badgeid, 'debug', "course=$courseid :: $badgename: coins already awarded");
                    continue;
                }

                $completions = $course_completions[$courseid] ?? [];
                if (is_object($completions)) {
                    self::log($userid, $badgeid, 'error', "course=$courseid :: $badgename: course_completions[$courseid] is stdClass, expected array");
                    $completions = [$completions];
                }

                $check = self::{$check_fn}(
                    $userid,
                    $courseid,
                    $course_activities[$courseid] ?? [],
                    $completions,
                    $course_grades[$courseid] ?? []
                );

                $results[] = $check['result']
                    ? "Badge ID $badgeid ($badgename) conditions met in course $courseid."
                    : "Badge ID $badgeid ($badgename) conditions not met in course $courseid.";
                if (!empty($check['debug'])) {
                    $results[] = $check['debug'];
                }

                if ($check['result']) {
                    self::log($userid, $badgeid, 'success', "Badge conditions met for $badgename in course $courseid");
                    $contributing_activities = $check['activities'] ?? [];
                    self::award_coins($userid, $badgeid, $courseid, $contributing_activities);
                } else {
                    self::log($userid, $badgeid, 'debug', "course=$courseid :: " . ($check['debug'] ?? "$badgename: not met"));
                }
            }

            // Now check meta (gold) badges per course
            foreach ($meta_categories as $method => $base_ids) {
                $badgeid = $badges[$method];
                $badgename = str_replace('_', ' ', ucwords($method));

                if (self::coin_already_awarded($userid, $badgeid, $courseid)) {
                    $results[] = "Badge ID $badgeid ($badgename) already awarded in course $courseid.";
                    self::log($userid, $badgeid, 'debug', "$badgename: already awarded in course $courseid");
                    continue;
                }

                // Count base badges IN THIS COURSE ONLY (course-scoped metas)
                $earned = self::count_earned($userid, $base_ids, $courseid);
                $check = ['result' => ($earned >= 2), 'debug' => "$badgename: has {$earned}/2 of (" . implode(',', $base_ids) . ") in course $courseid."];

                $results[] = $check['result']
                    ? "Badge ID $badgeid ($badgename) conditions met in course $courseid."
                    : "Badge ID $badgeid ($badgename) conditions not met in course $courseid.";
                if (!empty($check['debug'])) {
                    $results[] = $check['debug'];
                }

                if ($check['result']) {
                    self::log($userid, $badgeid, 'success', "Badge conditions met for $badgename in course $courseid");
                    // Meta badges - get names of the base badges that contributed
                    $meta_activities = [];
                    foreach ($base_ids as $base_badgeid) {
                        if (self::coin_already_awarded($userid, $base_badgeid, $courseid)) {
                            $badge_names_map = [
                                6 => 'Getting Started', 5 => 'Halfway Hero',
                                13 => 'Feedback Follower', 15 => 'Steady Improver', 14 => 'Tenacious Tiger',
                            ];
                            $meta_activities[] = $badge_names_map[$base_badgeid] ?? "Badge #{$base_badgeid}";
                        }
                    }
                    self::award_coins($userid, $badgeid, $courseid, $meta_activities);
                } else {
                    self::log($userid, $badgeid, 'debug', $check['debug'] ?? "$badgename: not met in course $courseid");
                }
            }
        }

        set_user_preferences(['ascend_last_badge_check' => time()], $userid);
        return $results;
    }

    /* ==================== Helpers (PLUGIN-ONLY) ==================== */

    /**
     * Check if a badge can be awarded multiple times in the same course
     */
    public static function is_repeatable_badge(int $badgeid): bool {
        $repeatable_badges = [
            13, // Feedback Follower
            14, // Tenacious Tiger
            15, // Steady Improver
        ];
        return in_array($badgeid, $repeatable_badges, true);
    }

    /**
     * Get count of how many times a badge has been awarded to user in course
     */
    private static function badge_award_count(int $userid, int $badgeid, int $courseid): int {
        global $DB;
        return (int)$DB->count_records('local_ascend_rewards_coins', [
            'userid'   => $userid,
            'badgeid'  => $badgeid,
            'courseid' => $courseid,
        ]);
    }

    public static function coin_already_awarded(int $userid, int $badgeid, int $courseid): bool {
        global $DB;

        // Check if this is a repeatable badge
        if (self::is_repeatable_badge($badgeid)) {
            // For repeatable badges, never block (they can be earned unlimited times)
            return false;
        }

        // For non-repeatable badges, check if already awarded once
        return $DB->record_exists('local_ascend_rewards_coins', [
            'userid'   => $userid,
            'badgeid'  => $badgeid,
            'courseid' => $courseid,
        ]);
    }

    /**
     * Count DISTINCT base badges for a user.
     * If $courseid is provided (>0), restrict to that course; else count site-wide.
     * Uses positional params only to avoid "Mixed types of sql query parameters!!".
     */
    private static function count_earned(int $userid, array $badgeids, ?int $courseid = null): int {
        global $DB;

        if (empty($badgeids)) {
            return 0;
        }

        // Use positional placeholders only to avoid "Mixed types of sql query parameters!!".
        [$inSql, $inParams] = $DB->get_in_or_equal($badgeids, SQL_PARAMS_QM);

        $sql = "
            SELECT COUNT(DISTINCT badgeid)
              FROM {local_ascend_rewards_coins}
             WHERE userid = ?
        ";

        $params = [$userid];

        if ($courseid !== null) {
            $sql .= ' AND courseid = ?';
            $params[] = $courseid;
        }

        $sql .= " AND badgeid $inSql";

        $params = array_merge($params, $inParams);

        return (int)$DB->get_field_sql($sql, $params);
    }

    public static function award_coins(int $userid, int $badgeid, ?int $courseid, array $contributing_activities = []): void {
        global $DB, $CFG;

        self::log($userid, $badgeid, 'debug', "award_coins called: courseid=$courseid, activities=" . json_encode($contributing_activities));

        // Skip site-wide badges (only award coins for course-based badges)
        // CRITICAL: Never award coins to course ID 1 (the home/site page)
        if (!$courseid || $courseid <= 1) {
            self::log($userid, $badgeid, 'debug', "award_coins: skipping, courseid=$courseid (must be > 1)");
            return;
        }

        $coins = self::coins_for_badge($badgeid);
        if ($coins <= 0) {
            self::log($userid, $badgeid, 'debug', "award_coins: skipping, coins=$coins");
            return;
        }
        $cid = (int)$courseid;

        // Skip "already awarded" check for repeatable badges - they manage their own logic
        if (!self::is_repeatable_badge($badgeid) && self::coin_already_awarded($userid, $badgeid, $cid)) {
            self::log($userid, $badgeid, 'debug', "award_coins: coin_already_awarded returned true");
            return;
        }

        // For repeatable badges, check if this specific achievement has already been awarded
        $new_activities_to_store = [];
        if (self::is_repeatable_badge($badgeid) && !empty($contributing_activities)) {
            $pref_key = "ascend_badge_{$badgeid}_course_{$cid}_activities";
            $existing = get_user_preferences($pref_key, '', $userid);
            $existing_activities = $existing ? json_decode($existing, true) : [];
            if (!is_array($existing_activities)) {
                $existing_activities = [];
            }

            self::log($userid, $badgeid, 'debug', "award_coins: existing_activities=" . json_encode($existing_activities));

            // CRITICAL FIX: Verify that badges actually exist for these activities
            // If preferences exist but no badge records, preferences are stale (e.g., after plugin reinstall)
            if (!empty($existing_activities)) {
                // Check if ANY badge records exist for this badge+course combination
                $badge_exists = $DB->record_exists('local_ascend_rewards_coins', [
                    'userid' => $userid,
                    'badgeid' => $badgeid,
                    'courseid' => $cid,
                ]);

                if (!$badge_exists) {
                    // Preferences are stale - clear them and treat all activities as new
                    self::log($userid, $badgeid, 'debug', "award_coins: preferences exist but no badge records found - clearing stale data");
                    unset_user_preference($pref_key, $userid);
                    $existing_activities = [];
                }
            }

            // For repeatable badges, check if we have NEW activities to award
            // Only award if there's at least one activity not yet rewarded
            $new_activities = array_diff($contributing_activities, $existing_activities);

            self::log($userid, $badgeid, 'debug', "award_coins: new_activities=" . json_encode($new_activities));

            if (empty($new_activities)) {
                // No new activities - all have already been awarded
                self::log($userid, $badgeid, 'debug', "award_coins: no new activities, returning early");
                return;
            }

            // Prepare to store activities AFTER successful DB insert
            // Ensure we're merging arrays with string values, not numeric keys
            $all_awarded = array_unique(array_merge($existing_activities, $contributing_activities));
            // Re-index to ensure clean numeric array (not associative)
            $all_awarded_indexed = array_values($all_awarded);
            $new_activities_to_store = [
                'key' => $pref_key,
                'value' => json_encode($all_awarded_indexed),
            ];

            // Update the contributing_activities to only include NEW ones for the notification
            $contributing_activities = array_values($new_activities);
        }

        try {
            // Calculate XP independently of coins
            $xp = self::xp_for_badge($badgeid);

            // Apply XP multiplier if active
            $xp_multiplier = 1;
            $multiplier_end = (int)get_user_preferences('ascend_xp_multiplier_end', 0, $userid);
            if ($multiplier_end > time()) {
                $xp_multiplier = 2;
            }
            $xp *= $xp_multiplier;

            $insert_id = $DB->insert_record('local_ascend_rewards_coins', (object)[
                'userid'      => $userid,
                'badgeid'     => $badgeid,
                'coins'       => (int)$coins,
                'xp'          => $xp,
                'courseid'    => $cid,
                'timecreated' => time(),
            ], true);

            if (!$insert_id) {
                // Insert failed, don't store activities
                self::log($userid, $badgeid, 'error', "Failed to insert coins record for course $cid");
                return;
            }

            // ========== UPDATE XP TABLE (SEPARATE FROM COINS) ==========
            // XP is permanent and used for rankings. Coins can be spent.
            self::update_xp_table($userid, $cid, $xp);

            // Clear performance caches for this user
            \local_ascend_rewards\performance_cache::clear_user_cache($userid);
            \local_ascend_rewards\performance_cache::clear_leaderboard_cache();
        } catch (\Exception $e) {
            // Log the error and don't store activities
            self::log($userid, $badgeid, 'error', "Exception inserting coins: " . $e->getMessage());
            return;
        }

        // Only store activities tracking AFTER successful coin insertion
        if (!empty($new_activities_to_store)) {
            set_user_preference($new_activities_to_store['key'], $new_activities_to_store['value'], $userid);
        }

        set_user_preferences(['ascendassets_lastcoin' => time()], $userid);

        // Store activities in cache immediately for fast modal loading
        // Build metadata based on badge type
        $cache_metadata = [];
        if (self::is_repeatable_badge($badgeid)) {
            // For repeatable badges, build proper metadata with award numbers
            $pref_key = "ascend_badge_{$badgeid}_course_{$cid}_activities";
            $all_awarded = get_user_preferences($pref_key, '', $userid);
            $all_awarded_array = $all_awarded ? json_decode($all_awarded, true) : [];

            if (is_array($all_awarded_array) && count($all_awarded_array) > 0) {
                // Feedback Follower, Steady Improver, Tenacious Tiger: 1 activity per award
                $total_awards = count($all_awarded_array);
                foreach ($all_awarded_array as $idx => $name) {
                    $cache_metadata[] = [
                        'award_number' => $idx + 1,
                        'award_count' => $total_awards,
                        'single_activity' => true,
                    ];
                }

                // Cache all awarded activities with proper metadata
                try {
                    \local_ascend_rewards\badge_cache_helper::store_cache($userid, $cid, $badgeid, $all_awarded_array, $cache_metadata);
                } catch (\Exception $e) {
                    self::log($userid, $badgeid, 'warning', 'Cache storage failed: ' . $e->getMessage());
                }
            }
        } else {
            // Non-repeatable badges
            if (!empty($contributing_activities)) {
                foreach ($contributing_activities as $act) {
                    $cache_metadata[] = [
                        'award_number' => 1,
                        'award_count' => 1,
                    ];
                }
                try {
                    \local_ascend_rewards\badge_cache_helper::store_cache($userid, $cid, $badgeid, $contributing_activities, $cache_metadata);
                } catch (\Exception $e) {
                    self::log($userid, $badgeid, 'warning', 'Cache storage failed: ' . $e->getMessage());
                }
            }
        }

        // Add notification for badge award
        self::add_badge_notification($userid, $badgeid, $coins, $cid, $contributing_activities);
    }

    /**
     * Add a badge notification to user preferences for display on next page load
     */
    private static function add_badge_notification(int $userid, int $badgeid, int $coins, int $courseid = 0, array $contributing_activities = []): void {
        global $DB;

        // Get XP for this badge
        $xp = self::xp_for_badge($badgeid);

        // Badge names map - DEMO VERSION: Only 7 badges enabled
        $badge_names = [
            6  => 'Getting Started',
            5  => 'Halfway Hero',
            8  => 'Master Navigator',
            13 => 'Feedback Follower',
            15 => 'Steady Improver',
            14 => 'Tenacious Tiger',
            16 => 'Glory Guide',
        ];

        $badgename = $badge_names[$badgeid] ?? "Badge #{$badgeid}";

        // Demo: Only allow notifications for enabled badges
        if (!isset($badge_names[$badgeid])) {
            return; // Badge not in demo version, skip notification
        }

        // Get course name
        $coursename = 'Unknown Course';
        if ($courseid > 0) {
            $course = $DB->get_record('course', ['id' => $courseid], 'fullname');
            if ($course) {
                $coursename = $course->fullname;
            }
        }

        // Use the contributing activities passed from the badge check methods
        // If none provided, fall back to getting all completed activities
        $activities = [];
        if (!empty($contributing_activities)) {
            $activities = $contributing_activities;
        } else if ($courseid > 0) {
            $sql = "SELECT cm.id, cm.module as modid, m.name as modname, cm.instance
                      FROM {course_modules} cm
                      JOIN {modules} m ON m.id = cm.module
                      JOIN {course_modules_completion} cmc ON cmc.coursemoduleid = cm.id
                     WHERE cm.course = :courseid
                       AND cm.visible = 1
                       AND cm.deletioninprogress = 0
                       AND cmc.userid = :userid
                       AND cmc.completionstate IN (1, 2)
                  ORDER BY cmc.timemodified DESC";
            $completed = $DB->get_records_sql($sql, ['courseid' => $courseid, 'userid' => $userid]);

            foreach ($completed as $cm) {
                // Get the activity name
                $modtable = $cm->modname;
                if ($modinstance = $DB->get_record($modtable, ['id' => $cm->instance], 'name')) {
                    $activities[] = $modinstance->name;
                }
            }
        }

        // Calculate current rank after this badge
        $sql = "SELECT userid, COALESCE(SUM(coins), 0) AS total
                  FROM {local_ascend_rewards_coins}
                 GROUP BY userid
                 ORDER BY total DESC, userid ASC";
        $allranks = $DB->get_records_sql($sql);

        $current_rank = null;
        $total_users = count($allranks);
        $position = 1;
        foreach ($allranks as $row) {
            if ($row->userid === $userid) {
                $current_rank = $position;
                break;
            }
            $position++;
        }

        // Get previous rank from user preferences
        $prev_rank = get_user_preferences('ascend_last_rank', null, $userid);
        $rank_change = null;
        if ($prev_rank !== null && $current_rank !== null) {
            $rank_diff = $prev_rank - $current_rank; // Positive means improved (moved up)
            if ($rank_diff !== 0) {
                $rank_change = $rank_diff;
            }
        }

        // Save current rank for next time
        if ($current_rank !== null) {
            set_user_preference('ascend_last_rank', $current_rank, $userid);
        }

        // Check for level up (handle multiple level-ups in one badge award)
        $current_site_xp = (int)$DB->get_field('local_ascend_rewards_xp', 'xp', [
            'userid' => $userid,
            'courseid' => 0,
        ]);
        $new_level = min(10, (int)floor($current_site_xp / 1000));

        $prev_level = (int)get_user_preferences('ascend_current_level', 0, $userid);

        // If leveled up, queue notifications for ALL levels gained and award tokens
        if ($new_level > $prev_level && $new_level >= 1 && $new_level <= 10) {
            // Get existing level-up notifications
            $existing_levelups = get_user_preferences('ascend_pending_levelups', '', $userid);
            $levelup_notifications = $existing_levelups ? json_decode($existing_levelups, true) : [];
            if (!is_array($levelup_notifications)) {
                $levelup_notifications = [];
            }

            // Queue notification for each level gained and award tokens (e.g., if went from 0 to 3, queue 1, 2, 3)
            for ($level = max(1, $prev_level + 1); $level <= $new_level; $level++) {
                $levelup_notifications[] = [
                    'type' => 'levelup',
                    'level' => $level,
                    'timestamp' => time(),
                ];

                // Award 3 tokens per level-up
                // Get or create token record
                $token_record = $DB->get_record('local_ascend_rewards_level_tokens', ['userid' => $userid]);
                if ($token_record) {
                    // Update existing record - increment tokens_available
                    $token_record->tokens_available += 3;
                    $DB->update_record('local_ascend_rewards_level_tokens', $token_record);
                } else {
                    // Create new token record with 3 tokens
                    $DB->insert_record('local_ascend_rewards_level_tokens', (object)[
                        'userid' => $userid,
                        'tokens_available' => 3,
                        'tokens_used' => 0,
                        'timecreated' => time(),
                        'timemodified' => time(),
                    ]);
                }
            }

            // Update stored level
            set_user_preference('ascend_current_level', $new_level, $userid);

            // Keep only last 5 level-ups
            $levelup_notifications = array_slice($levelup_notifications, -5);

            // Save level-up notifications
            set_user_preference('ascend_pending_levelups', json_encode($levelup_notifications), $userid);
        } else if ($prev_level === 0 && $new_level >= 0) {
            // Initialize level tracking
            set_user_preference('ascend_current_level', $new_level, $userid);
        }

        // Badge descriptions
        $badge_descriptions = [
            'Getting Started'   => 'Awarded after completing the first activity in a course = 250',
            'Halfway Hero'      => 'Completed 50% of course activities = 550',
            'Master Navigator'  => 'Gold Badge - obtain 2 x badges in the Progress Based Badges Category = 700',
            'Feedback Follower' => 'Improve your grade in an activity = 200',
            'Steady Improver'   => 'After not passing an activity (on the first attempt) pass another activity first time = 300',
            'Tenacious Tiger'   => 'Improve your grade in 2 activities = 350',
            'Glory Guide'       => 'Gold Badge - Obtain any 2 badges in the Quality-Based & Growth/Improvement Badges Category = 600',
        ];

        // Badge categories
        $badge_categories = [
            'Getting Started'   => 'Progress-Based',
            'Halfway Hero'      => 'Progress-Based',
            'Master Navigator'  => 'Progress-Based',
            'Feedback Follower' => 'Quality & Growth',
            'Steady Improver'   => 'Quality & Growth',
            'Tenacious Tiger'   => 'Quality & Growth',
            'Glory Guide'       => 'Quality & Growth',
        ];

        // Limit activities to first 10 to prevent data size issues
        $limited_activities = array_slice($activities, 0, 10);

        $notification = [
            'badgeid' => $badgeid,
            'badgename' => $badgename,
            'coins' => $coins,
            'xp' => $xp,
            'coursename' => $coursename,
            'courseid' => $courseid,
            'activities' => $limited_activities,
            'rank' => $current_rank,
            'total_users' => $total_users,
            'rank_change' => $rank_change,
            'timestamp' => time(),
            'description' => $badge_descriptions[$badgename] ?? 'See badge rules.',
            'category' => $badge_categories[$badgename] ?? 'General',
        ];

        // Get existing notifications
        $existing = get_user_preferences('ascend_pending_notifications', '', $userid);
        $notifications = $existing ? json_decode($existing, true) : [];
        if (!is_array($notifications)) {
            $notifications = [];
        }

        // Add new notification
        $notifications[] = $notification;

        // Keep only last 3 notifications to prevent size issues
        $notifications = array_slice($notifications, -3);

        // Double-check total JSON size before saving (max ~1300 chars for safety)
        $json = json_encode($notifications);
        if (strlen($json) > 1300) {
            // If still too large, keep only the newest notification
            $notifications = array_slice($notifications, -1);
            $json = json_encode($notifications);
        }

        // Save back
        set_user_preference('ascend_pending_notifications', $json, $userid);
    }

    private static function gi_for_assign(int $instanceid, $grade_items = null): array {
        global $DB;

        if (isset(self::$gi_cache[$instanceid])) {
            return self::$gi_cache[$instanceid];
        }

        // Prefer provided grade_items (already filtered by course) when available.
        if ($grade_items) {
            if (is_array($grade_items)) {
                foreach ($grade_items as $gi) {
                    if ((int)$gi->iteminstance === (int)$instanceid) {
                        $grademax = isset($gi->grademax) ? (float)$gi->grademax : 100.0;
                        $pass     = (isset($gi->gradepass) && (float)$gi->gradepass > 0)
                            ? (float)$gi->gradepass
                            : ($grademax * 0.5);
                        return self::$gi_cache[$instanceid] = ['grademax' => $grademax, 'pass' => $pass];
                    }
                }
            } else if (is_object($grade_items) && (isset($grade_items->grademax) || isset($grade_items->gradepass))) {
                $grademax = isset($grade_items->grademax) ? (float)$grade_items->grademax : 100.0;
                $pass     = (isset($grade_items->gradepass) && (float)$grade_items->gradepass > 0)
                    ? (float)$grade_items->gradepass
                    : ($grademax * 0.5);
                return self::$gi_cache[$instanceid] = ['grademax' => $grademax, 'pass' => $pass];
            }
        }

        // Fallback: fetch ONE deterministic grade_item for this module instance.
        // Select the unique id first and LIMIT 1 to avoid "more than one record" and duplicate-key warnings.
        $records = $DB->get_records_sql("
            SELECT gi.id, gi.grademax, gi.gradepass
              FROM {grade_items} gi
             WHERE gi.itemtype = 'mod'
               AND gi.iteminstance = :instanceid
          ORDER BY gi.id DESC
        ", ['instanceid' => $instanceid], 0, 1);

        $gi = $records ? reset($records) : null;

        $grademax = $gi ? (float)$gi->grademax : 100.0;
        $pass     = ($gi && (float)$gi->gradepass > 0) ? (float)$gi->gradepass : ($grademax * 0.5);

        return self::$gi_cache[$instanceid] = ['grademax' => $grademax, 'pass' => $pass];
    }

    /* ==================== Course-scoped badge checks ==================== */

    private static function check_getting_started(int $userid, int $courseid, array $course_activities = [], array $course_completions = [], array $course_grades = []) {
        global $DB;

        // Get completed activities with names
        $completed = $DB->get_records_sql("
            SELECT cm.id, m.name AS modname, cm.instance
              FROM {course_modules_completion} cmc
              JOIN {course_modules} cm ON cm.id = cmc.coursemoduleid
              JOIN {modules} m ON m.id = cm.module
             WHERE cm.course = :c AND cmc.userid = :u AND cmc.completionstate >= 1
        ", ['u' => $userid, 'c' => $courseid]);

        $n = count($completed);
        $activity_names = [];

        foreach ($completed as $cm) {
            $modtable = $cm->modname;
            try {
                if ($modinstance = $DB->get_record($modtable, ['id' => $cm->instance], 'name')) {
                    $activity_names[] = $modinstance->name;
                }
            } catch (Exception $e) {
                continue;
            }
        }

        $debug = "Getting Started: completed={$n} (need >=1).";
        return ['result' => ($n >= 1), 'debug' => $debug, 'activities' => $activity_names];
    }

    private static function check_halfway_hero(int $userid, int $courseid, array $course_activities = [], array $course_completions = [], array $course_grades = []) {
        global $DB;
        $tot = self::safe_count(!empty($course_activities) ? $course_activities : $DB->get_records_sql("
            SELECT cm.id
              FROM {course_modules} cm
             WHERE cm.course = :courseid
               AND cm.completion > 0
        ", ['courseid' => $courseid]));
        if ($tot === 0) {
            return ['result' => false, 'debug' => "Halfway Hero: 0 activities in course $courseid."];
        }
        $completed = self::safe_count(!empty($course_completions) ? $course_completions : $DB->get_records_sql("
            SELECT cmc.id
              FROM {course_modules_completion} cmc
              JOIN {course_modules} cm ON cm.id = cmc.coursemoduleid
             WHERE cm.course = :c AND cmc.userid = :u AND cmc.completionstate >= 1
        ", ['c' => $courseid, 'u' => $userid]));
        $pct = round(100.0 * $completed / max(1, $tot), 2);
        $debug = "Halfway Hero: completed={$completed}/{$tot} = {$pct}% (need >=50%).";
        return ['result' => ($pct >= 50.0), 'debug' => $debug];
    }

    private static function check_feedback_follower(
        int $userid,
        int $courseid,
        array $course_activities = [],
        array $course_completions = [],
        array $course_grades = [],
        bool $skip_awarded_filter = false
    ) {
        global $DB;

        // Get already awarded activities for this badge (unless we're checking for revocation)
        $already_awarded = [];
        if (!$skip_awarded_filter) {
            $badgeid = 13; // Feedback Follower
            $pref_key = "ascend_badge_{$badgeid}_course_{$courseid}_activities";
            $existing = get_user_preferences($pref_key, '', $userid);
            $already_awarded = $existing ? json_decode($existing, true) : [];
            if (!is_array($already_awarded)) {
                $already_awarded = [];
            }
        }

        $activities = !empty($course_activities) ? $course_activities : $DB->get_records_sql("
            SELECT cm.id AS cmid, cm.instance, m.name AS modname
              FROM {course_modules} cm
              JOIN {modules} m ON m.id = cm.module
             WHERE cm.course = :courseid
               AND cm.completion > 0
        ", ['courseid' => $courseid]);
        if (!$activities) {
            return ['result' => false, 'debug' => "Feedback Follower: no activities in course $courseid."];
        }

        $improved = 0;
        $debug_details = [];
        $improved_activity_names = [];

        foreach ($activities as $a) {
            $has_improvement = false;

            // For quizzes, check quiz_attempts table directly
            if ($a->modname === 'quiz') {
                $attempts = $DB->get_records_sql("
                    SELECT qa.id, qa.sumgrades, qa.timefinish
                      FROM {quiz_attempts} qa
                     WHERE qa.quiz = :quizid
                       AND qa.userid = :userid
                       AND qa.state = 'finished'
                       AND qa.sumgrades IS NOT NULL
                  ORDER BY qa.timefinish ASC
                ", ['quizid' => $a->instance, 'userid' => $userid]);

                if (count($attempts) >= 2) {
                    $attempts_array = array_values($attempts);
                    $first_grade = (float)$attempts_array[0]->sumgrades;
                    $last_grade = (float)end($attempts_array)->sumgrades;

                    if ($last_grade > $first_grade) {
                        $improved++;
                        $has_improvement = true;
                        $debug_details[] = "quiz#{$a->instance}: improved from {$first_grade} to {$last_grade} (" . count($attempts) . " attempts)";
                    } else {
                        $debug_details[] = "quiz#{$a->instance}: no improvement ({$first_grade} to {$last_grade}, " . count($attempts) . " attempts)";
                    }
                } else {
                    $debug_details[] = "quiz#{$a->instance}: only " . count($attempts) . " attempt(s)";
                }
                continue;
            }

            // For other activities, check grade_grades
            $gradeitem = $DB->get_record('grade_items', [
                'itemtype' => 'mod',
                'itemmodule' => $a->modname,
                'iteminstance' => $a->instance,
            ], 'id, grademax, gradepass');

            if (!$gradeitem) {
                $debug_details[] = "{$a->modname}#{$a->instance}: no grade item";
                continue;
            }

            // Get all grade attempts ordered by time
            $all_grades = $DB->get_records_sql("
                SELECT gg.id, gg.finalgrade, gg.timemodified
                  FROM {grade_grades} gg
                 WHERE gg.itemid = :itemid AND gg.userid = :u AND gg.finalgrade IS NOT NULL
              ORDER BY gg.timemodified ASC, gg.id ASC
            ", ['itemid' => $gradeitem->id, 'u' => $userid]);

            if (count($all_grades) < 2) {
                $debug_details[] = "{$a->modname}#{$a->instance}: only " . count($all_grades) . " grade record(s)";
                continue;
            }

            $grades_array = array_values($all_grades);
            $first = $grades_array[0];
            $latest = end($grades_array);

            if ((float)$latest->finalgrade > (float)$first->finalgrade) {
                $improved++;
                $debug_details[] = "{$a->modname}#{$a->instance}: improved from {$first->finalgrade} to {$latest->finalgrade}";
            } else {
                $debug_details[] = "{$a->modname}#{$a->instance}: no improvement ({$first->finalgrade} to {$latest->finalgrade})";
            }
        }

        // Get activity names for activities where improvement was shown
        $activity_names = [];
        if ($improved > 0) {
            foreach ($activities as $a) {
                $modtable = $a->modname;
                try {
                    if ($modinstance = $DB->get_record($modtable, ['id' => $a->instance], 'name')) {
                        // Check if this activity had improvement (check both quiz and grade_grades)
                        $had_improvement = false;

                        if ($a->modname === 'quiz') {
                            $attempts = $DB->get_records_sql("
                                SELECT qa.id, qa.sumgrades
                                  FROM {quiz_attempts} qa
                                 WHERE qa.quiz = :quizid AND qa.userid = :userid AND qa.state = 'finished'
                              ORDER BY qa.timefinish ASC
                            ", ['quizid' => $a->instance, 'userid' => $userid]);
                            if (count($attempts) >= 2) {
                                $attempts_array = array_values($attempts);
                                if ((float)end($attempts_array)->sumgrades > (float)$attempts_array[0]->sumgrades) {
                                    $had_improvement = true;
                                }
                            }
                        } else {
                            $gradeitem = $DB->get_record('grade_items', [
                                'itemtype' => 'mod',
                                'itemmodule' => $a->modname,
                                'iteminstance' => $a->instance,
                            ], 'id');
                            if ($gradeitem) {
                                $all_grades = $DB->get_records_sql("
                                    SELECT gg.finalgrade FROM {grade_grades} gg
                                     WHERE gg.itemid = :itemid AND gg.userid = :u AND gg.finalgrade IS NOT NULL
                                  ORDER BY gg.timemodified ASC
                                ", ['itemid' => $gradeitem->id, 'u' => $userid]);
                                if (count($all_grades) >= 2) {
                                    $grades_array = array_values($all_grades);
                                    if ((float)end($grades_array)->finalgrade > (float)$grades_array[0]->finalgrade) {
                                        $had_improvement = true;
                                    }
                                }
                            }
                        }

                        // Only include if improved AND not already awarded
                        // Prefix with activity type to avoid name collisions
                        $activity_key = ucfirst($a->modname) . ": {$modinstance->name}";
                        if ($had_improvement && !in_array($activity_key, $already_awarded)) {
                            $activity_names[] = $activity_key;
                        }
                    }
                } catch (Exception $e) {
                    continue;
                }
            }
        }

        return [
            'result' => (count($activity_names) >= 1),
            'debug' => "Feedback Follower: new_improved_activities=" . count($activity_names) . " (need >=1). Details: " . implode('; ', $debug_details),
            'activities' => $activity_names,
        ];
    }

    private static function check_tenacious_tiger(int $userid, int $courseid, array $course_activities = [], array $course_completions = [], array $course_grades = []) {
        global $DB;
        $activities = !empty($course_activities) ? $course_activities : $DB->get_records_sql("
            SELECT cm.id AS cmid, cm.instance, m.name AS modname
              FROM {course_modules} cm
              JOIN {modules} m ON m.id = cm.module
             WHERE cm.course = :courseid
               AND cm.completion > 0
        ", ['courseid' => $courseid]);
        if (!$activities) {
            return ['result' => false, 'debug' => "Tenacious Tiger: no activities in course $courseid."];
        }

        $improved = 0;
        $improved_activities = [];

        foreach ($activities as $a) {
            $has_improvement = false;
            $activity_name = null;

            // Get activity name
            try {
                $modtable = $a->modname;
                if ($modinstance = $DB->get_record($modtable, ['id' => $a->instance], 'name')) {
                    $activity_name = $modinstance->name;
                }
            } catch (Exception $e) {
                continue;
            }

            // Check quizzes using quiz_attempts
            if ($a->modname === 'quiz') {
                $attempts = $DB->get_records_sql("
                    SELECT qa.id, qa.sumgrades
                      FROM {quiz_attempts} qa
                     WHERE qa.quiz = :quizid AND qa.userid = :userid AND qa.state = 'finished'
                       AND qa.sumgrades IS NOT NULL
                  ORDER BY qa.timefinish ASC
                ", ['quizid' => $a->instance, 'userid' => $userid]);

                if (count($attempts) >= 2) {
                    $attempts_array = array_values($attempts);
                    $first_grade = (float)$attempts_array[0]->sumgrades;
                    $last_grade = (float)end($attempts_array)->sumgrades;
                    if ($last_grade > $first_grade) {
                        $has_improvement = true;
                    }
                }
            }
            // Check assignments using assign_grades
            else if ($a->modname === 'assign') {
                $grades = $DB->get_records_sql("
                    SELECT ag.id, ag.grade, ag.timemodified
                      FROM {assign_grades} ag
                     WHERE ag.assignment = :assignid AND ag.userid = :userid
                       AND ag.grade IS NOT NULL AND ag.grade >= 0
                  ORDER BY ag.timemodified ASC
                ", ['assignid' => $a->instance, 'userid' => $userid]);

                if (count($grades) >= 2) {
                    $grades_array = array_values($grades);
                    $first_grade = (float)$grades_array[0]->grade;
                    $last_grade = (float)end($grades_array)->grade;
                    if ($last_grade > $first_grade) {
                        $has_improvement = true;
                    }
                }
            }
            // For other activity types, check grade_grades (if multiple records exist)
            else {
                $all_grades = $DB->get_records_sql("
                    SELECT gg.id, gg.finalgrade, gg.timemodified
                      FROM {grade_grades} gg
                      JOIN {grade_items} gi ON gi.id = gg.itemid
                     WHERE gi.itemtype = 'mod' AND gi.itemmodule = :modname AND gi.iteminstance = :instance
                       AND gg.userid = :u AND gg.finalgrade IS NOT NULL
                  ORDER BY gg.timemodified ASC
                ", ['modname' => $a->modname, 'instance' => $a->instance, 'u' => $userid]);

                if (count($all_grades) >= 2) {
                    $grades_array = array_values($all_grades);
                    $first_grade = (float)$grades_array[0]->finalgrade;
                    $last_grade = (float)end($grades_array)->finalgrade;
                    if ($last_grade > $first_grade) {
                        $has_improvement = true;
                    }
                }
            }

            if ($has_improvement && $activity_name) {
                $improved++;
                $improved_activities[] = ucfirst($a->modname) . ": {$activity_name}";
            }
        }

        $debug = "Tenacious Tiger: improved_activities={$improved} (need >=2 activities with improvement).";
        return [
            'result' => ($improved >= 2),
            'debug' => $debug,
            'activities' => $improved_activities,
        ];
    }

    private static function check_steady_improver(
        int $userid,
        int $courseid,
        array $course_activities = [],
        array $course_completions = [],
        array $course_grades = [],
        bool $skip_awarded_filter = false
    ) {
        global $DB;

        // Get already awarded activities for this badge (unless we're checking for revocation)
        $already_awarded = [];
        if (!$skip_awarded_filter) {
            $badgeid = 15; // Steady Improver
            $pref_key = "ascend_badge_{$badgeid}_course_{$courseid}_activities";
            $existing = get_user_preferences($pref_key, '', $userid);
            $already_awarded = $existing ? json_decode($existing, true) : [];
            if (!is_array($already_awarded)) {
                $already_awarded = [];
            }
        }

        $activities = !empty($course_activities) ? $course_activities : $DB->get_records_sql("
            SELECT cm.id AS cmid, cm.instance, m.name AS modname
              FROM {course_modules} cm
              JOIN {modules} m ON m.id = cm.module
             WHERE cm.course = :courseid
               AND cm.completion > 0
        ", ['courseid' => $courseid]);
        if (!$activities) {
            return ['result' => false, 'debug' => "Steady Improver: no activities in course $courseid."];
        }

        $qualifying_activities = [];

        // Simpler logic: Find any activity where student improved from failing to passing
        foreach ($activities as $a) {
            // Get activity name first
            $modtable = $a->modname;
            $activity_name = null;
            try {
                if ($modinstance = $DB->get_record($modtable, ['id' => $a->instance], 'name')) {
                    $activity_name = $modinstance->name;
                }
            } catch (Exception $e) {
                continue;
            }

            // Prefix with activity type to avoid name collisions
            $activity_key = ucfirst($a->modname) . ": {$activity_name}";

            // Skip if already awarded
            if (in_array($activity_key, $already_awarded)) {
                continue;
            }

            // Handle quizzes separately - check quiz_attempts table
            if ($a->modname === 'quiz') {
                // Get quiz passing grade
                $quiz = $DB->get_record('quiz', ['id' => $a->instance], 'grade, sumgrades');
                if (!$quiz) {
                    continue;
                }

                // Get the quiz grade item to find pass grade
                $gradeitem = $DB->get_record('grade_items', [
                    'itemtype' => 'mod',
                    'itemmodule' => 'quiz',
                    'iteminstance' => $a->instance,
                ], 'gradepass');

                if (!$gradeitem || $gradeitem->gradepass <= 0) {
                    continue;
                }

                // Get first and last quiz attempts
                $first_attempt = $DB->get_record_sql("
                    SELECT qa.sumgrades
                      FROM {quiz_attempts} qa
                     WHERE qa.quiz = :quizid AND qa.userid = :userid
                       AND qa.state = 'finished' AND qa.sumgrades IS NOT NULL
                  ORDER BY qa.timefinish ASC
                     LIMIT 1
                ", ['quizid' => $a->instance, 'userid' => $userid]);

                $last_attempt = $DB->get_record_sql("
                    SELECT qa.sumgrades
                      FROM {quiz_attempts} qa
                     WHERE qa.quiz = :quizid AND qa.userid = :userid
                       AND qa.state = 'finished' AND qa.sumgrades IS NOT NULL
                  ORDER BY qa.timefinish DESC
                     LIMIT 1
                ", ['quizid' => $a->instance, 'userid' => $userid]);

                if ($first_attempt && $last_attempt) {
                    // Convert quiz sumgrades to grade scale
                    $first_scaled = ($first_attempt->sumgrades / $quiz->sumgrades) * $quiz->grade;
                    $last_scaled = ($last_attempt->sumgrades / $quiz->sumgrades) * $quiz->grade;

                    // Award if failed first time and passed later
                    if ($first_scaled < $gradeitem->gradepass && $last_scaled >= $gradeitem->gradepass) {
                        $qualifying_activities[] = $activity_key;
                    }
                }
                continue;
            }

            // For other activities, check grade_grades table
            $gi = self::gi_for_assign((int)$a->instance, $course_grades ?? []);
            if ($gi['pass'] <= 0) {
                continue;
            }

            // Get first and last grades
            $first_grade = $DB->get_record_sql("
                SELECT gg.finalgrade
                  FROM {grade_grades} gg
                  JOIN {grade_items} gi ON gi.id = gg.itemid
                 WHERE gi.itemtype = 'mod' AND gi.itemmodule = :modname AND gi.iteminstance = :instance
                   AND gg.userid = :u AND gg.finalgrade IS NOT NULL
              ORDER BY gg.timemodified ASC
                 LIMIT 1
            ", ['modname' => $a->modname, 'instance' => $a->instance, 'u' => $userid]);

            $last_grade = $DB->get_record_sql("
                SELECT gg.finalgrade
                  FROM {grade_grades} gg
                  JOIN {grade_items} gi ON gi.id = gg.itemid
                 WHERE gi.itemtype = 'mod' AND gi.itemmodule = :modname AND gi.iteminstance = :instance
                   AND gg.userid = :u AND gg.finalgrade IS NOT NULL
              ORDER BY gg.timemodified DESC
                 LIMIT 1
            ", ['modname' => $a->modname, 'instance' => $a->instance, 'u' => $userid]);

            // Award if: started below pass grade AND ended at or above pass grade (showing steady improvement)
            if (
                $first_grade && $last_grade
                && (float)$first_grade->finalgrade < $gi['pass']
                && (float)$last_grade->finalgrade >= $gi['pass']
                && $activity_name
            ) {
                $qualifying_activities[] = $activity_key;
            }
        }

        return [
            'result' => (count($qualifying_activities) >= 1),
            'debug' => "Steady Improver: " . count($qualifying_activities) . " new activities where student improved from below pass to passing grade.",
            'activities' => $qualifying_activities,
        ];
    }

    /* ==================== Meta badge checks ==================== */
    /* (These site-wide helpers are kept for compatibility/logging; course-scoped meta awards are handled in check_all_badges()) */

    private static function check_glory_guide(int $userid) {
        $earned = self::count_earned($userid, [13, 14, 15], null);
        return ['result' => ($earned >= 2), 'debug' => "Glory Guide: has {$earned}/2 of (13,14,15)."];
    }

    private static function check_master_navigator(int $userid) {
        $earned = self::count_earned($userid, [6, 5], null);
        return ['result' => ($earned >= 2), 'debug' => "Master Navigator: has {$earned}/2 of (6,5)."];
    }

    /* ==================== End of Badge Awarder ==================== */
}
