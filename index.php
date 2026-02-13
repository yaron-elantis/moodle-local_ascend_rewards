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
 * Main dashboard page for Ascend Rewards.
 *
 * Displays user's coin balance, badges, rank, course progress, and gamification elements.
 *
 * @package   local_ascend_rewards
 * @copyright 2026 Elantis (Pty) LTD
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
defined('MOODLE_INTERNAL') || die();
require_login();

// This script renders large inline HTML/CSS blocks. Avoid auto-reflow that could
// change rendering by suppressing line length, variable naming, and indent sniffs.
// phpcs:disable moodle.Files.LineLength.MaxExceeded,moodle.Files.LineLength.TooLong
// phpcs:disable moodle.NamingConventions.ValidVariableName.VariableNameUnderscore
// phpcs:disable Generic.WhiteSpace.ScopeIndent.Incorrect,Generic.WhiteSpace.ScopeIndent.IncorrectExact
// phpcs:disable moodle.Commenting.MissingDocblock.File
// phpcs:disable moodle.Commenting.InlineComment.InvalidEndChar,moodle.Commenting.InlineComment.NotCapital
// phpcs:disable moodle.Commenting.InlineComment.DocBlock
// phpcs:disable Squiz.WhiteSpace.SuperfluousWhitespace.EndLine,Squiz.WhiteSpace.SuperfluousWhitespace.EmptyLines
// phpcs:disable Squiz.WhiteSpace.ControlStructureSpacing.SpacingBeforeClose
// phpcs:disable Squiz.ControlStructures.ControlSignature.SpaceAfterCloseParenthesis,Squiz.ControlStructures.ControlSignature.SpaceAfterKeyword
// phpcs:disable Squiz.ControlStructures.ControlSignature.NewlineAfterOpenBrace,Squiz.ControlStructures.ElseIfDeclaration.NotAllowed
// phpcs:disable Squiz.WhiteSpace.ScopeClosingBrace.ContentBefore,Squiz.WhiteSpace.ScopeClosingBrace.Indent
// phpcs:disable Squiz.WhiteSpace.OperatorSpacing.NoSpaceBefore,Squiz.WhiteSpace.OperatorSpacing.NoSpaceAfter
// phpcs:disable Generic.ControlStructures.InlineControlStructure.NotAllowed,Generic.CodeAnalysis.EmptyStatement.DetectedCatch
// phpcs:disable PSR12.Operators.OperatorSpacing.NoSpaceBefore,PSR12.Operators.OperatorSpacing.NoSpaceAfter
// phpcs:disable PSR2.Methods.FunctionCallSignature.MultipleArguments,Squiz.Functions.MultiLineFunctionDeclaration.Indent
// phpcs:disable NormalizedArrays.Arrays.CommaAfterLast.MissingMultiLine,Generic.Arrays.DisallowLongArraySyntax.Found
// phpcs:disable Universal.Lists.DisallowLongListSyntax.Found,Generic.Formatting.DisallowMultipleStatements.SameLine
// phpcs:disable moodle.WhiteSpace.WhiteSpaceInStrings.EndLine,moodle.Commenting.MissingDocblock.Function
// phpcs:disable moodle.PHP.IncludingFile.UseRequire,Squiz.PHP.CommentedOutCode.Found

global $DB, $USER, $CFG, $OUTPUT, $PAGE;

$context = context_system::instance();
$PAGE->set_context($context);
require_capability('local/ascend_rewards:view', $context);

$avatar_circular_dir = '/local/ascend_rewards/pix/Avatars/circular%20avatars';

// AJAX: leaderboard context
$apex_action = optional_param('apex_action', '', PARAM_ALPHANUMEXT);
if ($apex_action === 'get_leaderboard_context') {
    header('Content-Type: application/json');
    if (!confirm_sesskey()) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'error' => get_string('invalidsesskey', 'error'),
        ]);
        exit;
    }
    $neighbors = optional_param('neighbors', 3, PARAM_INT);
    $courseid_param = optional_param('courseid', 0, PARAM_INT);

    $xp_cid = ($courseid_param > 0) ? $courseid_param : 0;

    $sql = "SELECT x.userid, x.xp
            FROM {local_ascend_rewards_xp} x
            JOIN {user} u ON u.id = x.userid
            WHERE x.courseid = :cid AND x.xp > 0
              AND u.suspended = 0 AND u.deleted = 0
            ORDER BY x.xp DESC, x.userid ASC";

    $rows = $DB->get_records_sql($sql, ['cid' => $xp_cid]);
    $ranked = [];
    $r = 1;
    foreach ($rows as $row) {
        $ranked[] = [
            'userid' => (int)$row->userid,
            'xp' => (int)$row->xp,
            'rank' => $r,
            'medal' => local_ascend_rewards_medal_for_place($r),
            'is_current_user' => ($row->userid == $USER->id),
        ];
        $r++;
    }

    $total = count($ranked);
    $myrank = null;
    foreach ($ranked as $r) {
        if ($r['userid'] == $USER->id) {
            $myrank = $r['rank'];
            break;
        }
    }

    // Build users array with neighbors
    $users_array = [];
    if ($myrank !== null && $total > 1) {
        $start = max(0, $myrank - 1 - $neighbors);
        $end = min($total - 1, $myrank - 1 + $neighbors);
        $users_array = array_slice($ranked, $start, $end - $start + 1);
        $start_rank = $start + 1;
        $end_rank = min($end + 1, $total);
    } else if ($myrank !== null) {
        $users_array = array_filter($ranked, function ($r) use ($myrank) {
            return $r['rank'] == $myrank;
        });
        $start_rank = $myrank;
        $end_rank = $myrank;
    }

    $result = [
        'success' => true,
        'users' => $users_array,
        'start_rank' => isset($start_rank) ? $start_rank : 0,
        'end_rank' => isset($end_rank) ? $end_rank : 0,
        'total_users' => $total,
        'myrank' => $myrank,
    ];

    echo json_encode($result);
    exit;
}

/** ------------------------------------------------------------------------
 *  HELPER FUNCTIONS
 *  --------------------------------------------------------------------- */

/**
 * Convert a moodle_url to a string URL for template context.
 *
 * @param moodle_url $url The URL object
 * @param string $alt Alt text for the image (unused, for semantic clarity)
 * @return string The URL as a string
 */
function local_ascend_rewards_img(moodle_url $url, string $alt = ''): string {
    return $url->out(false);
}

/**
 * Get medal emoji/label for leaderboard position.
 *
 * @param int $place The rank position (1-indexed)
 * @return string Medal emoji or position number
 */
function local_ascend_rewards_medal_for_place(int $place): string {
    if ($place === 1) {
        return '🥇';
    } elseif ($place === 2) {
        return '🥈';
    } elseif ($place === 3) {
        return '🥉';
    }
    return (string)$place;
}

/**
 * Format a Unix timestamp as a readable date string.
 *
 * @param int $timestamp Unix timestamp
 * @return string Formatted date (e.g., "Jan 15, 2026")
 */
function local_ascend_rewards_fmt_date(int $timestamp): string {
    return date('M d, Y', $timestamp);
}

/** ------------------------------------------------------------------------
 *  PIX / BADGE CONFIG
 *  --------------------------------------------------------------------- */
$coin_fallback  = new moodle_url('/local/ascend_rewards/pix/ascend_coin_main.png');
$stack_fallback = new moodle_url('/local/ascend_rewards/pix/ascend_assets_stack.png');

$coinimgurl  = local_ascend_rewards_img($coin_fallback, 'ascend_coin_main');
$stackimgurl = local_ascend_rewards_img($stack_fallback, 'ascend_assets_stack');

$medal_gold_url   = local_ascend_rewards_img(new moodle_url('/local/ascend_rewards/pix/medal_gold.png'), 'medal_gold');
$medal_silver_url = local_ascend_rewards_img(new moodle_url('/local/ascend_rewards/pix/medal_silver.png'), 'medal_silver');
$medal_bronze_url = local_ascend_rewards_img(new moodle_url('/local/ascend_rewards/pix/medal_bronze.png'), 'medal_bronze');

$pixbase = new moodle_url('/local/ascend_rewards/pix');

// Section header icons
$icon_leaderboard_url      = local_ascend_rewards_img(new moodle_url('/local/ascend_rewards/pix/leaderboard.png'), 'leaderboard');
$icon_badges_course_url   = local_ascend_rewards_img(new moodle_url('/local/ascend_rewards/pix/badges_course.png'), 'badges_course');
$icon_challenges_url      = local_ascend_rewards_img(new moodle_url('/local/ascend_rewards/pix/mystery_box.png'), 'challenge');
$icon_journey_url         = local_ascend_rewards_img(new moodle_url('/local/ascend_rewards/pix/journey.png'), 'journey');

/**
 * Badge -> icon filename mapping.
 * PNG images are in the root pix folder with lowercase underscore filenames.
 */
// DEMO VERSION: Only 7 active badges.
$badge_images = [
    'Getting Started'   => 'getting_started.png',
    'Halfway Hero'      => 'halfway_hero.png',
    'Master Navigator'  => 'master_navigator.png',
    'Feedback Follower' => 'feedback_follower.png',
    'Tenacious Tiger'   => 'tenacious_tiger.png',
    'Steady Improver'   => 'steady_improver.png',
    'Glory Guide'       => 'glory_guide.png',
];

/**
 * Badge definitions (name -> badgeid).
 */
// DEMO VERSION: Only 7 active badges.
$badge_definitions = [
    'Getting Started'   => 6,
    'Halfway Hero'      => 5,
    'Master Navigator'  => 8,
    'Feedback Follower' => 13,
    'Steady Improver'   => 15,
    'Tenacious Tiger'   => 14,
    'Glory Guide'       => 16,
];

/**
 * Badge descriptions for modal / tooltips.
 */
