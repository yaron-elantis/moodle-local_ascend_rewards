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
 * Admin dashboard page for Ascend Rewards.
 *
 * @package   local_ascend_rewards
 * @copyright 2026 Elantis (Pty) LTD
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
defined('MOODLE_INTERNAL') || die();
require_login();
// This page renders large inline HTML blocks.
// phpcs:disable moodle.Files.LineLength.MaxExceeded,moodle.Files.LineLength.TooLong
// phpcs:disable moodle.NamingConventions.ValidVariableName.VariableNameUnderscore
// phpcs:disable Generic.WhiteSpace.ScopeIndent.Incorrect,Generic.WhiteSpace.ScopeIndent.IncorrectExact
// phpcs:disable moodle.Commenting.InlineComment.InvalidEndChar,moodle.Commenting.InlineComment.NotCapital
// phpcs:disable moodle.Commenting.MissingDocblock.File
// phpcs:disable moodle.WhiteSpace.WhiteSpaceInStrings.EndLine
// phpcs:disable PSR2.ControlStructures.SwitchDeclaration.TerminatingComment
$context = context_system::instance();
require_capability('local/ascend_rewards:manage', $context);
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/ascend_rewards/admin_dashboard.php'));
$admintitle = get_string('admin_dashboard_title', 'local_ascend_rewards');
$PAGE->set_title($admintitle);
$PAGE->set_heading($admintitle);

// Define available tabs
$tabs = [
    'metrics' => get_string('admin_tab_metrics', 'local_ascend_rewards'),
    'users' => get_string('admin_tab_users', 'local_ascend_rewards'),
    'award' => get_string('admin_tab_award', 'local_ascend_rewards'),
    'gifts' => get_string('admin_tab_gifts', 'local_ascend_rewards'),
    'cleanup' => get_string('admin_tab_cleanup', 'local_ascend_rewards'),
    'debug' => get_string('admin_tab_debug', 'local_ascend_rewards'),
    'audit' => get_string('admin_tab_audit', 'local_ascend_rewards'),
];

// Get current tab from URL parameter, default to 'metrics'
$tab = optional_param('tab', 'metrics', PARAM_ALPHA);
if (!array_key_exists($tab, $tabs)) {
    $tab = 'metrics';
}

// Badge definitions for consistent naming
$badge_definitions = [
    6  => 'badge_name_getting_started',
    5  => 'badge_name_halfway_hero',
    8  => 'badge_name_master_navigator',
    13 => 'badge_name_feedback_follower',
    15 => 'badge_name_steady_improver',
    14 => 'badge_name_tenacious_tiger',
    16 => 'badge_name_glory_guide',
];
// Map badge IDs to names for lookup
$badge_names = [];
foreach ($badge_definitions as $id => $stringkey) {
    $badge_names[$id] = get_string($stringkey, 'local_ascend_rewards');
}

