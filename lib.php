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
 * Library functions for Ascend Rewards plugin.
 *
 * Contains callback functions for navigation, rendering, and plugin functionality.
 *
 * @package   local_ascend_rewards
 * @copyright 2026 Elantis (Pty) LTD
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// phpcs:disable moodle.Files.MoodleInternal.MoodleInternalNotNeeded
defined('MOODLE_INTERNAL') || die();

// Preserve behavior while suppressing style sniffs that would require broad renaming.
// phpcs:disable moodle.NamingConventions.ValidVariableName.VariableNameUnderscore
// phpcs:disable moodle.Commenting.InlineComment.InvalidEndChar,moodle.Commenting.InlineComment.NotCapital
// phpcs:disable moodle.Files.LineLength.MaxExceeded,moodle.Files.LineLength.TooLong
// phpcs:disable moodle.WhiteSpace.WhiteSpaceInStrings.EndLine
// phpcs:disable Generic.CodeAnalysis.EmptyStatement.DetectedCatch

/**
 * Ensure the notifications AMD module is loaded when modals are rendered.
 */
function local_ascend_rewards_require_notifications_js(): void {
    global $PAGE;
    if (isset($PAGE) && method_exists($PAGE, 'requires')) {
        $PAGE->requires->js_call_amd('local_ascend_rewards/notifications', 'init');
    }
}

/**
 * Build badge notification HTML for hook output.
 *
 * @return string HTML to inject at top of body
 */
