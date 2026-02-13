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
 * Weekly gameboard system for Ascend Rewards.
 *
 * Each badge earned gets its own gameboard. Meta badges allow 2 picks on their gameboard.
 *
 * @package   local_ascend_rewards
 * @copyright 2026 Elantis (Pty) LTD
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// phpcs:disable moodle.Files.MoodleInternal.MoodleInternalNotNeeded
defined('MOODLE_INTERNAL') || die();

// This class favors stable output over broad naming refactors.
// phpcs:disable moodle.NamingConventions.ValidVariableName.VariableNameUnderscore
// phpcs:disable moodle.Commenting.InlineComment.InvalidEndChar,moodle.Commenting.InlineComment.NotCapital
// phpcs:disable moodle.Commenting.MissingDocblock.Function
// phpcs:disable moodle.Files.MoodleInternal.MoodleInternalNotNeeded
// phpcs:disable moodle.WhiteSpace.WhiteSpaceInStrings.EndLine
// phpcs:disable moodle.Files.LineLength.MaxExceeded,moodle.Files.LineLength.TooLong
// phpcs:disable Squiz.PHP.CommentedOutCode.Found

/**
 * Gameboard class for weekly badge tracking.
 */
class local_ascend_rewards_gameboard {
    /**
     * Get the current week identifier (year-week format).
     *
     * @return string Week identifier in Y-W format
     */
    public static function get_current_week() {
        [$week_start, $week_end] = self::get_week_range();
        unset($week_end);
        return date('Ymd', $week_start);
    }

    /**
     * Get current week keys for backward compatibility.
     * New key is Friday-based Ymd; legacy key is ISO Y-W.
     *
     * @return array
     */
    private static function get_current_week_keys() {
        return [self::get_current_week(), date('Y-W')];
    }

    /**
     * Get week start and end timestamps (Friday to Thursday)
     */
    public static function get_week_range() {
        // Find the most recent Friday at 00:00:00
        $now = time();
        $dow = (int)date('N', $now); // 1=Mon, 5=Fri, 7=Sun

        if ($dow >= 5) {
            // Today is Fri, Sat, or Sun - use this week's Friday
            $days_since_friday = $dow - 5;
            $week_start = strtotime('today 00:00:00', $now) - ($days_since_friday * DAYSECS);
        } else {
            // Today is Mon-Thu - use last week's Friday
            $days_since_friday = $dow + 2; // Mon=3, Tue=4, Wed=5, Thu=6
            $week_start = strtotime('today 00:00:00', $now) - ($days_since_friday * DAYSECS);
        }

        $week_end = $week_start + (7 * DAYSECS) - 1; // Next Thursday 23:59:59

        return [$week_start, $week_end];
    }

    /**
     * Check if user has earned badges this week
     */
    public static function has_earned_badges_this_week($userid) {
        global $DB;

        [$week_start, $week_end] = self::get_week_range();

        $count = $DB->count_records_sql(
            "SELECT COUNT(*) FROM {local_ascend_rewards_coins}
             WHERE userid = :uid
               AND badgeid > 0
               AND timecreated >= :start
               AND timecreated <= :end",
            ['uid' => $userid, 'start' => $week_start, 'end' => $week_end]
        );

        return $count > 0;
    }

    /**
     * Get available picks for current week
     * Returns array with 'normal' and 'meta' badge counts
     */
    public static function get_available_picks($userid) {
        global $DB;

        [$week_start, $week_end] = self::get_week_range();
        $meta_badges = [8, 12, 16, 20];
        [$metainsql, $metaparams] = $DB->get_in_or_equal($meta_badges, SQL_PARAMS_NAMED);
        $params = [
            'uid' => $userid,
            'start' => $week_start,
            'end' => $week_end,
        ] + $metaparams;

        $counts = $DB->get_record_sql(
            "SELECT
                SUM(CASE WHEN badgeid {$metainsql} THEN 1 ELSE 0 END) AS metacount,
                COUNT(*) AS totalcount
             FROM {local_ascend_rewards_coins}
             WHERE userid = :uid
               AND badgeid > 0
               AND timecreated >= :start
               AND timecreated <= :end",
            $params
        );

        $metacount = (int)($counts->metacount ?? 0);
        $totalcount = (int)($counts->totalcount ?? 0);

        return [
            'normal' => max(0, $totalcount - $metacount),
            'meta' => $metacount,
        ];
    }

    /**
     * Get picks already made this week
     */
    public static function get_picks_made($userid) {
        global $DB;
        [$weeknew, $weeklegacy] = self::get_current_week_keys();
        [$weeksql, $weekparams] = $DB->get_in_or_equal([$weeknew, $weeklegacy], SQL_PARAMS_NAMED);
        $params = ['userid' => $userid] + $weekparams;
        $picks = $DB->get_records_sql(
            "SELECT *
             FROM {local_ascend_rewards_gameboard}
             WHERE userid = :userid
               AND week {$weeksql}
             ORDER BY position ASC",
            $params
        );

        return array_column(array_values($picks), 'position');
    }