$badge_categories_js = [
    6 => get_string('admin_badge_category_progress', 'local_ascend_rewards'),
    4 => get_string('admin_badge_category_progress', 'local_ascend_rewards'),
    5 => get_string('admin_badge_category_progress', 'local_ascend_rewards'),
    8 => get_string('admin_badge_category_progress', 'local_ascend_rewards'),
    9 => get_string('admin_badge_category_timeliness', 'local_ascend_rewards'),
    11 => get_string('admin_badge_category_timeliness', 'local_ascend_rewards'),
    10 => get_string('admin_badge_category_timeliness', 'local_ascend_rewards'),
    12 => get_string('admin_badge_category_timeliness', 'local_ascend_rewards'),
    13 => get_string('admin_badge_category_quality', 'local_ascend_rewards'),
    15 => get_string('admin_badge_category_quality', 'local_ascend_rewards'),
    14 => get_string('admin_badge_category_quality', 'local_ascend_rewards'),
    16 => get_string('admin_badge_category_quality', 'local_ascend_rewards'),
    19 => get_string('admin_badge_category_quality', 'local_ascend_rewards'),
    17 => get_string('admin_badge_category_mastery', 'local_ascend_rewards'),
    7 => get_string('admin_badge_category_mastery', 'local_ascend_rewards'),
    20 => get_string('admin_badge_category_mastery', 'local_ascend_rewards'),
];
$badge_descriptions_js = [
    6 => get_string('admin_badge_desc_getting_started', 'local_ascend_rewards'),
    4 => get_string('admin_badge_desc_on_a_roll', 'local_ascend_rewards'),
    5 => get_string('admin_badge_desc_halfway_hero', 'local_ascend_rewards'),
    8 => get_string('admin_badge_desc_master_navigator', 'local_ascend_rewards'),
    9 => get_string('admin_badge_desc_early_bird', 'local_ascend_rewards'),
    11 => get_string('admin_badge_desc_sharp_shooter', 'local_ascend_rewards'),
    10 => get_string('admin_badge_desc_deadline_burner', 'local_ascend_rewards'),
    12 => get_string('admin_badge_desc_time_tamer', 'local_ascend_rewards'),
    13 => get_string('admin_badge_desc_feedback_follower', 'local_ascend_rewards'),
    15 => get_string('admin_badge_desc_steady_improver', 'local_ascend_rewards'),
    14 => get_string('admin_badge_desc_tenacious_tiger', 'local_ascend_rewards'),
    16 => get_string('admin_badge_desc_glory_guide', 'local_ascend_rewards'),
    19 => get_string('admin_badge_desc_high_flyer', 'local_ascend_rewards'),
    17 => get_string('admin_badge_desc_activity_ace', 'local_ascend_rewards'),
    7 => get_string('admin_badge_desc_mission_complete', 'local_ascend_rewards'),
    20 => get_string('admin_badge_desc_learning_legend', 'local_ascend_rewards'),
];
$known_badge_names_js = [
    get_string('badge_name_getting_started', 'local_ascend_rewards'),
    get_string('badge_name_on_a_roll', 'local_ascend_rewards'),
    get_string('badge_name_halfway_hero', 'local_ascend_rewards'),
    get_string('badge_name_early_bird', 'local_ascend_rewards'),
    get_string('badge_name_sharp_shooter', 'local_ascend_rewards'),
    get_string('badge_name_deadline_burner', 'local_ascend_rewards'),
    get_string('badge_name_feedback_follower', 'local_ascend_rewards'),
    get_string('badge_name_steady_improver', 'local_ascend_rewards'),
    get_string('badge_name_tenacious_tiger', 'local_ascend_rewards'),
    get_string('badge_name_high_flyer', 'local_ascend_rewards'),
    get_string('badge_name_activity_ace', 'local_ascend_rewards'),
    get_string('badge_name_mission_complete', 'local_ascend_rewards'),
];
$badge_videos = [
    6 => 'Getting Started/getting_started_2.mp4',
    4 => 'On a Roll/on_a_roll_2.mp4',
    5 => 'Halfway Hero/halfway_hero_1.mp4',
    8 => 'Master Navigator/master_navigator_3.mp4',
    9 => 'Early Bird/early_bird_1.mp4',
    11 => 'Sharp Shooter/sharp_shooter_1.mp4',
    10 => 'Deadline Burner/deadline_burner_1.mp4',
    12 => 'Time Tamer/time_tamer_4.mp4',
    13 => 'Feedback Follower/feedback_follower_1.mp4',
    15 => 'Steady Improver/steady_improver_1.mp4',
    14 => 'Tenacious Tiger/tenacious_tiger_1.mp4',
    16 => 'Glory Guide/glory_guide_3.mp4',
    19 => 'High Flyer/high_flyer_3.mp4',
    17 => 'Assessment Ace/activity_ace_3.mp4',
    7 => 'Mission Complete/mission_complete_1.mp4',
    20 => 'Learning Legend/learning_legend_5.mp4',
];