function local_ascend_rewards_build_badge_notification_html() {
    global $USER, $OUTPUT;

    if (!isloggedin() || isguestuser()) {
        return '';
    }

    // Priority 1: Check for level-up notifications first (show after badges)
    $levelup_output = local_ascend_rewards_show_levelup_modal();

    // Priority 2: Check for badge notifications
    $pending = get_user_preferences('ascend_pending_notifications', '', $USER->id);
    if (empty($pending)) {
        return $levelup_output; // Return level-up modal if no badge notifications
    }

    $notifications = json_decode($pending, true);
    if (!is_array($notifications) || empty($notifications)) {
        return $levelup_output;
    }

    // Filter notifications to the current gameboard week so celebration
    // and gameboard picks follow the same Friday-Thursday window.
    $now = time();
    $weekstart = $now - (7 * DAYSECS);
    $weekend = $now;
    try {
        [$weekstart, $weekend] = local_ascend_rewards_gameboard::get_week_range();
    } catch (\Throwable $e) {
        // Fallback to last 7 days when the gameboard helper is unavailable.
    }
    $notifications = array_filter($notifications, function ($notif) use ($weekstart, $weekend) {
        $timestamp = $notif['timestamp'] ?? 0;
        return ($timestamp >= $weekstart && $timestamp <= $weekend);
    });

    if (empty($notifications)) {
        unset_user_preference('ascend_pending_notifications', $USER->id);
        return $levelup_output;
    }

    // Get the first notification
    $notification = array_shift($notifications);

    // Save remaining notifications
    if (empty($notifications)) {
        unset_user_preference('ascend_pending_notifications', $USER->id);
    } else {
        set_user_preference('ascend_pending_notifications', json_encode($notifications), $USER->id);
    }

    // Prepare data for JavaScript
    $badgename = s($notification['badgename']);
    $badgeid = (int)($notification['badgeid'] ?? 0);
    $coins = (int)($notification['coins'] ?? 0);
    $xp = isset($notification['xp']) ? (int)$notification['xp'] : (int)floor($coins / 2);
    $coursename = s($notification['coursename'] ?? get_string('unknown_course_label', 'local_ascend_rewards'));
    $activities = $notification['activities'] ?? [];
    $rank = isset($notification['rank']) ? (int)$notification['rank'] : 0;
    $total_users = isset($notification['total_users']) ? (int)$notification['total_users'] : 0;
    $rank_change = $notification['rank_change'] ?? null;
    $badge_description = s($notification['description'] ?? get_string('badge_desc_default', 'local_ascend_rewards'));
    $badge_category = s($notification['category'] ?? get_string('badge_category_default', 'local_ascend_rewards'));
    $timestamp = isset($notification['timestamp']) ? (int)$notification['timestamp'] : time();
    $date_earned = userdate($timestamp, get_string('strftimedate', 'langconfig'));
    $rewardsurl = (new moodle_url('/local/ascend_rewards/index.php'))->out(false);

    // Map badges to video files with specific animations for each badge
    $badge_videos = [
        // Progress-Based badges (pink theme)
        6  => 'Getting Started/getting_started_2.mp4', // Getting Started
        4  => 'On a Roll/on_a_roll_2.mp4', // On a Roll
        5  => 'Halfway Hero/halfway_hero_1.mp4', // Halfway Hero
        8  => 'Master Navigator/master_navigator_3.mp4', // Master Navigator (meta)

        // Timeliness & Discipline (cyan theme)
        9  => 'Early Bird/early_bird_1.mp4', // Early Bird
        11 => 'Sharp Shooter/sharp_shooter_1.mp4', // Sharp Shooter
        10 => 'Deadline Burner/deadline_burner_1.mp4', // Deadline Burner
        12 => 'Time Tamer/time_tamer_4.mp4', // Time Tamer (meta)

        // Quality & Growth (orange theme)
        13 => 'Feedback Follower/feedback_follower_1.mp4', // Feedback Follower
        15 => 'Steady Improver/steady_improver_1.mp4', // Steady Improver
        14 => 'Tenacious Tiger/tenacious_tiger_1.mp4', // Tenacious Tiger
        16 => 'Glory Guide/glory_guide_3.mp4', // Glory Guide (meta)

        // Course Mastery (purple theme)
        19 => 'High Flyer/high_flyer_3.mp4', // High Flyer
        17 => 'Activity Ace/activity_ace_3.mp4', // Activity Ace
        7  => 'Mission Complete/mission_complete_1.mp4', // Mission Complete
        20 => 'Learning Legend/learning_legend_5.mp4', // Learning Legend (super meta)
    ];

    // Get video filename for this badge, fallback to default
    $video_filename = $badge_videos[$badgeid] ?? 'reward_animation_2.mp4';
    $videourl = (new moodle_url('/local/ascend_rewards/pix/' . $video_filename))->out(false);
    $medalurl = (new moodle_url('/local/ascend_rewards/pix/medal_gold.png'))->out(false);

    $badge_earned_title = get_string('badge_earned_title', 'local_ascend_rewards');
    $badge_earned_subtitle = get_string('badge_earned_subtitle', 'local_ascend_rewards');
    $close_label = get_string('close_label', 'local_ascend_rewards');
    $fullscreen_label = get_string('fullscreen_label', 'local_ascend_rewards');
    $badge_label = get_string('badge_label', 'local_ascend_rewards');
    $xp_default_display = get_string('xp_default_display', 'local_ascend_rewards');
    $xp_label = get_string('xp_label', 'local_ascend_rewards');
    $experience_points_label = get_string('experience_points_label', 'local_ascend_rewards');
    $assets_label = get_string('assets_label', 'local_ascend_rewards');
    $coins_label = get_string('coins_label', 'local_ascend_rewards');
    $medal_label = get_string('medal_label', 'local_ascend_rewards');
    $course_label = get_string('course_label', 'local_ascend_rewards');
    $earned_on_label = get_string('earned_on_label', 'local_ascend_rewards');
    $achievement_details_label = get_string('achievement_details_label', 'local_ascend_rewards');
    $qualifying_activities_label = get_string('qualifying_activities_label', 'local_ascend_rewards');
    $view_my_progress_label = get_string('view_my_progress_label', 'local_ascend_rewards');
    $rank_label = get_string('rank_label', 'local_ascend_rewards');
    $of_label = get_string('of_label', 'local_ascend_rewards');

    $activity_items = [];
    $activities_more_label = '';
    if (!empty($activities)) {
        $activity_count = 0;
        foreach ($activities as $activity) {
            if ($activity_count >= 10) {
                $remaining = count($activities) - $activity_count;
                $activities_more_label = get_string('more_activities_label', 'local_ascend_rewards', $remaining);
                break;
            }
            $activity_items[] = s($activity);
            $activity_count++;
        }
    }

    $rank_change_class = '';
    $rank_change_text = '';
    if ($rank > 0 && $total_users > 0 && $rank_change !== null && $rank_change != 0) {
        if ($rank_change > 0) {
            $rank_change_class = 'up';
            $rank_change_text = '  +' . abs($rank_change);
        } else if ($rank_change < 0) {
            $rank_change_class = 'down';
            $rank_change_text = '  ' . abs($rank_change);
        }
    }

    $templatecontext = [
        'badge_earned_title' => $badge_earned_title,
        'badge_earned_subtitle' => $badge_earned_subtitle,
        'close_label' => $close_label,
        'fullscreen_label' => $fullscreen_label,
        'videourl' => $videourl,
        'badgename' => $badgename,
        'badge_category' => $badge_category,
        'badge_label' => $badge_label,
        'xp' => $xp,
        'xp_label' => $xp_label,
        'experience_points_label' => $experience_points_label,
        'medalurl' => $medalurl,
        'medal_label' => $medal_label,
        'coins' => $coins,
        'coins_label' => $coins_label,
        'assets_label' => $assets_label,
        'course_label' => $course_label,
        'coursename' => $coursename,
        'earned_on_label' => $earned_on_label,
        'date_earned' => $date_earned,
        'achievement_details_label' => $achievement_details_label,
        'badge_description' => $badge_description,
        'has_activities' => !empty($activity_items) || $activities_more_label !== '',
        'qualifying_activities_label' => $qualifying_activities_label,
        'activities_items' => $activity_items,
        'activities_more_label' => $activities_more_label,
        'has_rank' => ($rank > 0 && $total_users > 0),
        'rank_label' => $rank_label,
        'rank' => $rank,
        'of_label' => $of_label,
        'total_users' => $total_users,
        'rank_change_text' => $rank_change_text,
        'rank_change_class' => $rank_change_class,
        'view_my_progress_label' => $view_my_progress_label,
        'rewardsurl' => $rewardsurl,
    ];

    $output = $OUTPUT->render_from_template('local_ascend_rewards/notification_badge', $templatecontext);

    return $output . $levelup_output;
}