// DEMO VERSION: Only 7 active badges.
$badge_descriptions = [
    'Getting Started'   => get_string('badge_desc_getting_started', 'local_ascend_rewards'),
    'Halfway Hero'      => get_string('badge_desc_halfway_hero', 'local_ascend_rewards'),
    'Master Navigator'  => get_string('badge_desc_master_navigator', 'local_ascend_rewards'),
    'Feedback Follower' => get_string('badge_desc_feedback_follower', 'local_ascend_rewards'),
    'Steady Improver'   => get_string('badge_desc_steady_improver', 'local_ascend_rewards'),
    'Tenacious Tiger'   => get_string('badge_desc_tenacious_tiger', 'local_ascend_rewards'),
    'Glory Guide'       => get_string('badge_desc_glory_guide', 'local_ascend_rewards'),
];

/**
 * Normalize a badge name for use as a map key.
 *
 * @param string $badgename The badge name to normalize
 * @return string The normalized badge name
 */
function local_ascend_rewards_normalize_badge_name(string $badgename): string {
    // Return the name as-is; badge names from $badge_definitions are already properly formatted
    // This function exists as a safety layer and for future extensibility
    return trim($badgename);
}

/**
 * Resolve a badge icon URL.
 */
function local_ascend_rewards_badge_icon_url(string $badgename, array $map, moodle_url $pixbase, moodle_url $fallback): moodle_url {
    $name = local_ascend_rewards_normalize_badge_name($badgename);
    $filename = $map[$name] ?? null;
    if ($filename) {
        return new moodle_url('/local/ascend_rewards/pix/' . $filename);
    }
    return $fallback;
}

/** Get the course filter parameter from the URL */
$courseid_param = optional_param('courseid', '', PARAM_INT);
$courseid = (!empty($courseid_param) && $courseid_param > 0) ? (int)$courseid_param : null;

$urlparams = [];
if (!is_null($courseid)) {
    $urlparams['courseid'] = $courseid;
}
$PAGE->set_url(new moodle_url('/local/ascend_rewards/index.php', $urlparams));

/** --------
 *  USER PARAMS / WHERE FRAGMENTS
 *  --------------------------------------------------------------------- */
$userparam = ['uid' => $USER->id];

$where_course_c   = is_null($courseid) ? "" : " AND c.courseid = :cid ";
$where_course_cns = is_null($courseid) ? "" : " AND cns.courseid = :cid ";
$where_course_lb  = is_null($courseid) ? "" : " AND courseid = :cid";

if (!is_null($courseid)) {
    $userparam['cid'] = $courseid;
}

/** ------------------------------------------------------------------------
 *  TOTAL COINS (GLOBAL) & BALANCE PER COURSE - Including Gameboard Coins
 *  --------------------------------------------------------------------- */
// All coins are now in the unified local_ascend_rewards_coins table
// This includes badge coins, gameboard coins, and any other coin earnings
// Count only positive coins (earned), not negative coins (spent)
$totalcoins = (int)$DB->get_field_sql(
    "SELECT COALESCE(SUM(coins), 0)
       FROM {local_ascend_rewards_coins}
      WHERE userid = :uid AND coins > 0",
    ['uid' => $USER->id]
);

// Get user's actual spendable balance (positive earnings minus spending)
$coin_balance = (int)$DB->get_field_sql(
    "SELECT COALESCE(SUM(coins), 0)
       FROM {local_ascend_rewards_coins}
      WHERE userid = :uid",
    ['uid' => $USER->id]
);

// If a test-only coin offset is set (for testing), add it to the displayed balance.
// This does NOT insert a ledger record; it's only for UI/testing purposes.
$test_offset = 0;
try {
  $pref = get_user_preferences('ascend_test_coins', '', $USER->id);
  if ($pref !== '') {
    $test_offset = (int)$pref;
  }
} catch (Exception $e) {
  // ignore
}

$coin_balance += $test_offset;

$balance_course = null;
if (!is_null($courseid)) {
    $balance_course = (int)$DB->get_field_sql(
        "SELECT COALESCE(SUM(coins), 0)
           FROM {local_ascend_rewards_coins}
          WHERE userid = :uid
            AND courseid = :cid
            AND coins > 0",
        ['uid' => $USER->id, 'cid' => $courseid]
    );
}

/** ------------------------------------------------------------------------
 *  TOTAL BADGES (INCLUDING REPEATABLE BADGES)
 *  --------------------------------------------------------------------- */
$totalbadges = (int)$DB->get_field_sql(
    "SELECT COUNT(*)
       FROM {local_ascend_rewards_coins} c
      WHERE c.userid = :uid
        AND c.badgeid > 0 {$where_course_c}",
    $userparam
);

/** ------------------------------------------------------------------------
 *  RANKING: Uses local_ascend_rewards_xp table - XP is SEPARATE from coins
 *  XP never decreases. Rankings are ALWAYS based on XP.
 *  --------------------------------------------------------------------- */

// Determine which XP to use: course-specific or site-wide (courseid=0)
$xp_courseid = is_null($courseid) ? 0 : $courseid;

// Use cached user stats for better performance
$user_stats = \local_ascend_rewards\performance_cache::get_user_stats($USER->id, $xp_courseid);
$user_xp = $user_stats['xp'];

// Get total earners (users with XP > 0 in this scope)
$totalearners = (int)$DB->count_records_sql(
    "SELECT COUNT(*) FROM {local_ascend_rewards_xp} WHERE courseid = :cid AND xp > 0",
    ['cid' => $xp_courseid]
);

// Calculate user's rank using cached method
$myrank = \local_ascend_rewards\performance_cache::get_user_rank($USER->id, $xp_courseid, $user_xp);

// Also get site-wide rank for display when course is filtered
$site_user_stats = \local_ascend_rewards\performance_cache::get_user_stats($USER->id, 0);
$site_user_xp = $site_user_stats['xp'];

$site_totalearners = (int)$DB->count_records_sql(
    "SELECT COUNT(*) FROM {local_ascend_rewards_xp} WHERE courseid = 0 AND xp > 0"
);

$site_myrank = \local_ascend_rewards\performance_cache::get_user_rank($USER->id, 0, $site_user_xp);

/** ------------------------------------------------------------------------
 *  TOP 10 LEADERBOARD - uses local_ascend_rewards_xp table (cached)
 *  --------------------------------------------------------------------- */
$top10 = \local_ascend_rewards\performance_cache::get_leaderboard($xp_courseid, 10);

// Check if current user is in top 10, if not, get their data separately
$user_in_top10 = false;
foreach ($top10 as $row) {
    if ((int)$row->userid === (int)$USER->id) {
        $user_in_top10 = true;
        break;
    }
}

$current_user_data = null;
if (!$user_in_top10 && $user_xp > 0) {
    // Get current user's data
    $current_user_data = (object)[
        'userid' => (int)$USER->id,
        'xp' => $user_xp,
        'rank' => $myrank
    ];
}

/** ------------------------------------------------------------------------
 *  COURSE DROPDOWN OPTIONS
 *  --------------------------------------------------------------------- */
$courseoptions = [];