$admin_js_config = [
    'alerts' => [
        'alertTitle' => get_string('alert_title', 'local_ascend_rewards'),
        'errorTitle' => get_string('error_title', 'local_ascend_rewards'),
        'closeLabel' => get_string('close_label', 'local_ascend_rewards'),
        'ajaxRequestFailed' => get_string('ajax_request_failed', 'local_ascend_rewards'),
    ],
    'badgePreview' => [
        'badgeVideos' => $badge_videos,
        'badgeCategories' => $badge_categories_js,
        'badgeDescriptions' => $badge_descriptions_js,
        'knownBadgeNames' => $known_badge_names_js,
        'metaBadgeIds' => [8, 12, 16, 20],
        'moreActivitiesTemplate' => str_replace('{$a}', '{count}', get_string('more_activities_label', 'local_ascend_rewards')),
        'badgePreviewFallback' => get_string('admin_badge_preview_fallback', 'local_ascend_rewards'),
        'congratsTemplate' => str_replace('{$a}', '{badge}', get_string('admin_congrats_earned_badge', 'local_ascend_rewards')),
        'xpLabel' => get_string('xp_label', 'local_ascend_rewards'),
        'assetsLabel' => get_string('assets_label', 'local_ascend_rewards'),
        'badgeLabel' => get_string('badge_label', 'local_ascend_rewards'),
        'badgeCategoryDefault' => get_string('badge_category_default', 'local_ascend_rewards'),
        'sampleCourseLabel' => get_string('admin_sample_course_label', 'local_ascend_rewards'),
        'selectBadgeAlert' => get_string('admin_select_badge_alert', 'local_ascend_rewards'),
    ],
    'awardForm' => [
        'awardCourseError' => get_string('admin_award_select_course_error', 'local_ascend_rewards'),
        'awardBadgeError' => get_string('admin_award_select_badge_error', 'local_ascend_rewards'),
    ],
];

$PAGE->requires->data_for_js('local_ascend_rewards_admin_dashboard', $admin_js_config);
$PAGE->requires->js_call_amd('local_ascend_rewards/admin_dashboard', 'init');

echo $OUTPUT->header();
// Data for the template wrapper (header, banner, tabs).
$reward_video_url = (new moodle_url('/local/ascend_rewards/pix/reward_animation.mp4'))->out(false);
$congrats_title = s(get_string('admin_congrats_title', 'local_ascend_rewards'));
$congrats_subtext = s(get_string('admin_congrats_subtext', 'local_ascend_rewards'));
$tabs_context = [];
foreach ($tabs as $key => $label) {
    $tabs_context[] = [
        'url' => (new moodle_url('/local/ascend_rewards/admin_dashboard.php', ['tab' => $key]))->out(false),
        'label' => $label,
        'active' => ($tab === $key),
    ];
}

// Store POST messages to display after rendering header/tabs
$post_message = '';
$post_message_type = '';
$handled_post = false;
$idpattern = '/\((?:[^)]*?)(\d+)\)\s*$/';

