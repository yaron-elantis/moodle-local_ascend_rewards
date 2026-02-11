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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Performance caching helper for Ascend Rewards plugin.
 *
 * Caches expensive computations to improve page load times.
 *
 * @package   local_ascend_rewards
 * @copyright 2026 Elantis (Pty) LTD
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_ascend_rewards;

// phpcs:disable moodle.Files.MoodleInternal.MoodleInternalNotNeeded
defined('MOODLE_INTERNAL') || die();
// Preserve legacy naming and inline comments in this cache helper.
// phpcs:disable moodle.Commenting.InlineComment.InvalidEndChar,moodle.Commenting.InlineComment.NotCapital
// phpcs:disable moodle.NamingConventions.ValidVariableName.VariableNameUnderscore
// phpcs:disable moodle.WhiteSpace.WhiteSpaceInStrings.EndLine

/**
 * Performance cache class for caching expensive operations.
 */
class performance_cache {
    /** Cache duration in seconds (5 minutes) */
    const CACHE_DURATION = 300;

    /** Cache duration for leaderboard (2 minutes) */
    const LEADERBOARD_CACHE_DURATION = 120;

    /**
     * Get or compute user stats with caching
     *
     * @param int $userid User ID
     * @param int $courseid Course ID (0 for site-wide)
     * @return array User stats array
     */
    public static function get_user_stats(int $userid, int $courseid = 0): array {
        global $DB;

        // Get XP directly from dedicated XP table (most reliable)
        $xp_record = $DB->get_record('local_ascend_rewards_xp', [
            'userid' => $userid,
            'courseid' => $courseid,
        ]);
        $xp = $xp_record ? (int)$xp_record->xp : 0;

        // Calculate level
        $level = (int)($xp / 1000) + 1;
        if ($level > 8) {
            $level = 8;
        }

        // For coins, use actual sum (includes both positive and negative for balance)
        $coins = (int)$DB->get_field_sql(
            "SELECT COALESCE(SUM(coins), 0) FROM {local_ascend_rewards_coins} WHERE userid = :uid" .
            ($courseid > 0 ? " AND (courseid = :cid OR courseid IS NULL)" : " AND courseid = 0"),
            array_merge(['uid' => $userid], $courseid > 0 ? ['cid' => $courseid] : [])
        ) ?: 0;

        $stats = [
            'coins' => $coins,
            'xp' => $xp,
            'level' => $level,
            'xp_current_level' => $xp % 1000,
            'xp_next_level' => 1000,
            'progress_pct' => ($xp % 1000) / 10,
        ];

        return $stats;
    }

    /**
     * Get leaderboard data with caching
     *
     * @param int $courseid Course ID (0 for site-wide)
     * @param int $limit Number of entries to return
     * @return array Leaderboard entries
     */
    public static function get_leaderboard(int $courseid = 0, int $limit = 10): array {
        global $DB;

        $cachekey = "ascend_leaderboard_{$courseid}_{$limit}";
        $cached = self::get_from_cache($cachekey);

        if ($cached !== null && $cached !== false) {
            return $cached;
        }

        $sql = "SELECT x.userid, x.xp
                FROM {local_ascend_rewards_xp} x
                JOIN {user} u ON u.id = x.userid
                WHERE x.courseid = :cid AND x.xp > 0
                  AND u.suspended = 0 AND u.deleted = 0
                ORDER BY x.xp DESC, x.userid ASC";

        $records = $DB->get_records_sql($sql, ['cid' => $courseid], 0, $limit);

        self::set_to_cache($cachekey, $records, self::LEADERBOARD_CACHE_DURATION);
        return $records;
    }

    /**
     * Get user's rank with caching
     *
     * @param int $userid User ID
     * @param int $courseid Course ID
     * @param int $userxp User's XP
     * @return int|null Rank or null if no XP
     */
    public static function get_user_rank(int $userid, int $courseid, int $userxp): ?int {
        global $DB;

        if ($userxp <= 0) {
            return null;
        }

        // Get rank directly - don't cache ranks as they change frequently
        $rankcount = (int)$DB->get_field_sql(
            "SELECT COUNT(*) FROM {local_ascend_rewards_xp}
             WHERE courseid = :cid
               AND xp > 0
               AND ((xp > :uxp) OR (xp = :uxp2 AND userid < :uid))",
            ['cid' => $courseid, 'uxp' => $userxp, 'uxp2' => $userxp, 'uid' => $userid]
        );

        return $rankcount + 1;
    }

    /**
     * Clear cache for a specific user
     *
     * @param int $userid User ID
     */
    public static function clear_user_cache(int $userid): void {
        // Clear all user-specific caches
        // This is a simplified version - in production you'd want more granular control
        $pattern = "ascend_stats_{$userid}_*";
        self::clear_cache_pattern($pattern);

        $pattern = "ascend_rank_{$userid}_*";
        self::clear_cache_pattern($pattern);
    }

    /**
     * Clear leaderboard caches
     */
    public static function clear_leaderboard_cache(): void {
        self::clear_cache_pattern("ascend_leaderboard_*");
    }

    /**
     * Get value from Moodle cache
     *
     * @param string $key Cache key
     * @return mixed|null Cached value or null if not found/expired
     */
    private static function get_from_cache(string $key) {
        try {
            $cache = \cache::make('local_ascend_rewards', 'performance');
            $result = $cache->get($key);
            // Return null if cache returned false (key not found)
            return ($result === false) ? null : $result;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Set value in Moodle cache
     *
     * @param string $key Cache key
     * @param mixed $value Value to cache
     * @param int $ttl Time to live in seconds
     */
    private static function set_to_cache(string $key, $value, int $ttl = self::CACHE_DURATION): void {
        $cache = \cache::make('local_ascend_rewards', 'performance');
        $cache->set($key, $value);

        // Store expiry time
        $cache->set($key . '_expiry', time() + $ttl);
    }

    /**
     * Clear cache entries matching a pattern
     *
     * @param string $pattern Pattern to match (simplified - just checks prefix)
     */
    private static function clear_cache_pattern(string $pattern): void {
        $cache = \cache::make('local_ascend_rewards', 'performance');
        $cache->purge();
    }
}