$course_rows = $DB->get_records_sql("
    SELECT DISTINCT cns.courseid, crs.fullname AS coursename
      FROM {local_ascend_rewards_coins} cns
      LEFT JOIN {course} crs ON crs.id = cns.courseid
     WHERE cns.userid = :uid AND cns.courseid > 1
     ORDER BY crs.fullname ASC
", ['uid' => $USER->id]);

foreach ($course_rows as $r) {
    $courseoptions[(string)(int)$r->courseid] = $r->coursename;
}

$selectedcoursename = (!is_null($courseid) && isset($courseoptions[(string)$courseid]))
    ? $courseoptions[(string)$courseid]
    : null;

/** ------------------------------------------------------------------------
 *  BADGES BY COURSE
 *  --------------------------------------------------------------------- */
$badgenames_map_full = array_flip($badge_definitions);
$paramscourse        = $userparam;

$sql_course = "SELECT cns.id,
                      cns.badgeid,
                      cns.coins,
                      cns.courseid,
                      cns.timecreated,
                      crs.fullname AS coursename
                 FROM {local_ascend_rewards_coins} cns
                 LEFT JOIN {course} crs ON crs.id = cns.courseid
                WHERE cns.userid = :uid {$where_course_cns}
                  AND cns.courseid > 0
                  AND cns.badgeid > 0
                  AND cns.coins > 0
             ORDER BY crs.fullname ASC, cns.timecreated DESC";

$rows_course = $DB->get_records_sql($sql_course, $paramscourse);

$bycourse = [];
foreach ($rows_course as $r) {
    $name  = $badgenames_map_full[(int)$r->badgeid] ?? 'Coin Transaction';
    $name  = local_ascend_rewards_normalize_badge_name($name);
    $bucket = $r->coursename;
    if (!$bucket) continue;  // Skip if no course name
    if (!isset($bycourse[$bucket])) {
        $bycourse[$bucket] = [];
    }
    $r->badgename_display = $name;
    $r->icon_url          = local_ascend_rewards_badge_icon_url($name, $badge_images, $pixbase, $coin_fallback)->out(false);
    $r->formatted_date    = local_ascend_rewards_fmt_date((int)$r->timecreated);
    $r->coins_text        = (((int)$r->coins) > 0 ? '+' : '') . (int)$r->coins;
    $bycourse[$bucket][]  = $r;
}

// Course journeys and AI coaching are disabled in the demo version.
$journeys = [];

/** ------------------------------------------------------------------------
 *  XP / LEVEL CALC - Use cached stats (NEVER decreases)
 *  --------------------------------------------------------------------- */
// Reuse site-wide stats we already fetched above
$xp = $site_user_xp;

// Calculate badge coins for coin balance (separate from XP)
$badge_coins = (int)$DB->get_field_sql(
    "SELECT COALESCE(SUM(coins), 0)
       FROM {local_ascend_rewards_coins}
      WHERE userid = :uid AND badgeid > 0 AND coins > 0",
    ['uid' => $USER->id]
);

// Check for XP Multiplier (applied when badges are awarded, not here)
$xp_multiplier_end = get_user_preferences('ascend_xp_multiplier_end', 0, $USER->id);
$xp_multiplier_active = ($xp_multiplier_end > time());
$xp_multiplier_expires = $xp_multiplier_active ? $xp_multiplier_end : 0;

$level              = (int)floor($xp / 1000);
$xp_for_current_lvl = $level * 1000;
$xp_for_next_lvl    = ($level + 1) * 1000;
$xp_progress        = ($level > 0) ? ($xp - $xp_for_current_lvl) : $xp;
$xp_needed          = $xp_for_next_lvl - $xp_for_current_lvl;
$xp_percent         = ($xp_needed > 0 ? ($xp_progress / $xp_needed) * 100 : 100);

$last_level_up = (int)get_user_preferences('ascend_level_up_time', 0, $USER->id);
$show_level_up = ($last_level_up > 0 && (time() - $last_level_up) <= 7 * DAYSECS);

/** ------------------------------------------------------------------------
 *  BADGE CATEGORIES (FOR DISPLAY)
 *  --------------------------------------------------------------------- */
// DEMO VERSION: Only 7 active badges.
$badge_categories = [
    'Getting Started'   => 'progress',
    'Halfway Hero'      => 'progress',
    'Master Navigator'  => 'progress',
    'Feedback Follower' => 'quality',
    'Steady Improver'   => 'quality',
    'Tenacious Tiger'   => 'quality',
    'Glory Guide'       => 'quality',
];

/** ------------------------------------------------------------------------
 *  CELEBRATION FLAG
 *  --------------------------------------------------------------------- */
$celebratesince = (int)get_user_preferences('ascendassets_lastcoin', 0, $USER->id);
$has_earned = false;
try {
    $has_earned = local_ascend_rewards_gameboard::has_earned_badges_this_week($USER->id);
} catch (Exception $e) {
    $has_earned = false;
}

// Keep celebration and gameboard eligibility in the exact same weekly window.
$showcelebration = $has_earned;

/** ------------------------------------------------------------------------
 *  TEMPLATE CONTEXT
 *  --------------------------------------------------------------------- */

// Course filter options for template
$courseoptions_list = [];
foreach ($courseoptions as $cid => $label) {
    $courseoptions_list[] = [
        'value' => (string)$cid,
        'label' => format_string($label),
        'selected' => ((string)$courseid === (string)$cid),
    ];
}

// Leaderboard mode label
$leaderboard_mode_label = get_string('leaderboard_top10_label', 'local_ascend_rewards');
if (!is_null($courseid) && !empty($selectedcoursename)) {
    $leaderboard_mode_label = get_string('leaderboard_top10_course_label', 'local_ascend_rewards', $selectedcoursename);
}

// Leaderboard context label
$leaderboard_context_label = !is_null($courseid) && !empty($selectedcoursename)
    ? $selectedcoursename
    : get_string('leaderboard_context_sitewide', 'local_ascend_rewards');

$you_label = get_string('you_label', 'local_ascend_rewards');
$user_number_prefix = get_string('user_number_prefix', 'local_ascend_rewards');

// Build Top 10 list for template
$top10_list = [];
$rank = 1;
foreach ($top10 as $row) {
    $is_current_user = ((int)$row->userid === (int)$USER->id);
    $display_xp = isset($row->xp) ? (int)$row->xp : 0;
    $top10_list[] = [
        'is_current_user' => $is_current_user,
        'pos_label' => local_ascend_rewards_medal_for_place($rank),
        'display_name' => $is_current_user ? $you_label : ($user_number_prefix . (int)$row->userid),
        'user_id' => (int)$USER->id,
        'grad_id' => 'xpIconGradLB' . $rank,
        'xp_display' => number_format($display_xp),
    ];
    $rank++;
}

$current_user_entry = null;
if ($current_user_data !== null) {
    $current_user_entry = [
        'pos_label' => local_ascend_rewards_medal_for_place((int)$current_user_data->rank),
        'you_label' => $you_label,
        'user_id' => (int)$USER->id,
        'xp_display' => number_format((int)$current_user_data->xp),
    ];
}

// Badges grouped by course and category
$badges_by_course = [];
$category_colors = [
    'progress' => '#FF00AA',
    'timeliness' => '#00D4FF',
    'quality' => '#FF9500',
    'mastery' => '#7A00FF',
    'other' => '#A5B4D6',
];

foreach ($bycourse as $coursename => $list) {
    $badge_counts = [];
    $badge_data = [];
    foreach ($list as $r) {
        $badge_key = $r->badgeid;
        if (!isset($badge_counts[$badge_key])) {
            $badge_counts[$badge_key] = 0;
            $badge_data[$badge_key] = $r;
        }
        $badge_counts[$badge_key]++;
    }

    $by_category = [];
    foreach ($badge_data as $badgeid => $r) {
        $category_key = $badge_categories[$r->badgename_display] ?? 'other';
        if (!isset($by_category[$category_key])) {
            $by_category[$category_key] = [];
        }
        $r->earn_count = $badge_counts[$badgeid];
        $by_category[$category_key][] = $r;
    }

    $categories = [];
    foreach ($by_category as $category_key => $badges) {
        $category_label = get_string('badge_category_' . $category_key, 'local_ascend_rewards');
        $badges_list = [];
        foreach ($badges as $item) {
            $earned_text = $item->earn_count > 1
                ? get_string('badge_earned_times', 'local_ascend_rewards', $item->earn_count)
                : get_string('badge_earned_on', 'local_ascend_rewards', $item->formatted_date);
            $badges_list[] = [
                'badge_name_raw' => $item->badgename_display,
                'badge_name' => format_string($item->badgename_display),
                'badge_id' => (int)$item->badgeid,
                'course_id' => (int)$item->courseid,
                'course_name' => $coursename,
                'formatted_date' => $item->formatted_date,
                'coins_text' => $item->coins_text,
                'xp' => $xp,
                'level' => $level,
                'xp_percent' => $xp_percent,
                'why' => $badge_descriptions[$item->badgename_display] ?? get_string('badge_desc_default', 'local_ascend_rewards'),
                'category' => $category_label,
                'earn_count_gt1' => ($item->earn_count > 1),
                'earn_count' => (int)$item->earn_count,
                'icon_url' => $item->icon_url,
                'fallback_icon_url' => $coin_fallback->out(false),
                'earned_text' => $earned_text,
            ];
        }
        $categories[] = [
            'name' => format_string($category_label),
            'color_class' => 'aa-category-' . $category_key,
            'badges' => $badges_list,
        ];
    }
    $badges_by_course[] = [
        'course_name' => $coursename,
        'categories' => $categories,
    ];
}

// Journey context disabled for demo.
$journeys_context = [];
$show_journeys = false;

// Weekly gameboard context
$gameboard_icon_url = (new moodle_url('/local/ascend_rewards/pix/gameboard.png'))->out(false);
$gameboard = [
    'available' => false,
    'icon_url' => $gameboard_icon_url,
];

if ($has_earned) {
    try {
        $available_picks = local_ascend_rewards_gameboard::get_available_picks($USER->id);
        $picks_made = local_ascend_rewards_gameboard::get_picks_made($USER->id);
        $remaining_picks = local_ascend_rewards_gameboard::get_remaining_picks($USER->id);
        $card_values = local_ascend_rewards_gameboard::get_card_values($USER->id);
        $badge_layout = local_ascend_rewards_gameboard::get_badge_layout();
        $badge_names = local_ascend_rewards_gameboard::get_badge_names();
        list($week_start, $week_end) = local_ascend_rewards_gameboard::get_week_range();

        $week_start_str = userdate($week_start, '%d %b');
        $week_end_str = userdate($week_end, '%d %b %Y');

        if (!empty($available_picks) && is_array($picks_made) && !empty($badge_layout) && !empty($badge_names)) {
            $cards = [];
            $flat_layout = [];
            foreach ($badge_layout as $row) {
                $flat_layout = array_merge($flat_layout, $row);
            }
            for ($i = 0; $i < 16; $i++) {
                $picked = in_array($i, $picks_made);
                $coin_value = $card_values[$i] ?? 0;
                $badge_id = $flat_layout[$i] ?? 0;
                $badge_name = $badge_names[$badge_id] ?? '';
                $badge_filename = strtolower(str_replace(' ', '_', $badge_name));
                $cards[] = [
                    'picked' => $picked,
                    'position' => $i,
                    'coin_value' => $coin_value,
                    'coin_icon_url' => (new moodle_url('/local/ascend_rewards/pix/ascend_coin_main.png'))->out(false),
                    'coin_label' => 'Coin',
                    'badge_image_url' => (new moodle_url('/local/ascend_rewards/pix/' . $badge_filename . '.png'))->out(false),
                    'badge_name' => $badge_name,
                    'hover_coin_url' => (new moodle_url('/local/ascend_rewards/pix/ascend_coin_main.png'))->out(false),
                    'pick_label' => 'Pick',
                ];
            }
            $gameboard = [
                'available' => true,
                'icon_url' => $gameboard_icon_url,
                'week_range_label' => $week_start_str . ' - ' . $week_end_str,
                'remaining_picks' => $remaining_picks,
                'available_picks_normal' => $available_picks['normal'] ?? 0,
                'available_picks_meta' => $available_picks['meta'] ?? 0,
                'cards' => $cards,
            ];
        }
    } catch (Exception $e) {
        // keep gameboard unavailable
    } catch (Throwable $t) {
        // keep gameboard unavailable
    }
}

// Avatars section data (demo version)
$avatar_levels = [
    1 => ['elf.png', 'imp.png'],
];

$avatar_pets_catalog = [
    100 => [
        'name' => 'Lynx',
        'avatar' => 'elf.png',
        'level' => 1,
        'icon' => 'pets/lynx.png',
        'video' => 'pets/videos/lynx.mp4',
        'download_image' => 'pets/pets_circular/lynx.png',
        'price' => 300,
    ],
    102 => [
        'name' => 'Hamster',
        'avatar' => 'imp.png',
        'level' => 1,
        'icon' => 'pets/hamster.png',
        'video' => 'pets/videos/hamster.mp4',
        'download_image' => 'pets/pets_circular/hamster.png',
        'price' => 300,
    ],
];

$villain_catalog = [
    300 => ['name' => 'Dryad', 'pet_id' => 100, 'avatar' => 'elf.png', 'level' => 1, 'icon' => 'villains/elf_dryad.png', 'video' => 'villains/videos/elf_dryad.mp4', 'price' => 500],
    302 => ['name' => 'Mole', 'pet_id' => 102, 'avatar' => 'imp.png', 'level' => 1, 'icon' => 'villains/imp_mole.png', 'video' => 'villains/videos/imp_mole.mp4', 'price' => 500],
];

// Get user's unlocked avatars from database
$unlocked_avatars_db = $DB->get_records('local_ascend_rewards_avatar_unlocks', ['userid' => $USER->id, 'pet_id' => null, 'villain_id' => null], 'timecreated');
$unlocked_avatars = [];
$avatar_unlock_types = [];
foreach ($unlocked_avatars_db as $record) {
    $unlocked_avatars[] = $record->avatar_name;
    $avatar_unlock_types[$record->avatar_name] = $record->unlock_type;
}

// Get user's pet unlocks from database
$pet_unlocks_db = $DB->get_records_sql(
    "SELECT * FROM {local_ascend_rewards_avatar_unlocks} WHERE userid = ? AND pet_id IS NOT NULL AND villain_id IS NULL",
    [$USER->id]
);
$owned_pets = [];
$pet_unlock_types = [];
foreach ($pet_unlocks_db as $record) {
    $owned_pets[] = $record->pet_id;
    $pet_unlock_types[$record->pet_id] = $record->unlock_type;
}

// Get user's villain unlocks from database
$villain_unlocks_db = $DB->get_records_sql(
    "SELECT * FROM {local_ascend_rewards_avatar_unlocks} WHERE userid = ? AND villain_id IS NOT NULL",
    [$USER->id]
);
$owned_villains = [];
$villain_unlock_types = [];
foreach ($villain_unlocks_db as $record) {
    $owned_villains[] = $record->villain_id;
    $villain_unlock_types[$record->villain_id] = $record->unlock_type;
}

// Get user's level tokens
$user_tokens = $DB->get_record('local_ascend_rewards_level_tokens', ['userid' => $USER->id]);
$tokens_available = $user_tokens ? $user_tokens->tokens_available - $user_tokens->tokens_used : 0;

// Determine which levels user can access (including epic levels 5-8)
$user_accessible_levels = [];
if ($level >= 1) $user_accessible_levels[] = 1;
if ($level >= 2) $user_accessible_levels[] = 2;
if ($level >= 3) $user_accessible_levels[] = 3;
if ($level >= 4) $user_accessible_levels[] = 4;
if ($level >= 5) $user_accessible_levels[] = 5;
if ($level >= 6) $user_accessible_levels[] = 6;
if ($level >= 7) $user_accessible_levels[] = 7;
if ($level >= 8) $user_accessible_levels[] = 8;

// Avatar section context
$avatar_icon_url = (new moodle_url('/local/ascend_rewards/pix/avatar.png'))->out(false);
$pets_icon_url = (new moodle_url('/local/ascend_rewards/pix/pets.png'))->out(false);
$villain_icon_url = (new moodle_url('/local/ascend_rewards/pix/villain.png'))->out(false);
$storybook_url = (new moodle_url('/local/ascend_rewards/pix/storybook.png'))->out(false);
$coin_stack_url = (new moodle_url('/local/ascend_rewards/pix/ascend_assets_stack.png'))->out(false);

$tokens_available = (int)$tokens_available;
$tokens_plural = ($tokens_available !== 1);

$level_worlds = [
    1 => 'Emberveil Forest',
    2 => 'Stormscar Desert',
    3 => 'Veilspire Empire',
    4 => 'Thalassar Archipelago',
    5 => 'Arcanum Citadel',
    6 => 'Frostfang Tundra',
];

$story_videos = [
    'elf' => '5Br95mH5oTU',
    'imp' => '5cWCcTysX54',
];

$levels_context = [];
for ($lv = 1; $lv <= 6; $lv++) {
    $can_access_level = in_array($lv, $user_accessible_levels);
    $level_avatars = $avatar_levels[$lv] ?? [];
    if (empty($level_avatars)) {
        continue;
    }

    $sets = [];
    foreach ($level_avatars as $avatar) {
        $set = ['avatar' => $avatar, 'pet' => null, 'pet_id' => null, 'villain' => null, 'villain_id' => null];

        foreach ($avatar_pets_catalog as $pet_id => $pet_data) {
            if ($pet_data['level'] === $lv && $pet_data['avatar'] === $avatar) {
                $set['pet'] = $pet_data;
                $set['pet_id'] = $pet_id;
                break;
            }
        }

        if ($set['pet_id']) {
            foreach ($villain_catalog as $villain_id => $villain_data) {
                if ($villain_data['level'] === $lv && $villain_data['pet_id'] == $set['pet_id']) {
                    $set['villain'] = $villain_data;
                    $set['villain_id'] = $villain_id;
                    break;
                }
            }
        }

        $sets[] = $set;
    }

    $set_contexts = [];
    foreach ($sets as $index => $set) {
        $avatar = $set['avatar'];
        $avatar_name = pathinfo($avatar, PATHINFO_FILENAME);
        $display_name = ucfirst($avatar_name);
        $is_unlocked = in_array($avatar, $unlocked_avatars);
        $avatar_url = (new moodle_url("/local/ascend_rewards/pix/Avatars/{$avatar}"))->out(false);

        $hero_context = [
            'filename' => $avatar,
            'display_name' => $display_name,
            'image_url' => $avatar_url,
            'unlocked' => $is_unlocked,
            'card_class' => $is_unlocked ? 'unlocked' : 'locked',
            'filter' => $is_unlocked ? 'none' : 'grayscale(100%) brightness(0.3)',
        ];

        $pet_context = null;
        $pet_data = $set['pet'];
        $pet_id = $set['pet_id'];
        $has_pet_unlocked = $pet_data && in_array($pet_id, $owned_pets);
        $can_unlock_pet = $pet_data && $is_unlocked;
        if ($pet_data) {
            $pet_name = $pet_data['name'];
            $pet_icon_path = str_replace('pets/', '', $pet_data['icon']);
            $pet_url = (new moodle_url("/local/ascend_rewards/pix/pets/{$pet_icon_path}"))->out(false);
            $avatar_filename = str_replace(['.png', '.jpeg'], '', $pet_data['avatar']);
            $avatar_circular_url = (new moodle_url("{$avatar_circular_dir}/{$avatar_filename}.png"))->out(false);

            $pet_context = [
                'id' => $pet_id,
                'name' => $pet_name,
                'price' => (int)$pet_data['price'],
                'avatar' => $pet_data['avatar'],
                'card_class' => $has_pet_unlocked ? 'owned' : 'locked',
                'cursor' => ($can_unlock_pet && !$has_pet_unlocked) ? 'pointer' : 'default',
                'can_unlock_flag' => $can_unlock_pet ? '1' : '0',
                'image_url' => $pet_url,
                'filter' => $has_pet_unlocked ? 'none' : 'grayscale(100%) brightness(0.3)',
                'show_lock_overlay' => !$can_unlock_pet,
                'show_price_overlay' => $can_unlock_pet && !$has_pet_unlocked,
                'show_avatar_badge' => $is_unlocked,
                'avatar_badge_url' => $avatar_circular_url,
            ];
        }

        $villain_context = null;
        $villain_data = $set['villain'];
        $villain_id = $set['villain_id'];
        $has_villain_unlocked = $villain_data && in_array($villain_id, $owned_villains);
        $can_unlock_villain = $villain_data && $has_pet_unlocked;
        if ($villain_data) {
            $villain_name = $villain_data['name'];
            $villain_icon_path = str_replace('villains/', '', $villain_data['icon']);
            $villain_icon_name = pathinfo($villain_icon_path, PATHINFO_FILENAME);
            $villain_url = (new moodle_url("/local/ascend_rewards/pix/villains/{$villain_icon_path}"))->out(false);
            $villain_pet_icon = $pet_data ? str_replace('pets/', '', $pet_data['icon']) : '';
            $villain_pet_url = $pet_data ? (new moodle_url("/local/ascend_rewards/pix/pets/{$villain_pet_icon}"))->out(false) : '';
            $villain_avatar_filename = $villain_data['avatar'] ? str_replace(['.png', '.jpeg'], '', $villain_data['avatar']) : '';
            $villain_avatar_circular_url = $villain_avatar_filename ? (new moodle_url("{$avatar_circular_dir}/{$villain_avatar_filename}.png"))->out(false) : '';

            $story = null;
            $set_complete = $is_unlocked && $has_pet_unlocked && $has_villain_unlocked;
            if ($set_complete) {
                $avatar_key = pathinfo($avatar, PATHINFO_FILENAME);
                $story = [
                    'set_name' => ucfirst($avatar_key),
                    'youtube_id' => $story_videos[$avatar_key] ?? 'qxZUXNi7AJw',
                    'storybook_url' => $storybook_url,
                ];
            }

            $villain_context = [
                'id' => $villain_id,
                'name' => $villain_name,
                'price' => (int)$villain_data['price'],
                'pet_id' => (int)$villain_data['pet_id'],
                'icon_name' => $villain_icon_name,
                'card_class' => $has_villain_unlocked ? 'owned' : 'locked',
                'cursor' => ($can_unlock_villain && !$has_villain_unlocked) ? 'pointer' : 'default',
                'can_unlock_flag' => $can_unlock_villain ? '1' : '0',
                'image_url' => $villain_url,
                'filter' => $has_villain_unlocked ? 'none' : 'grayscale(100%) brightness(0.3)',
                'show_lock_overlay' => !$can_unlock_villain,
                'show_price_overlay' => $can_unlock_villain && !$has_villain_unlocked,
                'show_pet_badge' => $has_pet_unlocked && !empty($villain_pet_url),
                'pet_badge_url' => $villain_pet_url,
                'show_avatar_badge' => $has_pet_unlocked && !empty($villain_avatar_circular_url),
                'avatar_badge_url' => $villain_avatar_circular_url,
                'story' => $story,
            ];
        }

        $set_contexts[] = [
            'is_first_column' => ($index === 0),
            'hero' => $hero_context,
            'pet' => $pet_context,
            'villain' => $villain_context,
        ];
    }

    $levels_context[] = [
        'level_number' => $lv,
        'world_name' => $level_worlds[$lv] ?? '',
        'can_access' => $can_access_level,
        'header_color' => $can_access_level ? '#06b6d4' : '#64748b',
        'content_opacity' => $can_access_level ? '1' : '0.4',
        'content_pointer' => $can_access_level ? 'auto' : 'none',
        'sets' => $set_contexts,
    ];
}

$epic_levels_context = [];
foreach ([7, 8, 9] as $epic_lv) {
    $can_access_epic = in_array($epic_lv, $user_accessible_levels);
    $epic_avatars = $avatar_levels[$epic_lv] ?? [];
    $epic_pets = [];
    foreach ($avatar_pets_catalog as $pet_id => $pet_data) {
        if ($pet_data['level'] === $epic_lv) {
            $epic_pets[$pet_id] = $pet_data;
        }
    }
    $epic_villains = [];
    foreach ($villain_catalog as $villain_id => $villain_data) {
        if ($villain_data['level'] === $epic_lv) {
            $epic_villains[$villain_id] = $villain_data;
        }
    }
    if (empty($epic_avatars) && empty($epic_pets) && empty($epic_villains)) {
        continue;
    }

    $hero_list = [];
    foreach ($epic_avatars as $avatar) {
        $avatar_name = pathinfo($avatar, PATHINFO_FILENAME);
        $display_name = ucfirst($avatar_name);
        $is_unlocked = in_array($avatar, $unlocked_avatars);
        $avatar_url = (new moodle_url("/local/ascend_rewards/pix/Avatars/{$avatar}"))->out(false);
        $hero_list[] = [
            'filename' => $avatar,
            'display_name' => $display_name,
            'image_url' => $avatar_url,
            'unlocked' => $is_unlocked,
            'card_class' => $is_unlocked ? 'unlocked' : 'locked',
            'filter' => $is_unlocked ? 'none' : 'grayscale(100%) brightness(0.3)',
        ];
    }

    $pet_list = [];
    foreach ($epic_pets as $pet_id => $pet_data) {
        $pet_name = $pet_data['name'];
        $is_owned = in_array($pet_id, $owned_pets);
        $pet_icon_path = str_replace('pets/', '', $pet_data['icon']);
        $pet_url = (new moodle_url("/local/ascend_rewards/pix/pets/{$pet_icon_path}"))->out(false);
        $avatar_filename = str_replace(['.png', '.jpeg'], '', $pet_data['avatar']);
        $avatar_circular_url = (new moodle_url("{$avatar_circular_dir}/{$avatar_filename}.png"))->out(false);
        $pet_avatar_unlocked = in_array($pet_data['avatar'], $unlocked_avatars);

        $pet_list[] = [
            'id' => $pet_id,
            'name' => $pet_name,
            'price' => (int)$pet_data['price'],
            'avatar' => $pet_data['avatar'],
            'card_class' => $is_owned ? 'owned' : 'locked',
            'cursor' => ($pet_avatar_unlocked && !$is_owned) ? 'pointer' : 'default',
            'can_unlock_flag' => $pet_avatar_unlocked ? '1' : '0',
            'image_url' => $pet_url,
            'filter' => $is_owned ? 'none' : 'grayscale(100%) brightness(0.3)',
            'show_lock_overlay' => !$pet_avatar_unlocked,
            'show_price_overlay' => $pet_avatar_unlocked && !$is_owned,
            'show_avatar_badge' => $pet_avatar_unlocked,
            'avatar_badge_url' => $avatar_circular_url,
        ];
    }

    $villain_list = [];
    foreach ($epic_villains as $villain_id => $villain_data) {
        $villain_name = $villain_data['name'];
        $is_owned = in_array($villain_id, $owned_villains);
        $villain_icon_path = str_replace('villains/', '', $villain_data['icon']);
        $villain_icon_name = pathinfo($villain_icon_path, PATHINFO_FILENAME);
        $villain_url = (new moodle_url("/local/ascend_rewards/pix/villains/{$villain_icon_path}"))->out(false);
        $pet_data = $villain_data['pet_id'] ? ($avatar_pets_catalog[$villain_data['pet_id']] ?? null) : null;
        $pet_icon = $pet_data ? str_replace('pets/', '', $pet_data['icon']) : '';
        $pet_url = $pet_data ? (new moodle_url("/local/ascend_rewards/pix/pets/{$pet_icon}"))->out(false) : '';
        $avatar_filename = $villain_data['avatar'] ? str_replace(['.png', '.jpeg'], '', $villain_data['avatar']) : '';
        $avatar_circular_url = $avatar_filename ? (new moodle_url("{$avatar_circular_dir}/{$avatar_filename}.png"))->out(false) : '';
        $villain_pet_owned = $villain_data['pet_id'] && in_array($villain_data['pet_id'], $owned_pets);

        $villain_list[] = [
            'id' => $villain_id,
            'name' => $villain_name,
            'price' => (int)$villain_data['price'],
            'pet_id' => (int)$villain_data['pet_id'],
            'icon_name' => $villain_icon_name,
            'card_class' => $is_owned ? 'owned' : 'locked',
            'cursor' => ($villain_pet_owned && !$is_owned) ? 'pointer' : 'default',
            'can_unlock_flag' => $villain_pet_owned ? '1' : '0',
            'image_url' => $villain_url,
            'filter' => $is_owned ? 'none' : 'grayscale(100%) brightness(0.3)',
            'show_lock_overlay' => !$villain_pet_owned,
            'show_price_overlay' => $villain_pet_owned && !$is_owned,
            'show_pet_badge' => $villain_pet_owned && !empty($pet_url),
            'pet_badge_url' => $pet_url,
            'show_avatar_badge' => $villain_pet_owned && !empty($avatar_circular_url),
            'avatar_badge_url' => $avatar_circular_url,
        ];
    }

    $epic_levels_context[] = [
        'level_number' => $epic_lv,
        'can_access' => $can_access_epic,
        'header_color' => $can_access_epic ? '#a855f7' : '#64748b',
        'content_opacity' => $can_access_epic ? '1' : '0.4',
        'content_pointer' => $can_access_epic ? 'auto' : 'none',
        'heroes' => $hero_list,
        'pets' => $pet_list,
        'villains' => $villain_list,
    ];
}

$modal_strings = [
    'alertTitle' => get_string('alert_title', 'local_ascend_rewards'),
    'errorTitle' => get_string('error_title', 'local_ascend_rewards'),
    'noTokensAvailable' => get_string('modal_no_tokens_available', 'local_ascend_rewards'),
    'unlockAvatarTitle' => get_string('modal_unlock_avatar_title', 'local_ascend_rewards', (object) [
        'name' => '{name}',
    ]),
    'unlockAvatarLevelNote' => get_string('modal_unlock_avatar_level_note', 'local_ascend_rewards', (object) [
        'level' => '{level}',
    ]),
    'unlockAvatarButton' => get_string('modal_unlock_avatar_button', 'local_ascend_rewards'),
    'unlockAvatarNote' => get_string('modal_unlock_avatar_note', 'local_ascend_rewards'),
    'unlockingLabel' => get_string('unlocking_label', 'local_ascend_rewards'),
    'processingLabel' => get_string('processing_label', 'local_ascend_rewards'),
    'errorPrefix' => get_string('error_prefix', 'local_ascend_rewards'),
    'errorUnlockAvatar' => get_string('modal_error_unlock_avatar', 'local_ascend_rewards'),
    'avatarUnlockedTitle' => get_string('modal_avatar_unlocked_title', 'local_ascend_rewards', (object) [
        'name' => '{name}',
    ]),
    'avatarPetRevealedTitle' => get_string('modal_avatar_pet_revealed_title', 'local_ascend_rewards', (object) [
        'name' => '{name}',
    ]),
    'avatarPetRevealedText' => get_string('modal_avatar_pet_revealed_text', 'local_ascend_rewards'),
    'downloadAvatarLabel' => get_string('modal_download_avatar_label', 'local_ascend_rewards'),
    'avatarUseProfileNote' => get_string('modal_avatar_use_profile_note', 'local_ascend_rewards'),
    'closeLabel' => get_string('close_label', 'local_ascend_rewards'),
    'cancelLabel' => get_string('cancel_label', 'local_ascend_rewards'),
    'petUnlockTitle' => get_string('modal_pet_unlock_title', 'local_ascend_rewards'),
    'unlockMethodLabel' => get_string('modal_unlock_method_label', 'local_ascend_rewards'),
    'tokenAvailableLabel' => get_string('modal_token_available_label', 'local_ascend_rewards', (object) [
        'count' => '{count}',
    ]),
    'payCoinsLabel' => get_string('modal_pay_coins_label', 'local_ascend_rewards', (object) [
        'price' => '{price}',
        'balance' => '{balance}',
    ]),
    'insufficientCoinsLabel' => get_string('modal_insufficient_coins_label', 'local_ascend_rewards', (object) [
        'price' => '{price}',
    ]),
    'useTokenButton' => get_string('modal_use_token_button', 'local_ascend_rewards'),
    'payButtonLabel' => get_string('modal_pay_button_label', 'local_ascend_rewards', (object) [
        'price' => '{price}',
    ]),
    'errorUnlockPet' => get_string('modal_error_unlock_pet', 'local_ascend_rewards'),
    'petAdoptedTitle' => get_string('modal_pet_adopted_title', 'local_ascend_rewards'),
    'petVillainRevealedTitle' => get_string('modal_pet_villain_revealed_title', 'local_ascend_rewards'),
    'petVillainRevealedText' => get_string('modal_pet_villain_revealed_text', 'local_ascend_rewards'),
    'downloadPetLabel' => get_string('modal_download_pet_label', 'local_ascend_rewards'),
    'unlockedWithLabel' => get_string('modal_unlocked_with_label', 'local_ascend_rewards', (object) [
        'method' => '{method}',
    ]),
    'newBalanceLabel' => get_string('modal_new_balance_label', 'local_ascend_rewards', (object) [
        'balance' => '{balance}',
        'coinsLabel' => '{coinsLabel}',
    ]),
    'continueLabel' => get_string('continue_label', 'local_ascend_rewards'),
    'villainUnlockTitle' => get_string('modal_villain_unlock_title', 'local_ascend_rewards'),
    'villainUnlockedTitle' => get_string('modal_villain_unlocked_title', 'local_ascend_rewards'),
    'villainStorybookUnlockedTitle' => get_string('modal_villain_storybook_unlocked_title', 'local_ascend_rewards'),
    'villainStorybookUnlockedText' => get_string('modal_villain_storybook_unlocked_text', 'local_ascend_rewards'),
    'downloadVillainLabel' => get_string('modal_download_villain_label', 'local_ascend_rewards'),
    'errorUnlockVillain' => get_string('modal_error_unlock_villain', 'local_ascend_rewards'),
    'tokenLabel' => get_string('token_label', 'local_ascend_rewards'),
    'coinsLabel' => get_string('coins_label', 'local_ascend_rewards'),
    'ajaxRequestFailed' => get_string('ajax_request_failed', 'local_ascend_rewards'),
    'ajaxConfigMissing' => get_string('ajax_config_missing', 'local_ascend_rewards'),
    'ajaxInvalidJson' => get_string('ajax_invalid_json', 'local_ascend_rewards'),
    'ajaxNetworkError' => get_string('ajax_network_error', 'local_ascend_rewards'),
];

$avatar_js_config = json_encode([
    'tokensAvailable' => $tokens_available,
    'coinBalance' => (int)$coin_balance,
    'storyFallbackTitle' => get_string('watch_story_label', 'local_ascend_rewards'),
    'modalStrings' => $modal_strings,
]);

$avatar_context = [
    'avatar_icon_url' => $avatar_icon_url,
    'ascend_universe_label' => get_string('ascend_universe_label', 'local_ascend_rewards'),
    'show_tokens_available' => ($tokens_available > 0),
    'tokens_available_badge_text' => $tokens_available > 0
        ? get_string('tokens_available_badge_text', 'local_ascend_rewards', $tokens_available)
        : '',
    'tokens_available_text' => $tokens_available > 0
        ? get_string('tokens_available_text', 'local_ascend_rewards', $tokens_available)
        : '',
    'you_are_level_label' => get_string('you_are_level_label', 'local_ascend_rewards'),
    'you_have_label' => get_string('you_have_label', 'local_ascend_rewards'),
    'unlocked_avatars_count' => count($unlocked_avatars),
    'heroes_plural' => (count($unlocked_avatars) !== 1),
    'hero_label' => get_string('hero_label', 'local_ascend_rewards'),
    'heroes_label' => get_string('heroes_label', 'local_ascend_rewards'),
    'unlocked_label' => get_string('unlocked_label', 'local_ascend_rewards'),
    'owned_pets_count' => count($owned_pets),
    'pets_plural' => (count($owned_pets) !== 1),
    'pet_label' => get_string('pet_label', 'local_ascend_rewards'),
    'pets_label' => get_string('pets_label', 'local_ascend_rewards'),
    'adopted_label' => get_string('adopted_label', 'local_ascend_rewards'),
    'and_label' => get_string('and_label', 'local_ascend_rewards'),
    'owned_villains_count' => count($owned_villains),
    'villains_plural' => (count($owned_villains) !== 1),
    'villain_label' => get_string('villain_label', 'local_ascend_rewards'),
    'villains_label' => get_string('villains_label', 'local_ascend_rewards'),
    'level' => $level,
    'levels' => $levels_context,
    'epic_levels' => $epic_levels_context,
    'hero_icon_url' => $avatar_icon_url,
    'pet_icon_url' => $pets_icon_url,
    'villain_icon_url' => $villain_icon_url,
    'font_family' => "'Uncial Antiqua', serif",
    'coin_stack_url' => $coin_stack_url,
    'coin_stack_size' => '92px',
    'coins_label' => get_string('coins_label', 'local_ascend_rewards'),
    'watch_story_label' => get_string('watch_story_label', 'local_ascend_rewards'),
    'avatar_js_config' => $avatar_js_config,
    'level_label' => get_string('level_label', 'local_ascend_rewards'),
    'locked_until_label' => get_string('locked_until_label', 'local_ascend_rewards'),
    'epic_level_label' => get_string('epic_level_label', 'local_ascend_rewards'),
    'collection_label' => get_string('collection_label', 'local_ascend_rewards'),
];

// Store context
$inventory_str = get_user_preferences('ascend_store_inventory', '', $USER->id);
$inventory = $inventory_str ? json_decode($inventory_str, true) : [];

$xp_item_price = 100;
$xp_item_can_afford = ($coin_balance >= $xp_item_price);
$xp_item_in_inventory = isset($inventory[4]) && $inventory[4] > 0;
$xp_item_inventory_count = $xp_item_in_inventory ? (int)$inventory[4] : 0;

$xp_item = [
    'id' => 4,
    'name' => get_string('xp_item_name', 'local_ascend_rewards'),
    'short_name' => get_string('xp_item_short_name', 'local_ascend_rewards'),
    'description' => get_string('xp_item_description', 'local_ascend_rewards'),
    'price' => $xp_item_price,
    'price_display' => number_format($xp_item_price),
    'icon_url' => (new moodle_url('/local/ascend_rewards/pix/ai_streak.png'))->out(false),
    'can_afford' => $xp_item_can_afford,
    'show_disabled' => (!$xp_item_can_afford && !$xp_item_in_inventory),
    'show_buy' => ($xp_item_can_afford && !$xp_item_in_inventory),
    'show_activate' => $xp_item_in_inventory,
    'inventory_count' => $xp_item_inventory_count,
    'buy_label' => get_string('xp_item_buy_label', 'local_ascend_rewards', number_format($xp_item_price)),
    'activate_label' => get_string('activate_label', 'local_ascend_rewards'),
];

$mystery_price = 50;
$mystery_box = [
    'label' => get_string('mystery_box_label', 'local_ascend_rewards'),
    'description' => get_string('mystery_box_description', 'local_ascend_rewards'),
    'price' => $mystery_price,
    'price_display' => number_format($mystery_price),
    'image_url' => (new moodle_url('/local/ascend_rewards/pix/mystery_box.png'))->out(false),
    'can_afford' => ($coin_balance >= $mystery_price),
    'open_label' => get_string('mystery_box_open_label', 'local_ascend_rewards', number_format($mystery_price)),
];

// Available pets for store
$available_pets = [];
foreach ($avatar_pets_catalog as $pet_id => $pet_data) {
    if ($pet_data['level'] > $level) {
        continue;
    }
    $avatar_unlocked = in_array($pet_data['avatar'], $unlocked_avatars);
    if (!$avatar_unlocked) {
        continue;
    }
    if (in_array($pet_id, $owned_pets)) {
        continue;
    }
    $pet_name = $pet_data['name'];
    $pet_price = (int)$pet_data['price'];
    $pet_icon_path = str_replace('pets/', '', $pet_data['icon']);
    $pet_url = (new moodle_url("/local/ascend_rewards/pix/pets/{$pet_icon_path}"))->out(false);
    $pet_avatar_circular_url = '';
    if (!empty($pet_data['avatar']) && in_array($pet_data['avatar'], $unlocked_avatars)) {
        $pet_avatar_filename = str_replace(['.png', '.jpeg'], '', $pet_data['avatar']);
        $pet_avatar_circular_url = (new moodle_url("{$avatar_circular_dir}/{$pet_avatar_filename}.png"))->out(false);
    }
    $available_pets[] = [
        'id' => $pet_id,
        'name' => $pet_name,
        'price' => $pet_price,
        'price_display' => number_format($pet_price),
        'level' => $pet_data['level'],
        'image_url' => $pet_url,
        'can_afford' => ($coin_balance >= $pet_price),
        'border_color' => '#ec4899',
        'avatar_badge_url' => $pet_avatar_circular_url ?: null,
        'aria_label' => get_string('pet_aria_label', 'local_ascend_rewards', (object) [
            'name' => $pet_name,
            'price' => number_format($pet_price),
        ]),
        'buy_label' => get_string('pet_buy_label', 'local_ascend_rewards', number_format($pet_price)),
    ];
}

// Available villains for store
$available_villains = [];
foreach ($villain_catalog as $villain_id => $villain_data) {
    if ($villain_data['level'] > $level) {
        continue;
    }
    $pet_owned = in_array($villain_data['pet_id'], $owned_pets);
    if (!$pet_owned) {
        continue;
    }
    if (in_array($villain_id, $owned_villains)) {
        continue;
    }

    $villain_name = $villain_data['name'];
    $villain_price = (int)$villain_data['price'];
    $villain_icon_path = str_replace('villains/', '', $villain_data['icon']);
    $villain_icon_name = pathinfo($villain_icon_path, PATHINFO_FILENAME);
    $villain_url = (new moodle_url("/local/ascend_rewards/pix/villains/{$villain_icon_path}"))->out(false);

    $villain_avatar_name = '';
    if (!empty($villain_data['avatar'])) {
        $villain_avatar_name = $villain_data['avatar'];
    } else {
        $villain_pet_info = $avatar_pets_catalog[$villain_data['pet_id']] ?? null;
        if ($villain_pet_info && !empty($villain_pet_info['avatar'])) {
            $villain_avatar_name = $villain_pet_info['avatar'];
        }
    }
    $villain_avatar_circular_url = '';
    if ($villain_avatar_name && in_array($villain_avatar_name, $unlocked_avatars)) {
        $villain_avatar_filename = str_replace(['.png', '.jpeg'], '', $villain_avatar_name);
        $villain_avatar_circular_url = (new moodle_url("{$avatar_circular_dir}/{$villain_avatar_filename}.png"))->out(false);
    }

    $villain_pet_badge_url = '';
    if (!empty($villain_data['pet_id'])) {
        $pet_info = $avatar_pets_catalog[$villain_data['pet_id']] ?? null;
        if ($pet_info) {
            $pet_icon = str_replace('pets/', '', $pet_info['icon']);
            $villain_pet_badge_url = (new moodle_url("/local/ascend_rewards/pix/pets/{$pet_icon}"))->out(false);
        }
    }

    $available_villains[] = [
        'id' => $villain_id,
        'name' => $villain_name,
        'price' => $villain_price,
        'price_display' => number_format($villain_price),
        'level' => $villain_data['level'] ?? 1,
        'image_url' => $villain_url,
        'icon_name' => $villain_icon_name,
        'can_afford' => ($coin_balance >= $villain_price),
        'border_color' => '#06b6d4',
        'avatar_badge_url' => $villain_avatar_circular_url ?: null,
        'pet_badge_url' => $villain_pet_badge_url ?: null,
        'aria_label' => get_string('villain_aria_label', 'local_ascend_rewards', (object) [
            'name' => $villain_name,
            'price' => number_format($villain_price),
        ]),
        'buy_label' => get_string('villain_buy_label', 'local_ascend_rewards', number_format($villain_price)),
    ];
}

$store_js_config = json_encode([
    'tokensAvailable' => $tokens_available,
    'coinBalance' => (int)$coin_balance,
    'modalStrings' => $modal_strings,
    'strings' => [
        'alertTitle' => get_string('alert_title', 'local_ascend_rewards'),
        'errorTitle' => get_string('error_title', 'local_ascend_rewards'),
        'closeLabel' => get_string('close_label', 'local_ascend_rewards'),
        'confirmTitle' => get_string('confirm_title', 'local_ascend_rewards'),
        'purchaseConfirmActionLabel' => get_string('purchase_confirm_action_label', 'local_ascend_rewards'),
        'expiredLabel' => get_string('expired_label', 'local_ascend_rewards'),
        'purchaseConfirmPrefix' => get_string('purchase_confirm_prefix', 'local_ascend_rewards'),
        'purchaseConfirmMid' => get_string('purchase_confirm_mid', 'local_ascend_rewards'),
        'purchaseConfirmSuffix' => get_string('purchase_confirm_suffix', 'local_ascend_rewards'),
        'processingLabel' => get_string('processing_label', 'local_ascend_rewards'),
        'purchaseSuccessLabel' => get_string('purchase_success_label', 'local_ascend_rewards'),
        'remainingBalanceLabel' => get_string('remaining_balance_label', 'local_ascend_rewards'),
        'errorPrefix' => get_string('error_prefix', 'local_ascend_rewards'),
        'purchaseErrorLabel' => get_string('purchase_error_label', 'local_ascend_rewards'),
        'purchaseProcessingErrorLabel' => get_string('purchase_processing_error_label', 'local_ascend_rewards'),
        'purchaseButtonPrefix' => get_string('purchase_button_prefix', 'local_ascend_rewards'),
        'activationErrorLabel' => get_string('activation_error_label', 'local_ascend_rewards'),
        'activationProcessingErrorLabel' => get_string('activation_processing_error_label', 'local_ascend_rewards'),
        'activateLabel' => get_string('activate_label', 'local_ascend_rewards'),
        'mysteryOpeningLabel' => get_string('mystery_opening_label', 'local_ascend_rewards'),
        'mysteryErrorCouldNotOpen' => get_string('mystery_error_could_not_open', 'local_ascend_rewards'),
        'mysteryErrorProcessing' => get_string('mystery_error_processing', 'local_ascend_rewards'),
        'balanceLabel' => get_string('balance_label', 'local_ascend_rewards'),
        'newBalanceLabel' => get_string('new_balance_label', 'local_ascend_rewards'),
        'tokensLabel' => get_string('tokens_label', 'local_ascend_rewards'),
        'coinsLabel' => get_string('coins_label', 'local_ascend_rewards'),
        'avatarAltLabel' => get_string('avatar_alt_label', 'local_ascend_rewards'),
        'ajaxRequestFailed' => get_string('ajax_request_failed', 'local_ascend_rewards'),
    ],
    'urls' => [
        'videoCoinsUrl' => (new moodle_url('/local/ascend_rewards/pix/coins.mp4'))->out(false),
        'videoTokensUrl' => (new moodle_url('/local/ascend_rewards/pix/token.mp4'))->out(false),
        'videoHeroUrl' => (new moodle_url('/local/ascend_rewards/pix/hero.mp4'))->out(false),
        'videoNoRewardUrl' => (new moodle_url('/local/ascend_rewards/pix/no_reward.mp4'))->out(false),
        'imgStarUrl' => (new moodle_url('/local/ascend_rewards/pix/start.png'))->out(false),
        'imgCoinsUrl' => (new moodle_url('/local/ascend_rewards/pix/ascend_assets_stack.png'))->out(false),
        'avatarCircularBaseUrl' => (new moodle_url($avatar_circular_dir . '/'))->out(false),
    ],
]);

$store_context = [
    'store_icon_url' => (new moodle_url('/local/ascend_rewards/pix/store.png'))->out(false),
    'store_label' => get_string('store_label', 'local_ascend_rewards'),
    'coin_balance_display' => number_format($coin_balance),
    'coins_available_label' => get_string('coins_available_label', 'local_ascend_rewards'),
    'xp_multiplier_active' => $xp_multiplier_active,
    'xp_multiplier_expires' => $xp_multiplier_expires,
    'xp_multiplier_active_label' => get_string('xp_multiplier_active_label', 'local_ascend_rewards'),
    'xp_multiplier_expires_label' => get_string('xp_multiplier_expires_label', 'local_ascend_rewards'),
    'powerups_title_label' => get_string('powerups_title_label', 'local_ascend_rewards'),
    'xp_item' => $xp_item,
    'mystery_box' => $mystery_box,
    'coin_icon_url' => (new moodle_url('/local/ascend_rewards/pix/ascend_coin_main.png'))->out(false),
    'coins_label' => get_string('coins_label', 'local_ascend_rewards'),
    'not_enough_coins_label' => get_string('not_enough_coins_label', 'local_ascend_rewards'),
    'available_label' => get_string('available_label', 'local_ascend_rewards'),
    'pets_icon_url' => $pets_icon_url,
    'pets_label' => get_string('pets_label', 'local_ascend_rewards'),
    'no_pets' => empty($available_pets),
    'no_pets_title' => get_string('no_pets_title', 'local_ascend_rewards'),
    'no_pets_description' => get_string('no_pets_description', 'local_ascend_rewards'),
    'no_pets_tip' => get_string('no_pets_tip', 'local_ascend_rewards'),
    'empty_state_icon' => get_string('empty_state_icon', 'local_ascend_rewards'),
    'pets' => $available_pets,
    'villain_icon_url' => $villain_icon_url,
    'villains_label' => get_string('villains_label', 'local_ascend_rewards'),
    'no_villains' => empty($available_villains),
    'no_villains_title' => get_string('no_villains_title', 'local_ascend_rewards'),
    'no_villains_description' => get_string('no_villains_description', 'local_ascend_rewards'),
    'no_villains_tip' => get_string('no_villains_tip', 'local_ascend_rewards'),
    'villains' => $available_villains,
    'level_label' => get_string('level_label', 'local_ascend_rewards'),
    'pet_label' => get_string('pet_label', 'local_ascend_rewards'),
    'villain_label' => get_string('villain_label', 'local_ascend_rewards'),
    'hero_label' => get_string('hero_label', 'local_ascend_rewards'),
    'linked_hero_label' => get_string('linked_hero_label', 'local_ascend_rewards'),
    'linked_pet_label' => get_string('linked_pet_label', 'local_ascend_rewards'),
    'coin_stack_url' => $coin_stack_url,
    'box_label_1' => get_string('box_label_1', 'local_ascend_rewards'),
    'box_label_2' => get_string('box_label_2', 'local_ascend_rewards'),
    'box_label_3' => get_string('box_label_3', 'local_ascend_rewards'),
    'box_label_4' => get_string('box_label_4', 'local_ascend_rewards'),
    'continue_label' => get_string('continue_label', 'local_ascend_rewards'),
    'store_js_config' => $store_js_config,
];

$badge_videos = [
    6 => 'Getting Started/getting_started_2.mp4',
    5 => 'Halfway Hero/halfway_hero_1.mp4',
    8 => 'Master Navigator/master_navigator_3.mp4',
    13 => 'Feedback Follower/feedback_follower_1.mp4',
    15 => 'Steady Improver/steady_improver_1.mp4',
    14 => 'Tenacious Tiger/tenacious_tiger_1.mp4',
    16 => 'Glory Guide/glory_guide_3.mp4',
];

$index_js_config = json_encode([
    'alerts' => [
        'alertTitle' => get_string('alert_title', 'local_ascend_rewards'),
        'errorTitle' => get_string('error_title', 'local_ascend_rewards'),
        'closeLabel' => get_string('close_label', 'local_ascend_rewards'),
        'ajaxRequestFailed' => get_string('ajax_request_failed', 'local_ascend_rewards'),
    ],
    'gameboard' => [
        'successText' => get_string('gameboard_success_text', 'local_ascend_rewards'),
        'errorPrefix' => get_string('gameboard_error_prefix', 'local_ascend_rewards'),
        'genericError' => get_string('gameboard_generic_error', 'local_ascend_rewards'),
        'processingErrorPrefix' => get_string('gameboard_processing_error_prefix', 'local_ascend_rewards'),
        'coinAltLabel' => get_string('coin_label_singular', 'local_ascend_rewards'),
        'coinIconUrl' => (new moodle_url('/local/ascend_rewards/pix/ascend_coin_main.png'))->out(false),
    ],
    'badgeModal' => [
        'badgeVideos' => $badge_videos,
        'metaBadgeIds' => [8, 16],
        'knownBadgeNames' => array_keys($badge_definitions),
        'moreActivitiesTemplate' => str_replace('{$a}', '{count}', get_string('more_activities_label', 'local_ascend_rewards')),
        'awardLabelPrefix' => get_string('award_number_label_prefix', 'local_ascend_rewards'),
        'awardLabelSuffix' => get_string('award_number_label_suffix', 'local_ascend_rewards'),
        'xpLabel' => get_string('xp_label', 'local_ascend_rewards'),
        'coinsLabel' => get_string('coins_label', 'local_ascend_rewards'),
        'assetsLabel' => get_string('assets_label', 'local_ascend_rewards'),
        'badgeLabel' => get_string('badge_label', 'local_ascend_rewards'),
        'badgeCategoryDefault' => get_string('badge_category_default', 'local_ascend_rewards'),
        'failedLabel' => get_string('failed_label', 'local_ascend_rewards'),
        'passedLabel' => get_string('passed_label', 'local_ascend_rewards'),
    ],
    'leaderboard' => [
        'courseId' => (int)($courseid ?? 0),
        'userId' => (int)$USER->id,
        'leaderboardRangeLabel' => get_string('leaderboard_range_label', 'local_ascend_rewards'),
        'leaderboardModeLabel' => '(' . $leaderboard_mode_label . ')',
        'leaderboardLoadingLabel' => get_string('leaderboard_loading_label', 'local_ascend_rewards'),
        'leaderboardContextErrorLabel' => get_string('leaderboard_context_error_label', 'local_ascend_rewards'),
        'leaderboardContextViewErrorLabel' => get_string('leaderboard_context_view_error_label', 'local_ascend_rewards'),
        'youLabel' => $you_label,
        'userNumberPrefix' => $user_number_prefix,
        'userIdBadgeLabel' => get_string('user_id_badge_label', 'local_ascend_rewards'),
    ],
    'instructions' => [
        'windowName' => 'ascend_instructions',
        'windowFeatures' => 'width=1600,height=900,scrollbars=yes,resizable=yes',
    ],
]);

$templatecontext = [
    'instructions_url' => (new moodle_url('/local/ascend_rewards/instructions.php'))->out(false),
    'instructions_icon_url' => (new moodle_url('/local/ascend_rewards/pix/instructions.png'))->out(false),
    'instructions_text' => get_string('instructions_text', 'local_ascend_rewards'),
    'show_congrats' => ($has_earned || $show_level_up),
    'reward_video_url' => (new moodle_url('/local/ascend_rewards/pix/reward_animation.mp4'))->out(false),
    'congrats_title' => $show_level_up
        ? get_string('congrats_title_levelup', 'local_ascend_rewards', $level)
        : get_string('congrats_title_newbadge', 'local_ascend_rewards'),
    'congrats_subtext' => get_string('congrats_subtext', 'local_ascend_rewards'),
    'coinimgurl' => $coinimgurl,
    'coinimg_fallback_url' => (new moodle_url('/local/ascend_rewards/pix/ascend_coin_main.png'))->out(false),
    'current_balance_label' => get_string('current_balance_label', 'local_ascend_rewards'),
    'coin_balance_display' => number_format($coin_balance),
    'assets_label' => get_string('assets_label', 'local_ascend_rewards'),
    'stackimgurl' => $stackimgurl,
    'stackimg_fallback_url' => (new moodle_url('/local/ascend_rewards/pix/ascend_assets_stack.png'))->out(false),
    'current_rank_label' => get_string('current_rank_label', 'local_ascend_rewards'),
    'rank_display' => $myrank !== null
        ? get_string('rank_display', 'local_ascend_rewards', (object) [
            'rank' => (int)$myrank,
            'total' => (int)$totalearners,
        ])
        : get_string('rank_not_ranked', 'local_ascend_rewards'),
    'filter_by_course_label' => get_string('filter_by_course_label', 'local_ascend_rewards'),
    'all_courses_label' => get_string('all_courses_label', 'local_ascend_rewards'),
    'courseoptions' => $courseoptions_list,
    'apply_label' => get_string('apply_label', 'local_ascend_rewards'),
    'has_course_filter' => !is_null($courseid),
    'reset_url' => (new moodle_url('/local/ascend_rewards/index.php'))->out(false),
    'reset_label' => get_string('reset_label', 'local_ascend_rewards'),
    'medal_gold_url' => $medal_gold_url,
    'medal_silver_url' => $medal_silver_url,
    'medal_bronze_url' => $medal_bronze_url,
    'coins_earned_label' => get_string('coins_earned_label', 'local_ascend_rewards'),
    'coins_earned_display' => number_format($courseid ? $balance_course : $totalcoins),
    'in_this_course_label' => get_string('in_this_course_label', 'local_ascend_rewards'),
    'badges_earned_label' => get_string('badges_earned_label', 'local_ascend_rewards'),
    'badges_earned_display' => number_format($totalbadges),
    'ranking_label' => get_string('ranking_label', 'local_ascend_rewards'),
    'show_level_up' => $show_level_up,
    'xp_final_offset' => 226 * (1 - ($xp_percent / 100)),
    'level' => $level,
    'xp_display' => number_format($xp),
    'xp_label' => get_string('xp_label', 'local_ascend_rewards'),
    'level_label' => get_string('level_label', 'local_ascend_rewards'),
    'icon_leaderboard_url' => $icon_leaderboard_url,
    'leaderboard_label' => get_string('leaderboard', 'local_ascend_rewards'),
    'leaderboard_mode_label' => $leaderboard_mode_label,
    'banner_icon_text' => get_string('banner_icon_text', 'local_ascend_rewards'),
    'your_id_label' => get_string('your_id_label', 'local_ascend_rewards'),
    'you_label' => $you_label,
    'user_number_prefix' => $user_number_prefix,
    'user_id' => (int)$USER->id,
    'has_rank' => ($myrank !== null),
    'rank_label' => get_string('rank_label', 'local_ascend_rewards'),
    'rank_value' => (int)$myrank,
    'of_label' => get_string('of_label', 'local_ascend_rewards'),
    'total_learners' => (int)$totalearners,
    'learners_label' => get_string('learners_label', 'local_ascend_rewards'),
    'leaderboard_context_label' => $leaderboard_context_label,
    'top10_label' => get_string('leaderboard_top10_label', 'local_ascend_rewards'),
    'my_position_label' => get_string('my_position_label', 'local_ascend_rewards'),
    'leaderboard_empty_label' => get_string('leaderboard_empty_label', 'local_ascend_rewards'),
    'top10' => $top10_list,
    'current_user_entry' => $current_user_entry,
    'icon_badges_course_url' => $icon_badges_course_url,
    'no_badges_label' => get_string('no_badges_label', 'local_ascend_rewards'),
    'badges_by_course' => $badges_by_course,
    'icon_journey_url' => $icon_journey_url,
    'course_journey_label' => get_string('course_journey_label', 'local_ascend_rewards'),
    'show_journeys' => $show_journeys,
    'no_journeys_label' => get_string('no_journeys_label', 'local_ascend_rewards'),
    'journeys' => $journeys_context,
    'activities_completed_label' => get_string('activities_completed_label', 'local_ascend_rewards'),
    'progress_label' => get_string('progress_label', 'local_ascend_rewards'),
    'weekly_gameboard_label' => get_string('weekly_gameboard_label', 'local_ascend_rewards'),
    'picks_available_label' => get_string('picks_available_label', 'local_ascend_rewards'),
    'normal_badges_label' => get_string('normal_badges_label', 'local_ascend_rewards'),
    'normal_picks_label' => get_string('normal_picks_label', 'local_ascend_rewards'),
    'meta_badges_label' => get_string('meta_badges_label', 'local_ascend_rewards'),
    'meta_picks_label' => get_string('meta_picks_label', 'local_ascend_rewards'),
    'gameboard' => $gameboard,
    'gameboard_locked_label' => get_string('gameboard_locked_label', 'local_ascend_rewards'),
    'level_up_audio_url' => (new moodle_url('/local/ascend_rewards/pix/level_up.mp3'))->out(false),
    'stack_fallback_url' => $stack_fallback->out(false),
    'avatar' => $avatar_context,
    'store' => $store_context,
    'index_js_config' => $index_js_config,
];

/** ------------------------------------------------------------------------
 *  RENDER PAGE
 *  --------------------------------------------------------------------- */
/** Render Page */
echo $OUTPUT->header();

echo $OUTPUT->render_from_template('local_ascend_rewards/index', $templatecontext);

echo $OUTPUT->footer();
