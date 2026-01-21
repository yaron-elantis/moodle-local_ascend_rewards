<?php
namespace local_ascend_rewards;

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

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/performance_cache.php');

require_once($CFG->dirroot . '/grade/lib.php');
require_once($CFG->dirroot . '/mod/assign/locallib.php');
require_once($CFG->dirroot . '/lib/accesslib.php');
require_once($CFG->dirroot . '/lib/enrollib.php');

/**
 * Apex Rewards — Badge awarder (PLUGIN-ONLY)
 *
 * - Awards recorded only in local_ascend_rewards_coins (+ audit in local_ascend_rewards_badgerlog).
 * - Updated for any activity completion with specified badge logic and coin values.
 */
class badge_awarder {

    /** @var array<int,array{grademax:float, pass:float}> */
    private static $gi_cache = [];

    /**
     * Safe counter for arrays / Countable / stdClass / recordsets.
     */
    private static function safe_count($value): int {
        if (is_array($value) || $value instanceof \Countable) {
            return count($value);
        }
        if ($value instanceof \moodle_recordset) {
            $n = 0;
            foreach ($value as $_) { $n++; }
            $value->close();
            return $n;
        }
        if (is_object($value)) {
            foreach (['items','results','records','rows'] as $prop) {
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
        $coin_map = [
            6  => 50,   // Getting Started
            5  => 250,  // Halfway Hero
            8  => 600,  // Master Navigator (meta)
            13 => 100,  // Feedback Follower
            15 => 200,  // Steady Improver
            14 => 250,  // Tenacious Tiger
            16 => 600,  // Glory Guide (meta)
        ];
        return $coin_map[$badgeid] ?? 0;
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
            $course_record = $DB->get_record('local_ascend_xp', [
                'userid' => $userid,
                'courseid' => $courseid
            ]);
            
            if ($course_record) {
                $course_record->xp += $xp_earned;
                $course_record->timemodified = time();
                $DB->update_record('local_ascend_xp', $course_record);
            } else {
                $DB->insert_record('local_ascend_xp', (object)[
                    'userid' => $userid,
                    'courseid' => $courseid,
                    'xp' => $xp_earned,
                    'timemodified' => time()
                ]);
            }
            
            // Update SITE-WIDE XP (courseid = 0)
            $site_record = $DB->get_record('local_ascend_xp', [
                'userid' => $userid,
                'courseid' => 0
            ]);
            
            if ($site_record) {
                $site_record->xp += $xp_earned;
                $site_record->timemodified = time();
                $DB->update_record('local_ascend_xp', $site_record);
            } else {
                $DB->insert_record('local_ascend_xp', (object)[
                    'userid' => $userid,
                    'courseid' => 0,
                    'xp' => $xp_earned,
                    'timemodified' => time()
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
        if (!$user) { return ["User $userid not found."]; }
        /* Exclusions temporarily disabled so all users can be evaluated. */

        $results = [];
        $results[] = "Checking all badges for user ID: $userid";

        $lastcheck = 0; $skipcourses = false; // throttle disabled for now

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

        // DEMO VERSION: Only 9 badges enabled (kept badges only)
        $badges = [
            'getting_started'  => 6,
            'on_a_roll'        => 4,
            'halfway_hero'     => 5,
            'master_navigator' => 8,
            'feedback_follower'=> 13,
            'steady_improver'  => 15,
            'tenacious_tiger'  => 14,
            'glory_guide'      => 16,
            'level_up'         => 3, // If exists
        ];

        $meta_categories = [
            'master_navigator' => [6, 4, 5],  // Progress (Getting Started, On a Roll, Halfway Hero)
            'glory_guide'      => [13, 15, 14],// Quality & Growth (Feedback Follower, Steady Improver, Tenacious Tiger)
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
                
                // Skip On a Roll if <2 completions
                if ($method === 'on_a_roll' && $completion_count < 2) {
                    $results[] = "$badgename: requires 2+ completions, user has $completion_count in course $courseid.";
                    continue;
                }
                
                // Skip Halfway Hero/Mission Complete based on completion %
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
                if (in_array($method, ['tenacious_tiger', 'steady_improver', 'feedback_follower'], true)
                    && empty($course_grades[$courseid])) {
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
                if (!empty($check['debug'])) { $results[] = $check['debug']; }

                if ($check['result']) {
                    self::log($userid, $badgeid, 'success', "Badge conditions met for $badgename in course $courseid");
                    $contributing_activities = $check['activities'] ?? [];
                    $pair_key = in_array($badgeid, [4], true) ? ($check['pair_key'] ?? '') : ''; // On a Roll uses pair_key
                    self::award_coins($userid, $badgeid, $courseid, $contributing_activities, $pair_key);
                } else {
                    self::log($userid, $badgeid, 'debug', "course=$courseid :: ".($check['debug'] ?? "$badgename: not met"));
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
                $check = ['result' => ($earned >= 2), 'debug' => "$badgename: has {$earned}/2 of (".implode(',',$base_ids).") in course $courseid."];

                $results[] = $check['result']
                    ? "Badge ID $badgeid ($badgename) conditions met in course $courseid."
                    : "Badge ID $badgeid ($badgename) conditions not met in course $courseid.";
                if (!empty($check['debug'])) { $results[] = $check['debug']; }

                if ($check['result']) {
                    self::log($userid, $badgeid, 'success', "Badge conditions met for $badgename in course $courseid");
                // Meta badges - get names of the base badges that contributed
                    $meta_activities = [];
                    foreach ($base_ids as $base_badgeid) {
                        if (self::coin_already_awarded($userid, $base_badgeid, $courseid)) {
                            $badge_names_map = [
                                6 => 'Getting Started', 4 => 'On a Roll', 5 => 'Halfway Hero',
                                13 => 'Feedback Follower', 15 => 'Steady Improver', 14 => 'Tenacious Tiger'
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
            4,  // On a Roll (can be earned twice)
            9,  // Early Bird
            11, // Sharp Shooter
            13, // Feedback Follower
            14, // Tenacious Tiger
            15, // Steady Improver
            19, // High Flyer
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
            'courseid' => $courseid
        ]);
    }

    public static function coin_already_awarded(int $userid, int $badgeid, int $courseid): bool {
        global $DB;
        
        // Check if this is a repeatable badge
        if (self::is_repeatable_badge($badgeid)) {
            // For "On a Roll", allow up to 2 awards
            if ($badgeid === 4) {
                $count = self::badge_award_count($userid, $badgeid, $courseid);
                return $count >= 2;
            }
            // For other repeatable badges, never block (they can be earned unlimited times)
            return false;
        }
        
        // For non-repeatable badges, check if already awarded once
        return $DB->record_exists('local_ascend_rewards_coins', [
            'userid'   => $userid,
            'badgeid'  => $badgeid,
            'courseid' => $courseid
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
        list($inSql, $inParams) = $DB->get_in_or_equal($badgeids, SQL_PARAMS_QM);

        $sql = "
            SELECT COUNT(DISTINCT badgeid)
              FROM {local_ascend_rewards_coins}
             WHERE userid = ?
        ";

        $params = [$userid];

        if ($courseid !== null) {
            $sql .= " AND courseid = ?";
            $params[] = $courseid;
        }

        $sql .= " AND badgeid $inSql";

        $params = array_merge($params, $inParams);

        return (int)$DB->get_field_sql($sql, $params);
    }

    public static function award_coins(int $userid, int $badgeid, ?int $courseid, array $contributing_activities = [], string $pair_key = ''): void {
        global $DB, $CFG;
        
        self::log($userid, $badgeid, 'debug', "award_coins called: courseid=$courseid, activities=" . json_encode($contributing_activities) . ", pair_key=$pair_key");
        
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
                    'courseid' => $cid
                ]);
                
                if (!$badge_exists) {
                    // Preferences are stale - clear them and treat all activities as new
                    self::log($userid, $badgeid, 'debug', "award_coins: preferences exist but no badge records found - clearing stale data");
                    unset_user_preference($pref_key, $userid);
                    $existing_activities = [];
                }
            }
            
            // SPECIAL HANDLING FOR "ON A ROLL" (badgeid=4): Store as pipe-delimited pair keys using cmids
            if ($badgeid === 4 && count($contributing_activities) === 2) {
                // Use the cmid-based pair key passed from check_on_a_roll()
                // If not available, fall back to name-based key for compatibility
                $actual_pair_key = !empty($pair_key) ? $pair_key : implode('|', $contributing_activities);
                
                // Check if this exact pair has already been awarded
                if (in_array($actual_pair_key, $existing_activities)) {
                    self::log($userid, $badgeid, 'debug', "award_coins: pair already awarded (key=$actual_pair_key), returning early");
                    return;
                }
                
                // Add this pair to existing pairs
                $all_awarded = $existing_activities;
                $all_awarded[] = $actual_pair_key;
                
                $new_activities_to_store = [
                    'key' => $pref_key,
                    'value' => json_encode($all_awarded)
                ];
                
                // For On a Roll, keep contributing_activities as the pair (2 items) for notification/metadata
                // But don't change it - keep it as is for storage and notification purposes
            } elseif ($badgeid === 11 && count($contributing_activities) === 2) {
                // SPECIAL HANDLING FOR "SHARP SHOOTER" (badgeid=11): Store as pipe-delimited pair keys using cmids
                // Use the cmid-based pair key passed from check_sharp_shooter()
                // If not available, fall back to name-based key for compatibility
                $actual_pair_key = !empty($pair_key) ? $pair_key : implode('|', $contributing_activities);
                
                // Check if this exact pair has already been awarded
                if (in_array($actual_pair_key, $existing_activities)) {
                    self::log($userid, $badgeid, 'debug', "award_coins: Sharp Shooter pair already awarded (key=$actual_pair_key), returning early");
                    return;
                }
                
                // Add this pair to existing pairs
                $all_awarded = $existing_activities;
                $all_awarded[] = $actual_pair_key;
                
                $new_activities_to_store = [
                    'key' => $pref_key,
                    'value' => json_encode($all_awarded)
                ];
                
                // For Sharp Shooter, keep contributing_activities as the pair (2 items) for notification/metadata
            } else {
                // For other repeatable badges, check if we have NEW activities to award
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
                    'value' => json_encode($all_awarded_indexed)
                ];
                
                // Update the contributing_activities to only include NEW ones for the notification
                $contributing_activities = array_values($new_activities);
            }
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
                // ON A ROLL (badgeid=4): Store as pipe-delimited pair keys, display as 2-per-award
                if ($badgeid === 4) {
                    $pair_count = 0;
                    $total_pairs = count($all_awarded_array);
                    foreach ($all_awarded_array as $pair_key) {
                        // Split pipe-delimited pair
                        $pair_activities = explode('|', $pair_key);
                        if (count($pair_activities) === 2) {
                            $pair_count++;
                            // Add both activities with same award number and pair metadata
                            foreach ($pair_activities as $activity_name) {
                                $cache_metadata[] = [
                                    'award_number' => $pair_count,
                                    'award_count' => $total_pairs,
                                    'is_on_a_roll_pair' => true
                                ];
                            }
                        }
                    }
                } else {
                    // Early Bird, Feedback Follower, Steady Improver: 1 activity per award
                    $total_awards = count($all_awarded_array);
                    foreach ($all_awarded_array as $idx => $name) {
                        $cache_metadata[] = [
                            'award_number' => $idx + 1,
                            'award_count' => $total_awards,
                            'single_activity' => true
                        ];
                    }
                }
                
                // Cache all awarded activities with proper metadata
                try {
                    require_once($CFG->dirroot . '/local/ascend_rewards/classes/badge_cache_helper.php');
                    \local_ascend_rewards\badge_cache_helper::store_cache($userid, $cid, $badgeid, $all_awarded_array, $cache_metadata);
                } catch (\Exception $e) {
                    self::log($userid, $badgeid, 'warning', 'Cache storage failed: ' . $e->getMessage());
                }
            }
        } else {
            // Non-repeatable badges or High Flyer
            if (!empty($contributing_activities)) {
                foreach ($contributing_activities as $act) {
                    $cache_metadata[] = [
                        'award_number' => 1,
                        'award_count' => 1
                    ];
                }
                try {
                    require_once($CFG->dirroot . '/local/ascend_rewards/classes/badge_cache_helper.php');
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
        
        // Badge names map - DEMO VERSION: Only 9 badges enabled
        $badge_names = [
            6  => 'Getting Started',
            4  => 'On a Roll',
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
        } elseif ($courseid > 0) {
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
        $current_site_xp = (int)$DB->get_field('local_ascend_xp', 'xp', [
            'userid' => $userid,
            'courseid' => 0
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
                $token_record = $DB->get_record('local_ascend_level_tokens', ['userid' => $userid]);
                if ($token_record) {
                    // Update existing record - increment tokens_available
                    $token_record->tokens_available += 3;
                    $DB->update_record('local_ascend_level_tokens', $token_record);
                } else {
                    // Create new token record with 3 tokens
                    $DB->insert_record('local_ascend_level_tokens', (object)[
                        'userid' => $userid,
                        'tokens_available' => 3,
                        'tokens_used' => 0,
                        'timecreated' => time(),
                        'timemodified' => time()
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
            'Getting Started'   => 'Awarded after completing the first activity in a course = 50',
            'On a Roll'         => 'Awarded for completing 3 consecutive activities = 150',
            'Halfway Hero'      => 'Completed 50% of course activities = 250',
            'Master Navigator'  => 'Gold Badge - obtain 2 x badges in the Progress Based Badges Category = 600',
            'Feedback Follower' => 'Improve your grade in an activity = 100',
            'Steady Improver'   => 'After not passing an activity (on the first attempt) pass another activity first time = 200',
            'Tenacious Tiger'   => 'Improve your grade in 2 activities = 250',
            'Glory Guide'       => 'Gold Badge - Obtain any 2 badges in the Quality-Based & Growth/Improvement Badges Category = 600',
        ];
        
        // Badge categories
        $badge_categories = [
            'Getting Started'   => 'Progress-Based',
            'Halfway Hero'      => 'Progress-Based',
            'On a Roll'         => 'Progress-Based',
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
            } elseif (is_object($grade_items) && (isset($grade_items->grademax) || isset($grade_items->gradepass))) {
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
        
        $debug = "Getting Started: completed={$n} (need ≥1).";
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
        if ($tot === 0) { return ['result' => false, 'debug' => "Halfway Hero: 0 activities in course $courseid."]; }
        $completed = self::safe_count(!empty($course_completions) ? $course_completions : $DB->get_records_sql("
            SELECT cmc.id
              FROM {course_modules_completion} cmc
              JOIN {course_modules} cm ON cm.id = cmc.coursemoduleid
             WHERE cm.course = :c AND cmc.userid = :u AND cmc.completionstate >= 1
        ", ['c' => $courseid, 'u' => $userid]));
        $pct = round(100.0 * $completed / max(1, $tot), 2);
        return ['result' => ($pct >= 50.0), 'debug' => "Halfway Hero: completed={$completed}/{$tot} = {$pct}% (need ≥50%)."];
    }

    private static function check_early_bird(int $userid, int $courseid, array $course_activities = [], array $course_completions = [], array $course_grades = [], bool $skip_awarded_filter = false) {
        global $DB;
        
        // Get already awarded activities for this badge (unless we're checking for revocation)
        $already_awarded = [];
        if (!$skip_awarded_filter) {
            $badgeid = 9; // Early Bird
            $pref_key = "ascend_badge_{$badgeid}_course_{$courseid}_activities";
            $existing = get_user_preferences($pref_key, '', $userid);
            $already_awarded = $existing ? json_decode($existing, true) : [];
            if (!is_array($already_awarded)) {
                $already_awarded = [];
            }
        }
        
        // For ASSIGNMENTS: Check submission time (not completion time) - must be submitted 24hrs before deadline
        // This allows users to qualify even if grading is pending
        $early_assigns = $DB->get_records_sql("
            SELECT a.name, cm.id AS coursemoduleid, asub.timemodified
              FROM {assign} a
              JOIN {course_modules} cm ON cm.instance = a.id AND cm.module = (SELECT id FROM {modules} WHERE name = 'assign')
              JOIN {assign_submission} asub ON asub.assignment = a.id AND asub.userid = :u AND asub.status IN ('submitted', 'reopened')
              LEFT JOIN {assign_overrides} ao_u ON ao_u.assignid = a.id AND ao_u.userid = :u2
              LEFT JOIN (
                  SELECT ao_g.assignid, gm.userid, MAX(ao_g.duedate) AS group_due
                    FROM {groups_members} gm
                    JOIN {assign_overrides} ao_g ON ao_g.groupid = gm.groupid
                   GROUP BY ao_g.assignid, gm.userid
              ) gd ON gd.assignid = a.id AND gd.userid = :u3
             WHERE cm.course = :c
               AND asub.timemodified <= COALESCE(ao_u.duedate, gd.group_due, a.duedate) - 86400
               AND COALESCE(ao_u.duedate, gd.group_due, a.duedate) > 0
        ", ['c' => $courseid, 'u' => $userid, 'u2' => $userid, 'u3' => $userid]);
        
        // Get early completions for quizzes (using timeclose as the deadline) - 24hrs early
        $early_quizzes = $DB->get_records_sql("
            SELECT q.name, cmc.coursemoduleid
              FROM {course_modules_completion} cmc
              JOIN {course_modules} cm ON cm.id = cmc.coursemoduleid
              JOIN {quiz} q ON q.id = cm.instance AND cm.module = (SELECT id FROM {modules} WHERE name = 'quiz')
             WHERE cm.course = :c
               AND cmc.userid = :u
               AND cmc.completionstate >= 1
               AND q.timeclose > 0
               AND cmc.timemodified <= q.timeclose - 86400
        ", ['c' => $courseid, 'u' => $userid]);
        
        // Get early completions for SCORM packages (using timeclose) - 24hrs early
        $early_scorm = $DB->get_records_sql("
            SELECT s.name, cmc.coursemoduleid
              FROM {course_modules_completion} cmc
              JOIN {course_modules} cm ON cm.id = cmc.coursemoduleid
              JOIN {scorm} s ON s.id = cm.instance AND cm.module = (SELECT id FROM {modules} WHERE name = 'scorm')
             WHERE cm.course = :c
               AND cmc.userid = :u
               AND cmc.completionstate >= 1
               AND s.timeclose > 0
               AND cmc.timemodified <= s.timeclose - 86400
        ", ['c' => $courseid, 'u' => $userid]);
        
        // Get early completions for Lessons (using deadline) - 24hrs early
        $early_lessons = $DB->get_records_sql("
            SELECT l.name, cmc.coursemoduleid
              FROM {course_modules_completion} cmc
              JOIN {course_modules} cm ON cm.id = cmc.coursemoduleid
              JOIN {lesson} l ON l.id = cm.instance AND cm.module = (SELECT id FROM {modules} WHERE name = 'lesson')
             WHERE cm.course = :c
               AND cmc.userid = :u
               AND cmc.completionstate >= 1
               AND l.deadline > 0
               AND cmc.timemodified <= l.deadline - 86400
        ", ['c' => $courseid, 'u' => $userid]);
        
        // Get early completions for Choices (using timeclose) - 24hrs early
        $early_choices = $DB->get_records_sql("
            SELECT ch.name, cmc.coursemoduleid
              FROM {course_modules_completion} cmc
              JOIN {course_modules} cm ON cm.id = cmc.coursemoduleid
              JOIN {choice} ch ON ch.id = cm.instance AND cm.module = (SELECT id FROM {modules} WHERE name = 'choice')
             WHERE cm.course = :c
               AND cmc.userid = :u
               AND cmc.completionstate >= 1
               AND ch.timeclose > 0
               AND cmc.timemodified <= ch.timeclose - 86400
        ", ['c' => $courseid, 'u' => $userid]);
        
        // Get early completions for Feedback (using timeclose) - 24hrs early
        $early_feedback = $DB->get_records_sql("
            SELECT f.name, cmc.coursemoduleid
              FROM {course_modules_completion} cmc
              JOIN {course_modules} cm ON cm.id = cmc.coursemoduleid
              JOIN {feedback} f ON f.id = cm.instance AND cm.module = (SELECT id FROM {modules} WHERE name = 'feedback')
             WHERE cm.course = :c
               AND cmc.userid = :u
               AND cmc.completionstate >= 1
               AND f.timeclose > 0
               AND cmc.timemodified <= f.timeclose - 86400
        ", ['c' => $courseid, 'u' => $userid]);
        
        // Combine all early activities (prefix with type to avoid name collisions)
        $all_early = [];
        foreach ($early_assigns as $item) {
            $all_early[] = "Assign: {$item->name}";
        }
        foreach ($early_quizzes as $item) {
            $all_early[] = "Quiz: {$item->name}";
        }
        foreach ($early_scorm as $item) {
            $all_early[] = "Scorm: {$item->name}";
        }
        foreach ($early_lessons as $item) {
            $all_early[] = "Lesson: {$item->name}";
        }
        foreach ($early_choices as $item) {
            $all_early[] = "Choice: {$item->name}";
        }
        foreach ($early_feedback as $item) {
            $all_early[] = "Feedback: {$item->name}";
        }
        
        // Filter out already awarded activities and award once per NEW early completion
        $new_activities = array_diff($all_early, $already_awarded);
        $new_activities = array_values(array_unique($new_activities));
        
        $n = count($new_activities);
        $total = count($all_early);
        
        // Return the first new early activity (award once per activity, not all at once)
        $activities_to_award = [];
        if ($n > 0) {
            $activities_to_award = [$new_activities[0]]; // Award only the first new early completion
        }
        
        return [
            'result' => ($n >= 1), 
            'debug' => "Early Bird: {$n} new of {$total} total early completions (awarding 1 at a time).",
            'activities' => $activities_to_award
        ];
    }

    private static function check_deadline_burner(int $userid, ?int $courseid = null, array $course_activities = [], array $course_completions = [], array $course_grades = []) {
        global $DB;
        if (!empty($courseid)) {
            $completions = is_array($course_completions) ? $course_completions : (is_object($course_completions) ? [$course_completions] : []);
            if (is_object($course_completions)) {
                self::log($userid, 10, 'error', "course=$courseid :: Deadline Burner: course_completions is stdClass, converted to array");
            }
            $ok = self::qualifies_deadline_burner_any_activity($userid, $courseid, $completions);
            $dbg = $ok ? "Deadline Burner: eligible in course {$courseid}." : "Deadline Burner: NOT eligible in course {$courseid}.";
            return ['result' => $ok, 'debug' => $dbg];
        }
        $courses = enrol_get_users_courses($userid, true, 'id, visible');
        foreach ($courses as $cid => $c) {
            if ((int)$cid > 1 && (int)$c->visible === 1) {
                $completions = $DB->get_records_sql("
                    SELECT cmc.coursemoduleid AS cmid, cmc.timemodified
                      FROM {course_modules_completion} cmc
                      JOIN {course_modules} cm ON cm.id = cmc.coursemoduleid
                     WHERE cm.course = :courseid
                       AND cmc.userid = :userid
                       AND cmc.completionstate >= 1
                ", ['courseid' => $cid, 'userid' => $userid]) ?: [];
                if (self::qualifies_deadline_burner_any_activity($userid, (int)$cid, $completions)) {
                    return ['result' => true, 'debug' => "Deadline Burner: site-level eligible via course ID $cid."];
                }
            }
        }
        return ['result' => false, 'debug' => "Deadline Burner: site-level NOT eligible in any course."];
    }

    private static function check_sharp_shooter(int $userid, int $courseid, array $course_activities = [], array $course_completions = [], array $course_grades = [], bool $skip_awarded_filter = false) {
        global $DB;
        
        // Get already awarded activity PAIRS to exclude them
        $badgeid = 11; // Sharp Shooter
        $pref_key = "ascend_badge_{$badgeid}_course_{$courseid}_activities";
        $existing = get_user_preferences($pref_key, '', $userid);
        $already_awarded_pairs = $existing ? json_decode($existing, true) : [];
        if (!is_array($already_awarded_pairs)) {
            $already_awarded_pairs = [];
        }
        
        // Get all completed activities with deadlines, ordered by completion time
        $rows = $DB->get_records_sql("
            SELECT cmc.coursemoduleid AS cmid, cmc.timemodified, cm.instance, m.name AS modname
              FROM {course_modules_completion} cmc
              JOIN {course_modules} cm ON cm.id = cmc.coursemoduleid
              JOIN {modules} m ON m.id = cm.module
             WHERE cm.course = :c
               AND cmc.userid = :u
               AND cmc.completionstate >= 1
          ORDER BY cmc.timemodified ASC
        ", ['c' => $courseid, 'u' => $userid]);
        
        $all_activities = [];
        foreach ($rows as $r) {
            $modtable = $r->modname;
            $activity_name = null;
            $deadline = 0;
            
            // Get activity name
            try {
                if ($modinstance = $DB->get_record($modtable, ['id' => $r->instance], 'name')) {
                    $activity_name = ucfirst($r->modname) . ": {$modinstance->name}";
                }
            } catch (Exception $e) {
                continue;
            }
            
            // Get deadline for this activity
            if ($r->modname === 'assign') {
                $assign = $DB->get_record('assign', ['id' => $r->instance], 'duedate');
                $deadline = $assign ? (int)$assign->duedate : 0;
            } elseif ($r->modname === 'quiz') {
                $quiz = $DB->get_record('quiz', ['id' => $r->instance], 'timeclose');
                $deadline = $quiz ? (int)$quiz->timeclose : 0;
            }
            
            // Only include activities with deadlines
            if ($activity_name && $deadline > 0) {
                $all_activities[] = [
                    'name' => $activity_name,
                    'cmid' => $r->cmid,
                    'deadline' => $deadline
                ];
            }
        }
        
        // Find first qualifying 2-activity pair where BOTH are completed before deadline
        $qualifying_pair = [];
        if (count($all_activities) >= 2) {
            for ($i = 0; $i < count($all_activities) - 1; $i++) {
                $pair = [$all_activities[$i], $all_activities[$i + 1]];
                $pair_key = $pair[0]['cmid'] . '|' . $pair[1]['cmid'];
                
                // Check if both activities completed before their deadlines
                $pair_ontime = true;
                foreach ($pair as $activity) {
                    if ($activity['deadline'] <= 0) {
                        $pair_ontime = false;
                        break;
                    }
                }
                
                // If this pair hasn't been awarded yet, it qualifies
                if ($pair_ontime && !in_array($pair_key, $already_awarded_pairs)) {
                    $qualifying_pair = [
                        'display' => [$pair[0]['name'], $pair[1]['name']],
                        'key' => $pair_key
                    ];
                    break; // Return first new pair found
                }
            }
        }
        
        $has_qualifying_pair = !empty($qualifying_pair);
        
        // Return pair with both display names for storage
        return [
            'result' => $has_qualifying_pair,
            'debug' => "Sharp Shooter: " . ($has_qualifying_pair ? "Found new 2-activity on-time pair: {$qualifying_pair['display'][0]} + {$qualifying_pair['display'][1]}" : "No new consecutive on-time activity pairs (need 2 before deadline)"),
            'activities' => $has_qualifying_pair ? $qualifying_pair['display'] : [], // Return the display names
            'pair_key' => $has_qualifying_pair ? $qualifying_pair['key'] : '' // Return the unique pair key using cmids
        ];
    }

    private static function check_on_a_roll(int $userid, int $courseid, array $course_activities = [], array $course_completions = [], array $course_grades = []) {
        global $DB;
        
        // Get already awarded activity PAIRS to exclude them
        $badgeid = 4; // On a Roll
        $pref_key = "ascend_badge_{$badgeid}_course_{$courseid}_activities";
        $existing = get_user_preferences($pref_key, '', $userid);
        $already_awarded_pairs = $existing ? json_decode($existing, true) : [];
        if (!is_array($already_awarded_pairs)) {
            $already_awarded_pairs = [];
        }
        
        $rows = $DB->get_records_sql("
            SELECT cmc.coursemoduleid AS cmid, cmc.timemodified, cm.instance, m.name AS modname
              FROM {course_modules_completion} cmc
              JOIN {course_modules} cm ON cm.id = cmc.coursemoduleid
              JOIN {modules} m ON m.id = cm.module
             WHERE cm.course = :c
               AND cmc.userid = :u
               AND cmc.completionstate >= 1
          ORDER BY cmc.timemodified ASC
        ", ['c' => $courseid, 'u' => $userid]);
        
        $all_activities = [];
        foreach ($rows as $r) {
            $modtable = $r->modname;
            $activity_name = null;
            
            try {
                if ($modinstance = $DB->get_record($modtable, ['id' => $r->instance], 'name')) {
                    $activity_name = ucfirst($r->modname) . ": {$modinstance->name}";
                    // Store both the displayable name and the unique cmid
                    $all_activities[] = [
                        'name' => $activity_name,
                        'cmid' => $r->cmid
                    ];
                }
            } catch (Exception $e) {
                // Skip if can't get name
                continue;
            }
        }
        
        // Find all consecutive pairs (2 activities in a row)
        $qualifying_pair = [];
        if (count($all_activities) >= 2) {
            // Check each consecutive pair of activities
            for ($i = 0; $i < count($all_activities) - 1; $i++) {
                $pair = [$all_activities[$i], $all_activities[$i + 1]];
                // Create pair key using cmids to ensure uniqueness (different activity instances)
                $pair_key = $pair[0]['cmid'] . '|' . $pair[1]['cmid'];
                
                // If this pair hasn't been awarded yet, it qualifies
                if (!in_array($pair_key, $already_awarded_pairs)) {
                    // Return display names for the pair, but pair_key is for uniqueness
                    $qualifying_pair = [
                        'display' => [$pair[0]['name'], $pair[1]['name']],
                        'key' => $pair_key
                    ];
                    break; // Return first new pair found
                }
            }
        }
        
        $has_qualifying_pair = !empty($qualifying_pair);
        
        // Return pair with both display names for storage
        return [
            'result' => $has_qualifying_pair,
            'debug' => "On a Roll: " . ($has_qualifying_pair ? "Found new 2-activity pair: {$qualifying_pair['display'][0]} + {$qualifying_pair['display'][1]}" : "No new consecutive activity pairs (need 2 in a row)"),
            'activities' => $has_qualifying_pair ? $qualifying_pair['display'] : [], // Return the display names
            'pair_key' => $has_qualifying_pair ? $qualifying_pair['key'] : '' // Return the unique pair key using cmids
        ];
    }

    private static function check_feedback_follower(int $userid, int $courseid, array $course_activities = [], array $course_completions = [], array $course_grades = [], bool $skip_awarded_filter = false) {
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
                'iteminstance' => $a->instance
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
                                'iteminstance' => $a->instance
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
            'debug' => "Feedback Follower: new_improved_activities=" . count($activity_names) . " (need ≥1). Details: " . implode('; ', $debug_details),
            'activities' => $activity_names
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
        if (!$activities) { return ['result' => false, 'debug' => "Tenacious Tiger: no activities in course $courseid."]; }
        
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
            elseif ($a->modname === 'assign') {
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
        
        return [
            'result' => ($improved >= 2),
            'debug' => "Tenacious Tiger: improved_activities={$improved} (need ≥2 activities with improvement).",
            'activities' => $improved_activities
        ];
    }

    private static function check_steady_improver(int $userid, int $courseid, array $course_activities = [], array $course_completions = [], array $course_grades = [], bool $skip_awarded_filter = false) {
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
        if (!$activities) { return ['result' => false, 'debug' => "Steady Improver: no activities in course $courseid."]; }
        
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
                if (!$quiz) { continue; }
                
                // Get the quiz grade item to find pass grade
                $gradeitem = $DB->get_record('grade_items', [
                    'itemtype' => 'mod',
                    'itemmodule' => 'quiz',
                    'iteminstance' => $a->instance
                ], 'gradepass');
                
                if (!$gradeitem || $gradeitem->gradepass <= 0) { continue; }
                
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
            if ($gi['pass'] <= 0) { continue; }
            
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
            if ($first_grade && $last_grade 
                && (float)$first_grade->finalgrade < $gi['pass'] 
                && (float)$last_grade->finalgrade >= $gi['pass']
                && $activity_name) {
                $qualifying_activities[] = $activity_key;
            }
        }
        
        return [
            'result' => (count($qualifying_activities) >= 1),
            'debug' => "Steady Improver: " . count($qualifying_activities) . " new activities where student improved from below pass to passing grade.",
            'activities' => $qualifying_activities
        ];
    }

    private static function check_activity_ace(int $userid, int $courseid, array $course_activities = [], array $course_completions = [], array $course_grades = []) {
        global $DB;
        $activities = !empty($course_activities) ? $course_activities : $DB->get_records_sql("
            SELECT cm.id AS cmid, cm.instance, m.name AS modname
              FROM {course_modules} cm
              JOIN {modules} m ON m.id = cm.module
             WHERE cm.course = :courseid
               AND cm.completion > 0
               AND cm.visible = 1
               AND cm.deletioninprogress = 0
        ", ['courseid' => $courseid]);
        $tot = self::safe_count($activities);
        if ($tot === 0) { return ['result' => false, 'debug' => "Activity Ace: 0 activities in course $courseid."]; }
        
        $passed_first = 0;
        $checked = 0;
        $failed_activities = [];
        
        foreach ($activities as $a) {
            $modname = $a->modname;
            $instance = (int)$a->instance;
            
            // Get activity name for debugging
            try {
                $modrecord = $DB->get_record($modname, ['id' => $instance], 'name');
                $actname = $modrecord ? "$modname: {$modrecord->name}" : "$modname #$instance";
            } catch (Exception $e) {
                $actname = "$modname #$instance";
            }
            
            // Get pass grade for this activity
            $gi = $DB->get_record_sql("
                SELECT gi.grademax, gi.gradepass
                  FROM {grade_items} gi
                 WHERE gi.courseid = :cid AND gi.itemtype = 'mod' 
                   AND gi.itemmodule = :mod AND gi.iteminstance = :inst
            ", ['cid' => $courseid, 'mod' => $modname, 'inst' => $instance]);
            
            if (!$gi || $gi->grademax <= 0) {
                self::log($userid, 17, 'debug', "Activity Ace: $actname has no gradeable component, skipping");
                continue;
            }
            
            $pass_grade = ($gi->gradepass > 0) ? $gi->gradepass : ($gi->grademax * 0.5);
            $checked++;
            $first_attempt_pass = false;
            
            // Check attempts based on activity type
            if ($modname === 'quiz') {
                // Count quiz attempts to see if it's first try
                $attempts = $DB->count_records('quiz_attempts',
                    ['quiz' => $instance, 'userid' => $userid, 'state' => 'finished']
                );
                
                // Get actual grade from grade_grades (properly scaled)
                $grade = $DB->get_record_sql("
                    SELECT gg.finalgrade
                    FROM {grade_grades} gg
                    JOIN {grade_items} gi ON gi.id = gg.itemid
                    WHERE gi.itemtype = 'mod' AND gi.itemmodule = :mod AND gi.iteminstance = :inst
                    AND gg.userid = :u AND gg.finalgrade IS NOT NULL
                ", ['mod' => $modname, 'inst' => $instance, 'u' => $userid]);
                
                if ($attempts === 1 && $grade && $grade->finalgrade >= $pass_grade) {
                    $first_attempt_pass = true;
                    $passed_first++;
                } elseif ($attempts > 1) {
                    $failed_activities[] = "$actname (multiple attempts)";
                }
            } elseif ($modname === 'assign') {
                $submissions = $DB->get_records('assign_grades',
                    ['assignment' => $instance, 'userid' => $userid],
                    'timemodified ASC'
                );
                $graded_submissions = array_filter($submissions, fn($s) => $s->grade !== null && $s->grade >= 0);
                if (count($graded_submissions) >= 1) {
                    $first = reset($graded_submissions);
                    if ($first->grade >= $pass_grade) {
                        $first_attempt_pass = true;
                        $passed_first++;
                    } elseif (count($graded_submissions) > 1) {
                        $failed_activities[] = "$actname (multiple submissions)";
                    }
                }
            } else {
                // For other activity types, check grade_grades
                $grades = $DB->get_records_sql("
                    SELECT gg.finalgrade
                      FROM {grade_grades} gg
                      JOIN {grade_items} gi ON gi.id = gg.itemid
                     WHERE gi.itemtype = 'mod' AND gi.itemmodule = :mod AND gi.iteminstance = :inst
                       AND gg.userid = :u AND gg.finalgrade IS NOT NULL
                  ORDER BY gg.timemodified ASC
                ", ['mod' => $modname, 'inst' => $instance, 'u' => $userid]);
                if (count($grades) >= 1) {
                    $first = reset($grades);
                    if ($first->finalgrade >= $pass_grade) {
                        $first_attempt_pass = true;
                        $passed_first++;
                    } elseif (count($grades) > 1) {
                        $failed_activities[] = "$actname (multiple attempts)";
                    }
                }
            }
            
            if (!$first_attempt_pass && $checked > 0) {
                $failed_activities[] = $actname;
            }
        }
        
        $debug_msg = "Activity Ace: first-try passes={$passed_first}/{$checked} activities checked (need 100%).";
        if (!empty($failed_activities)) {
            $debug_msg .= " Failed: " . implode(', ', array_slice($failed_activities, 0, 3));
        }
        
        return [
            'result' => ($checked > 0 && $passed_first === $checked),
            'debug' => $debug_msg
        ];
    }

    private static function check_mission_complete(int $userid, int $courseid, array $course_activities = [], array $course_completions = [], array $course_grades = []) {
        global $DB;
        $tot = self::safe_count(!empty($course_activities) ? $course_activities : $DB->get_records_sql("
            SELECT cm.id
              FROM {course_modules} cm
             WHERE cm.course = :courseid
               AND cm.completion > 0
        ", ['courseid' => $courseid]));
        if ($tot === 0) { return ['result' => false, 'debug' => "Mission Complete: 0 activities in course $courseid."]; }
        $completed = 0;
        if (!empty($course_completions)) {
            if (is_array($course_completions)) {
                $completed = self::safe_count(array_unique(array_map(static function($r) { return (int)$r->cmid; }, $course_completions)));
            } elseif (is_object($course_completions)) {
                self::log($userid, 7, 'error', "Mission Complete: course_completions is stdClass, expected array");
                $completed = 1;
            }
        } else {
            $completed = (int)$DB->get_field_sql("
                SELECT COUNT(DISTINCT cmc.coursemoduleid)
                  FROM {course_modules_completion} cmc
                  JOIN {course_modules} cm ON cm.id = cmc.coursemoduleid
                 WHERE cm.course = :c
                   AND cmc.userid = :u
                   AND cmc.completionstate >= 1
            ", ['c' => $courseid, 'u' => $userid]);
        }
        return ['result' => ($completed === $tot),
                'debug' => "Mission Complete: completed={$completed}/{$tot} (need 100%)."];
    }

    public static function check_high_flyer(int $userid, int $courseid, array $course_activities = [], array $course_completions = [], array $course_grades = [], bool $skip_awarded_filter = false) {
        global $DB;
        
        // Count how many times badge already awarded
        $award_count = $DB->count_records('local_ascend_rewards_coins', [
            'userid' => $userid,
            'badgeid' => 19,
            'courseid' => $courseid
        ]);
        
        // Get all passed activities
        $rows = $DB->get_records_sql("
            SELECT cmc.coursemoduleid AS cmid, cmc.timemodified AS comp_time,
                   cm.instance, m.name AS modname,
                   gg.finalgrade, COALESCE(gi.gradepass, gi.grademax * 0.5) AS pass_grade
              FROM {course_modules_completion} cmc
              JOIN {course_modules} cm ON cm.id = cmc.coursemoduleid
              JOIN {modules} m ON m.id = cm.module
              LEFT JOIN {grade_items} gi ON gi.itemtype = 'mod' AND gi.itemmodule = m.name AND gi.iteminstance = cm.instance AND gi.courseid = :c
              LEFT JOIN {grade_grades} gg ON gg.itemid = gi.id AND gg.userid = :u
             WHERE cm.course = :c2
               AND cmc.userid = :u2
               AND cmc.completionstate >= 1
          ORDER BY cmc.timemodified ASC
        ", ['u' => $userid, 'u2' => $userid, 'c' => $courseid, 'c2' => $courseid]);
        
        $passed_activities = [];
        
        foreach ($rows as $r) {
            // Check if passed
            if ($r->finalgrade !== null && $r->pass_grade !== null) {
                $passed = (float)$r->finalgrade >= (float)$r->pass_grade;
                if ($passed) {
                    // Get activity name
                    try {
                        $modrecord = $DB->get_record($r->modname, ['id' => $r->instance], 'name');
                        if ($modrecord) {
                            $passed_activities[] = ucfirst($r->modname) . ": {$modrecord->name}";
                        }
                    } catch (Exception $e) {
                        // Skip if can't get name
                    }
                }
            }
        }
        
        $total_passed = count($passed_activities);
        
        // Calculate how many pairs of 2 can be awarded
        // Each award uses 2 activities: 1st award = activities 1-2, 2nd = 3-4, 3rd = 5-6, etc.
        $max_possible_awards = floor($total_passed / 2);
        
        // Check if user can get another award
        if ($award_count < $max_possible_awards) {
            // Return the next 2 activities to award
            $start_index = $award_count * 2;
            $activities_to_award = array_slice($passed_activities, $start_index, 2);
            
            return [
                'result' => true,
                'debug' => "High Flyer: Found 2 passed activities (award " . ($award_count + 1) . " of {$max_possible_awards} possible).",
                'activities' => $activities_to_award
            ];
        }
        
        return [
            'result' => false,
            'debug' => "High Flyer: Already awarded {$award_count} times. Need " . (($award_count + 1) * 2) . " passed activities for next award (have {$total_passed}).",
            'activities' => []
        ];
    }

    /* ==================== Meta badge checks ==================== */
    /* (These site-wide helpers are kept for compatibility/logging; course-scoped meta awards are handled in check_all_badges()) */

    private static function check_glory_guide(int $userid) {
        $earned = self::count_earned($userid, [13,14,15], null);
        return ['result' => ($earned >= 2), 'debug' => "Glory Guide: has {$earned}/2 of (13,14,15)."];
    }

    private static function check_master_navigator(int $userid) {
        $earned = self::count_earned($userid, [6,5,4], null);
        return ['result' => ($earned >= 2), 'debug' => "Master Navigator: has {$earned}/2 of (6,5,4)."];
    }

    private static function check_learning_legend(int $userid) {
        // REMOVED: Learning Legend badge was removed from demo
        return ['result' => false, 'debug' => "Learning Legend: badge removed"];
    }

    /* ==================== End of Badge Awarder ==================== */
}