/**
 * Generate level-up modal HTML if there are pending level-ups.
 *
 * @return string HTML for level-up modal or empty string
 */
function local_ascend_rewards_show_levelup_modal() {
    global $USER, $OUTPUT;

    if (!isloggedin() || isguestuser()) {
        return '';
    }

    // Check for pending level-up notifications
    $pending = get_user_preferences('ascend_pending_levelups', '', $USER->id);
    if (empty($pending)) {
        return '';
    }

    $levelups = json_decode($pending, true);
    if (!is_array($levelups) || empty($levelups)) {
        return '';
    }

    // Filter out stale level-ups (older than 7 days)
    $now = time();
    $levelups = array_filter($levelups, function ($lvl) use ($now) {
        $timestamp = $lvl['timestamp'] ?? 0;
        return ($now - $timestamp) <= (7 * DAYSECS);
    });

    if (empty($levelups)) {
        unset_user_preference('ascend_pending_levelups', $USER->id);
        return '';
    }

    // Get the first level-up notification
    $levelup = array_shift($levelups);

    // Save remaining level-up notifications
    if (empty($levelups)) {
        unset_user_preference('ascend_pending_levelups', $USER->id);
    } else {
        set_user_preference('ascend_pending_levelups', json_encode($levelups), $USER->id);
    }

    $level = (int)($levelup['level'] ?? 1);

    // Ensure level is between 1 and 10
    if ($level < 1 || $level > 10) {
        return '';
    }

    // Get video URL for this level
    $video_filename = "Level Up/Level_{$level}.mp4";
    $videourl = (new moodle_url('/local/ascend_rewards/pix/' . $video_filename))->out(false);
    $soundurl = (new moodle_url('/local/ascend_rewards/pix/level_up.mp3'))->out(false);

    $levelup_title = get_string('levelup_modal_title', 'local_ascend_rewards');
    $levelup_subtitle = get_string('levelup_modal_subtitle', 'local_ascend_rewards');
    $close_label = get_string('close_label', 'local_ascend_rewards');
    $level_label_upper = strtoupper(get_string('level_label', 'local_ascend_rewards'));

    $templatecontext = [
        'close_label' => $close_label,
        'levelup_title' => $levelup_title,
        'videourl' => $videourl,
        'level_label_upper' => $level_label_upper,
        'level' => $level,
        'levelup_subtitle' => $levelup_subtitle,
        'soundurl' => $soundurl,
    ];

    return $OUTPUT->render_from_template('local_ascend_rewards/notification_levelup', $templatecontext);
}