// Handle POST requests for award/revoke/gift from any tab
$ispost = data_submitted();
$action = optional_param('action', '', PARAM_ALPHANUMEXT);
if ($ispost) {
    global $DB;
    require_sesskey();



    // Extract userid and amount for gift actions
    $userid_str = optional_param('userid', '', PARAM_TEXT);
    $amount = optional_param('amount', 0, PARAM_INT);

    // Parse userid from "Name (ID: X)" format
    $userid = 0;
    if (!empty($userid_str)) {
        if (preg_match($idpattern, $userid_str, $matches)) {
            $userid = (int)$matches[1];
        } else if (is_numeric($userid_str)) {
            $userid = (int)$userid_str;
        }
    }

    $handled_actions = ['gift_coins', 'gift_tokens', 'award', 'revoke'];
    if (in_array($action, $handled_actions, true)) {
        $handled_post = true;
    }

    $giftactions = ['gift_coins', 'gift_tokens'];
    if (in_array($action, $giftactions, true)) {
        $post_message = get_string('pro_version_only_message', 'local_ascend_rewards');
        $post_message_type = 'error';
    } else if (($action === 'gift_coins' || $action === 'gift_tokens') && (!$userid || $amount <= 0)) {
        $post_message = get_string('admin_error_invalid_user_amount', 'local_ascend_rewards');
        $post_message_type = 'error';
    } else if ($action === 'gift_coins' || $action === 'gift_tokens') {
            // Get user's full name for display
            $user = $DB->get_record('user', ['id' => $userid], 'id,firstname,lastname,firstnamephonetic,lastnamephonetic,middlename,alternatename');
            $username = $user ? htmlspecialchars(fullname($user)) : get_string('user_number_label', 'local_ascend_rewards', $userid);

        if ($action === 'gift_coins') {
            $coinlabel = $amount === 1
                ? get_string('coin_label_singular', 'local_ascend_rewards')
                : get_string('coin_label_plural', 'local_ascend_rewards');
            // Gift coins
            try {
                $record = (object)[
                    'userid' => $userid,
                    'badgeid' => 0, // Special badge ID for admin gifts
                    'courseid' => 0, // Site-wide gift
                    'coins' => $amount,
                    'xp' => 0, // Admin gifts don't award XP
                    'timecreated' => time(),
                    'awarded_by' => $USER->id, // Track who made the award
                ];
                $insert_id = $DB->insert_record('local_ascend_rewards_coins', $record);

                if (!$insert_id) {
                    $post_message = get_string('admin_error_gift_coins_save', 'local_ascend_rewards');
                    $post_message_type = 'error';
                } else {
                    // Log the gift with awarding user
                    $DB->insert_record('local_ascend_rewards_badgerlog', (object)[
                        'userid' => $userid,
                        'badgeid' => 0,
                        'status' => 'admin_gift_coins',
                        'message' => get_string('admin_log_gift_coins', 'local_ascend_rewards', (object)[
                            'amount' => $amount,
                            'coinlabel' => $coinlabel,
                        ]),
                        'timecreated' => time(),
                        'awarded_by' => $USER->id,
                    ], false);
                    $post_message = get_string('admin_gift_awarded_coins', 'local_ascend_rewards', (object)[
                        'amount' => $amount,
                        'coinlabel' => $coinlabel,
                        'username' => $username,
                    ]);
                    $post_message_type = 'success';
                }
            } catch (Exception $e) {
                $post_message = get_string('admin_error_gift_coins', 'local_ascend_rewards', s($e->getMessage()));
                $post_message_type = 'error';
            }
        } else if ($action === 'gift_tokens') {
            $tokenlabel = $amount === 1
                ? get_string('token_label_singular', 'local_ascend_rewards')
                : get_string('token_label_plural', 'local_ascend_rewards');
            // Gift tokens
            try {
                // Check if user has a token record
                $token_record = $DB->get_record('local_ascend_rewards_level_tokens', ['userid' => $userid]);

                if ($token_record) {
                    // Update existing record - add to tokens_available
                    $token_record->tokens_available += $amount;
                    $token_record->timemodified = time();
                    $DB->update_record('local_ascend_rewards_level_tokens', $token_record);
                    $new_available = $token_record->tokens_available;
                } else {
                    // Create new record
                    $token_record = (object)[
                        'userid' => $userid,
                        'tokens_available' => $amount,
                        'tokens_used' => 0,
                        'timemodified' => time(),
                    ];
                    $DB->insert_record('local_ascend_rewards_level_tokens', $token_record);
                    $new_available = $amount;
                }

                // Log the gift with awarding user
                $DB->insert_record('local_ascend_rewards_badgerlog', (object)[
                    'userid' => $userid,
                    'badgeid' => 0,
                    'status' => 'admin_gift_tokens',
                    'message' => get_string('admin_log_gift_tokens', 'local_ascend_rewards', (object)[
                        'amount' => $amount,
                        'tokenlabel' => $tokenlabel,
                    ]),
                    'timecreated' => time(),
                    'awarded_by' => $USER->id,
                ], false);
                $post_message = get_string('admin_gift_awarded_tokens', 'local_ascend_rewards', (object)[
                    'amount' => $amount,
                    'tokenlabel' => $tokenlabel,
                    'username' => $username,
                ]);
                $post_message_type = 'success';
            } catch (Exception $e) {
                $post_message = get_string('admin_error_gift_tokens', 'local_ascend_rewards', s($e->getMessage()));
                $post_message_type = 'error';
            }
        }
    } else if ($action === 'award' || $action === 'revoke') {
        // For award/revoke actions, require courseid and badgeid
        $userid_str = optional_param('userid', '', PARAM_TEXT);
        $courseid_str = optional_param('courseid', '', PARAM_TEXT);
        $badgeid_str = optional_param('badgeid', '', PARAM_TEXT);

        // Parse IDs - handle both "Name (ID: X)" format and plain "X" format
        $userid = 0;
        if (!empty($userid_str)) {
            if (preg_match($idpattern, $userid_str, $matches)) {
                $userid = (int)$matches[1];
            } else if (is_numeric($userid_str)) {
                $userid = (int)$userid_str;
            }
        }

        $courseid = 0;
        if (!empty($courseid_str)) {
            if (preg_match($idpattern, $courseid_str, $matches)) {
                $courseid = (int)$matches[1];
            } else if (is_numeric($courseid_str)) {
                $courseid = (int)$courseid_str;
            }
        }

        $badgeid = 0;
        if (!empty($badgeid_str)) {
            if (preg_match($idpattern, $badgeid_str, $matches)) {
                $badgeid = (int)$matches[1];
            } else if (is_numeric($badgeid_str)) {
                $badgeid = (int)$badgeid_str;
            }
        }

        if (!$userid || !$courseid || !$badgeid) {
            $post_message = get_string('admin_error_invalid_selection', 'local_ascend_rewards');
            $post_message_type = 'error';
        } else if ($action === 'award') {
            // Validate course and badge before attempting award
            if ($courseid <= 1) {
                $post_message = get_string('admin_error_invalid_course', 'local_ascend_rewards');
                $post_message_type = 'error';
            } else {
                // Get user's full name for display
                $user = $DB->get_record('user', ['id' => $userid], 'id,firstname,lastname,firstnamephonetic,lastnamephonetic,middlename,alternatename');
                $username = $user ? htmlspecialchars(fullname($user)) : get_string('user_number_label', 'local_ascend_rewards', $userid);
                $badgename = isset($badge_names[$badgeid]) ? $badge_names[$badgeid] : get_string('badge_number_label', 'local_ascend_rewards', $badgeid);

                // Ensure this badge actually awards coins (only active demo badges return > 0)
                $coins_for_badge = \local_ascend_rewards\badge_awarder::coins_for_badge($badgeid);
                if ($coins_for_badge <= 0) {
                    $post_message = get_string('admin_error_badge_inactive', 'local_ascend_rewards');
                    $post_message_type = 'error';
                } else {
                    // Prevent duplicate awards for non-repeatable badges
                    $already_awarded = $DB->record_exists('local_ascend_rewards_coins', [
                        'userid' => $userid,
                        'badgeid' => $badgeid,
                        'courseid' => $courseid,
                    ]);
                    $is_repeatable = \local_ascend_rewards\badge_awarder::is_repeatable_badge($badgeid);

                    if ($already_awarded && !$is_repeatable) {
                        $post_message = get_string('admin_error_badge_already_awarded', 'local_ascend_rewards');
                        $post_message_type = 'error';
                    } else {
                        // Capture state before and after to detect silent failures
                        $before_awards = $DB->count_records('local_ascend_rewards_coins', [
                            'userid' => $userid,
                            'badgeid' => $badgeid,
                            'courseid' => $courseid,
                        ]);

                        // Award the badge
                        \local_ascend_rewards\badge_awarder::award_coins($userid, $badgeid, $courseid);

                        $after_awards = $DB->count_records('local_ascend_rewards_coins', [
                            'userid' => $userid,
                            'badgeid' => $badgeid,
                            'courseid' => $courseid,
                        ]);

                        if ($after_awards > $before_awards) {
                            $post_message = get_string('admin_award_success', 'local_ascend_rewards', (object)[
                                'badgename' => $badgename,
                                'username' => $username,
                            ]);
                            $post_message_type = 'success';
                        } else {
                            $post_message = get_string('admin_error_award_no_record', 'local_ascend_rewards');
                            $post_message_type = 'error';
                        }
                    }
                }
            }
        } else if ($action === 'revoke') {
            // Get user's full name for display
            $user = $DB->get_record('user', ['id' => $userid], 'id,firstname,lastname,firstnamephonetic,lastnamephonetic,middlename,alternatename');
            $username = $user ? htmlspecialchars(fullname($user)) : get_string('user_number_label', 'local_ascend_rewards', $userid);
            $badgename = isset($badge_names[$badgeid]) ? $badge_names[$badgeid] : get_string('badge_number_label', 'local_ascend_rewards', $badgeid);

            // Revoke the badge
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
                'message' => get_string('admin_log_badge_revoked', 'local_ascend_rewards'),
                'timecreated' => time(),
            ], false);
            $post_message = get_string('admin_revoke_success', 'local_ascend_rewards', (object)[
                'badgename' => $badgename,
                'username' => $username,
            ]);
            $post_message_type = 'success';
        }
    }
}

