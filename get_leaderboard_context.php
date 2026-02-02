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
 * Get leaderboard context view (20 above, user, 20 below user's rank)
 *
 * @package    local_ascend_rewards
 * @copyright 2026 Elantis (Pty) LTD
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

require_login();
// Preserve naming and inline comment separators without altering behavior.
// phpcs:disable moodle.NamingConventions.ValidVariableName.VariableNameUnderscore
// phpcs:disable moodle.Commenting.InlineComment.InvalidEndChar,moodle.Commenting.InlineComment.NotCapital
// phpcs:disable moodle.Files.LineLength.MaxExceeded,moodle.Files.LineLength.TooLong
// phpcs:disable moodle.Commenting.MissingDocblock.Function

$courseid = optional_param('courseid', 0, PARAM_INT);

header('Content-Type: application/json');

global $DB, $USER;

// Get user's XP and rank first
if ($courseid > 0) {
    // Course-scoped leaderboard
    $sql = "SELECT FLOOR(SUM(coins)/2) AS xp
            FROM {local_ascend_rewards_coins}
            WHERE userid = :userid AND (courseid = :cid OR courseid IS NULL)";
    $user_xp = (int)$DB->get_field_sql($sql, ['userid' => $USER->id, 'cid' => $courseid]) ?: 0;

    // Get rank
    $rank_sql = "SELECT COUNT(DISTINCT c.userid) + 1 AS rank
                 FROM {local_ascend_rewards_coins} c
                 WHERE EXISTS (
                   SELECT 1 FROM {local_ascend_rewards_coins} c2
                   WHERE c2.userid = c.userid AND (c2.courseid = :cid1 OR c2.courseid IS NULL)
                 )
                 GROUP BY c.userid
                 HAVING FLOOR(SUM(c.coins)/2) > :userxp";
    $myrank = (int)$DB->get_field_sql($rank_sql, ['cid1' => $courseid, 'userxp' => $user_xp]) ?: 1;
} else {
    // Site-wide leaderboard
    $sql = "SELECT FLOOR(SUM(coins)/2) AS xp
            FROM {local_ascend_rewards_coins}
            WHERE userid = :userid";
    $user_xp = (int)$DB->get_field_sql($sql, ['userid' => $USER->id]) ?: 0;

    // Get rank
    $rank_sql = "SELECT COUNT(DISTINCT userid) + 1 AS rank
                 FROM (
                   SELECT userid, FLOOR(SUM(coins)/2) AS xp
                   FROM {local_ascend_rewards_coins}
                   GROUP BY userid
                   HAVING FLOOR(SUM(coins)/2) > :userxp
                 ) subq";
    $myrank = (int)$DB->get_field_sql($rank_sql, ['userxp' => $user_xp]) ?: 1;
}

// Calculate range: 20 above and 20 below
$start_rank = max(1, $myrank - 20);
$end_rank = $myrank + 20;

// Get leaderboard data for this range
if ($courseid > 0) {
    $sql = "SELECT userid, FLOOR(SUM(coins)/2) AS xp
            FROM {local_ascend_rewards_coins}
            WHERE courseid = :cid OR courseid IS NULL
            GROUP BY userid
            HAVING FLOOR(SUM(coins)/2) > 0
            ORDER BY xp DESC, userid ASC";
    $all_users = $DB->get_records_sql($sql, ['cid' => $courseid]);
} else {
    $sql = "SELECT userid, FLOOR(SUM(coins)/2) AS xp
            FROM {local_ascend_rewards_coins}
            GROUP BY userid
            HAVING FLOOR(SUM(coins)/2) > 0
            ORDER BY xp DESC, userid ASC";
    $all_users = $DB->get_records_sql($sql);
}

// Convert to array with ranks
$ranked = [];
$rank = 1;
foreach ($all_users as $row) {
    $ranked[] = [
        'userid' => (int)$row->userid,
        'xp' => (int)$row->xp,
        'rank' => $rank++,
        'is_current_user' => ((int)$row->userid === (int)$USER->id),
    ];
}

// Filter to range
$context_users = array_filter($ranked, function ($u) use ($start_rank, $end_rank) {
    return $u['rank'] >= $start_rank && $u['rank'] <= $end_rank;
});

// Re-index array
$context_users = array_values($context_users);

// Medal helper
function get_medal_for_rank($rank) {
    if ($rank === 1) {
        return '1st';
    }
    if ($rank === 2) {
        return '2nd';
    }
    if ($rank === 3) {
        return '3rd';
    }
    return '#' . $rank;
}

// Add medal to each
foreach ($context_users as &$u) {
    $u['medal'] = get_medal_for_rank($u['rank']);
}

echo json_encode([
    'success' => true,
    'users' => $context_users,
    'myrank' => $myrank,
    'start_rank' => $start_rank,
    'end_rank' => $end_rank,
    'total_users' => count($ranked),
]);