    /**
     * Get remaining picks for the week
     */
    public static function get_remaining_picks($userid) {
        $available = self::get_available_picks($userid);
        $total_available = $available['normal'] + ($available['meta'] * 2);

        $picks_made = count(self::get_picks_made($userid));

        return max(0, $total_available - $picks_made);
    }

    /**
     * Get picks made for a specific badge's gameboard
     */
    public static function get_picks_for_badge($userid, $badgeid) {
        global $DB;
        [$weeknew, $weeklegacy] = self::get_current_week_keys();
        [$weeksql, $weekparams] = $DB->get_in_or_equal([$weeknew, $weeklegacy], SQL_PARAMS_NAMED);
        $params = ['userid' => $userid, 'badgeid' => $badgeid] + $weekparams;
        $picks = $DB->get_records_sql(
            "SELECT *
             FROM {local_ascend_rewards_gameboard}
             WHERE userid = :userid
               AND badgeid = :badgeid
               AND week {$weeksql}
             ORDER BY position ASC",
            $params
        );

        return $picks;
    }

    /**
     * Generate randomized card values for the week's gameboard
     * Uses week + userid seed for consistency
     */
    public static function get_card_values($userid) {
        $week = self::get_current_week();

        // Create seed from week + userid for randomization
        $seed = crc32($week . '_' . $userid);
        mt_srand($seed);

        // Generate 16 random values (50-1000 coins)
        $values = [];
        for ($i = 0; $i < 16; $i++) {
            $values[$i] = mt_rand(5, 100) * 10; // 50, 60, 70... up to 1000
        }

        // Reset random seed
        mt_srand();

        return $values;
    }

    /**
     * Get total gameboard coins earned (for inclusion in total coins)
     * Sums coins from local_ascend_rewards_gameboard table
     */
    public static function get_total_gameboard_coins($userid) {
        global $DB;

        $total = $DB->get_field_sql(
            "SELECT COALESCE(SUM(coins), 0) FROM {local_ascend_rewards_gameboard}
             WHERE userid = :uid",
            ['uid' => $userid]
        );

        return (int)$total;
    }

    /**
     * Badge layout for 4x4 grid
     * Meta badges (8,12,16,20) in right column
     */
    public static function get_badge_layout() {
        return [
            // Row 1: Progress badges + Master Navigator
            [6, 4, 5, 8],
            // Row 2: Timeliness badges + Time Tamer
            [9, 11, 10, 12],
            // Row 3: Quality badges + Glory Guide
            [13, 15, 14, 16],
            // Row 4: Mastery badges + Learning Legend
            [19, 17, 7, 20],
        ];
    }

    /**
     * Get badge names
     */
    public static function get_badge_names() {
        return [
            6 => 'Getting Started',
            4 => 'On a Roll',
            5 => 'Halfway Hero',
            8 => 'Master Navigator',
            9 => 'Early Bird',
            11 => 'Sharp Shooter',
            10 => 'Deadline Burner',
            12 => 'Time Tamer',
            13 => 'Feedback Follower',
            15 => 'Steady Improver',
            14 => 'Tenacious Tiger',
            16 => 'Glory Guide',
            19 => 'High Flyer',
            17 => 'Activity Ace',
            7 => 'Mission Complete',
            20 => 'Learning Legend',
        ];
    }

    /**
     * Make a pick on the weekly gameboard
     */
    public static function make_pick($userid, $position) {

        global $DB;
        $week = self::get_current_week();
        [$weeknew, $weeklegacy] = self::get_current_week_keys();

        // Check picks remaining
        $remaining = self::get_remaining_picks($userid);

        if ($remaining <= 0) {
            return ['success' => false, 'error' => 'No picks remaining'];
        }

        // Check if position already picked
        [$weeksql, $weekparams] = $DB->get_in_or_equal([$weeknew, $weeklegacy], SQL_PARAMS_NAMED);
        $params = ['userid' => $userid, 'position' => $position] + $weekparams;
        $already_picked = $DB->record_exists_sql(
            "SELECT 1
             FROM {local_ascend_rewards_gameboard}
             WHERE userid = :userid
               AND position = :position
               AND week {$weeksql}",
            $params
        );

        if ($already_picked) {
            return ['success' => false, 'error' => 'Already picked this card'];
        }

        // Get card value
        $values = self::get_card_values($userid);
        $coins = $values[$position] ?? 0;
        // Save the pick and mirror it into the coin ledger for totals/spending.
        $record = new stdClass();
        $record->userid = $userid;
        $record->badgeid = 0;
        $record->courseid = 0;
        $record->week = $week;
        $record->position = $position;
        $record->coins = $coins;
        $record->timecreated = time();
        $transaction = $DB->start_delegated_transaction();
        $DB->insert_record('local_ascend_rewards_gameboard', $record);
        $DB->insert_record('local_ascend_rewards_coins', (object)[
            'userid' => $userid, 'badgeid' => 0, 'courseid' => 0, 'coins' => $coins, 'xp' => 0, 'timecreated' => $record->timecreated,
        ]);
        $transaction->allow_commit();
        return [
            'success' => true, 'coins' => $coins, 'remaining' => $remaining - 1,
        ];
    }
}