// Fallback: ensure the admin sees something if POST produced no message
if ($ispost && $handled_post && $post_message === '') {
    $post_message = get_string('admin_error_no_status_message', 'local_ascend_rewards');
    $post_message_type = 'error';
}

// Surface the result using Moodle's native notification area as well
if (!empty($post_message)) {
    $notifytext = trim(str_replace(['<br />', '<br/>', '<br>'], "\n", strip_tags($post_message)));
    if ($post_message_type === 'success') {
        \core\notification::success($notifytext);
    } else {
        \core\notification::error($notifytext);
    }
}

$tabtemplate = '';
$tabcontext = [];

switch ($tab) {
    case 'metrics':
        $tabtemplate = 'local_ascend_rewards/admin_dashboard_metrics';
        $tabcontext = \local_ascend_rewards\admin_dashboard_helper::metrics_context($badge_names, $OUTPUT);
        break;
    case 'users':
        $tabtemplate = 'local_ascend_rewards/admin_dashboard_users';
        $tabcontext = \local_ascend_rewards\admin_dashboard_helper::users_context($badge_names, $idpattern);
        break;
    case 'award':
        $tabtemplate = 'local_ascend_rewards/admin_dashboard_award';
        $tabcontext = \local_ascend_rewards\admin_dashboard_helper::award_context($badge_names);
        break;
    case 'gifts':
        $tabtemplate = 'local_ascend_rewards/admin_dashboard_gifts';
        $tabcontext = \local_ascend_rewards\admin_dashboard_helper::gifts_context();
        break;
    case 'cleanup':
        $tabtemplate = 'local_ascend_rewards/admin_dashboard_cleanup';
        $tabcontext = \local_ascend_rewards\admin_dashboard_helper::cleanup_context($ispost, $action);
        break;
    case 'debug':
        $tabtemplate = 'local_ascend_rewards/admin_dashboard_debug';
        $tabcontext = \local_ascend_rewards\admin_dashboard_helper::debug_context($badge_names, $idpattern);
        break;
    case 'audit':
        $tabtemplate = 'local_ascend_rewards/admin_dashboard_audit';
        $tabcontext = \local_ascend_rewards\admin_dashboard_helper::audit_context($badge_names, $idpattern);
        break;
}

$tabcontent = $OUTPUT->render_from_template($tabtemplate, $tabcontext);


$post_message_class = '';
if (!empty($post_message)) {
    if ($post_message_type === 'success') {
        $post_message_class = 'alert alert-success aa-admin-alert aa-admin-alert-success';
    } else {
        $post_message_class = 'alert alert-danger aa-admin-alert aa-admin-alert-error';
    }
}

$templatecontext = [
    'admintitle' => $admintitle,
    'reward_video_url' => $reward_video_url,
    'congrats_title' => $congrats_title,
    'congrats_subtext' => $congrats_subtext,
    'tabs' => $tabs_context,
    'post_message' => $post_message ?: null,
    'post_message_class' => $post_message_class,
    'tabcontent' => $tabcontent,
];

echo $OUTPUT->render_from_template('local_ascend_rewards/admin_dashboard', $templatecontext);
echo $OUTPUT->footer();
