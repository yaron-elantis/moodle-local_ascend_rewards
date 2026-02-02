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
require_login();
// This page contains styling-sensitive inline HTML/CSS blocks.
// phpcs:disable moodle.Files.LineLength.MaxExceeded,moodle.Files.LineLength.TooLong
// phpcs:disable moodle.NamingConventions.ValidVariableName.VariableNameUnderscore
// phpcs:disable Generic.WhiteSpace.ScopeIndent.Incorrect,Generic.WhiteSpace.ScopeIndent.IncorrectExact
// phpcs:disable moodle.Commenting.InlineComment.InvalidEndChar,moodle.Commenting.InlineComment.NotCapital
// phpcs:disable moodle.Commenting.MissingDocblock.File
// phpcs:disable moodle.WhiteSpace.WhiteSpaceInStrings.EndLine
// phpcs:disable PSR2.ControlStructures.SwitchDeclaration.TerminatingComment
// Allow managers, teachers, and site admins to access the admin dashboard
$context = context_system::instance();
if (!has_capability('moodle/course:bulkmessaging', $context) && !has_capability('moodle/site:config', $context)) {
    throw new moodle_exception('nopermission');
}
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/ascend_rewards/admin_dashboard.php'));
$PAGE->set_title('Apex Rewards Admin Dashboard');
$PAGE->set_heading('Apex Rewards Admin Dashboard');

echo $OUTPUT->header();
// Open site-like wrapper to match index layout
echo '<div class="aa-wrap"><div class="apexrewards">';

echo '<style>
.apx-modal{position:fixed;left:50%;top:50%;transform:translate(-50%,-50%);width:min(480px,90vw);max-height:75vh;background:linear-gradient(135deg, #0a1832 0%, #0d1b35 100%);color:#e9f0ff;border:3px solid #FF9500;border-radius:18px;box-shadow:0 25px 70px rgba(0,0,0,0.7), 0 0 50px rgba(255,149,0,0.25), inset 0 1px 0 rgba(255,255,255,0.1);z-index:1001;display:none;overflow-y:auto;overflow-x:hidden;padding:24px;}
.apx-modal[style*="display: block"]{display:block!important;}
.apx-modal::-webkit-scrollbar{width:10px;}
.apx-modal::-webkit-scrollbar-track{background:rgba(255,255,255,0.03);border-radius:10px;}
.apx-modal::-webkit-scrollbar-thumb{background:rgba(255,149,0,0.4);border-radius:10px;}
.apx-modal::-webkit-scrollbar-thumb:hover{background:rgba(255,149,0,0.6);}
.apx-modal-backdrop{position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.85);backdrop-filter:blur(4px);display:none;z-index:1000;}
.apx-modal-close{position:absolute;top:34px;right:34px;background:rgba(255,149,0,0.2);border:2px solid rgba(255,149,0,0.4);color:#FF9500;cursor:pointer;width:38px;height:38px;display:flex;align-items:center;justify-content:center;border-radius:50%;transition:all 0.3s;z-index:20;padding:0;box-shadow:0 4px 12px rgba(0,0,0,0.3);}
.apx-modal-close:hover{background:rgba(255,149,0,0.4);transform:rotate(90deg) scale(1.15);box-shadow:0 0 24px rgba(255,149,0,0.7);border-color:#FF9500;}
.apx-modal-header{display:none;}
.apx-visual-container{position:relative;margin-bottom:24px;border-radius:14px;overflow:hidden;background:linear-gradient(135deg, rgba(0,212,255,0.1) 0%, rgba(255,0,170,0.1) 100%);border:2px solid rgba(255,149,0,0.2);box-shadow:inset 0 2px 10px rgba(0,0,0,0.3);width:100%;max-width:320px;aspect-ratio:1;margin-left:auto;margin-right:auto;display:flex;align-items:center;justify-content:center;}
.apx-badge-image-placeholder{min-height:0;}
.apx-badge-video{width:100%;height:100%;object-fit:cover;display:none;background:#000;}
.apx-video-fullscreen-btn{position:absolute;bottom:10px;right:10px;background:rgba(0,0,0,0.85);border:2px solid rgba(255,149,0,0.5);color:#FF9500;padding:6px 12px;border-radius:8px;cursor:pointer;font-size:13px;font-weight:700;display:none;z-index:10;transition:all 0.3s;box-shadow:0 4px 12px rgba(0,0,0,0.4);}
.apx-video-fullscreen-btn:hover{background:rgba(255,149,0,0.3);border-color:#FF9500;transform:scale(1.05);box-shadow:0 0 16px rgba(255,149,0,0.6);}
.apx-badge-title-block{margin-bottom:24px;text-align:center;padding:16px;background:rgba(0,212,255,0.05);border-radius:12px;border:1px solid rgba(0,212,255,0.2);}
.apx-badge-name{font-size:1.5rem;font-weight:800;color:#00D4FF;margin:0 0 8px 0;line-height:1.3;text-shadow:0 2px 8px rgba(0,212,255,0.3);}
.apx-detail-category{font-size:13px;color:#94a3b8;}
.apx-label{color:#FF00AA;font-weight:600;margin-right:6px;}
.apx-modal-xp-coins{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin:20px 0;padding:0;}
.apx-modal-stat{display:flex;flex-direction:column;align-items:center;gap:6px;padding:16px 12px;background:linear-gradient(135deg, rgba(255,0,170,0.08) 0%, rgba(255,0,170,0.15) 100%);border:2px solid rgba(255,0,170,0.35);border-radius:12px;text-align:center;transition:all 0.3s;box-shadow:0 4px 12px rgba(255,0,170,0.15);}
.apx-modal-stat:hover{transform:translateY(-2px);box-shadow:0 6px 20px rgba(255,0,170,0.3);border-color:#FF00AA;}
.apx-modal-stat svg,.apx-coin-icon{width:36px;height:36px;object-fit:contain;}
.apx-stat-value{font-size:17px;font-weight:800;color:#fff;text-shadow:0 2px 4px rgba(0,0,0,0.3);}
.apx-stat-label{font-size:10px;color:#94a3b8;text-transform:uppercase;letter-spacing:0.6px;font-weight:600;}
.apx-secondary-info-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin:20px 0;}
.apx-detail-item{color:#e9f0ff;font-size:14px;}
.apx-detail-why-block{margin:20px 0;}
.apx-why-title{color:#FF9500;font-size:16px;font-weight:600;margin:0 0 8px 0;}
.apx-why-text{color:#A5B4D6;margin:0;font-size:14px;line-height:1.5;}
/* Celebration banner and level-up pulse (copied from index.php) */
.aa-congrats{display:flex;gap:16px;align-items:center;justify-content:center;flex-wrap:wrap;margin-bottom:18px;text-align:center;}
.aa-congrats video{width:19.9cm;height:11.01cm;max-width:100%;object-fit:contain;border-radius:10px;margin:0 auto;}
.xp-ring-container{position:relative;width:80px;height:80px;margin:0 auto 12px;}
.level-up-flash{animation:levelUpPulse 2s infinite;}
@keyframes levelUpPulse{0%{transform:scale(1);box-shadow:0 0 0 rgba(255,149,0,0.25)}50%{transform:scale(1.04);box-shadow:0 10px 40px rgba(255,149,0,0.1)}100%{transform:scale(1);box-shadow:0 0 0 rgba(255,149,0,0.0)}}
.apx-divider{border:0;border-top:1px solid rgba(255,255,255,0.1);margin:20px 0;}
.apx-collapsible-section{margin:20px 0;}
.apx-section-title{font-size:14px;margin-bottom:8px;display:block;}
.cyan-accent{color:#00D4FF;}
.magenta-accent{color:#FF00AA;}
.apx-list-activities,.apx-list-badges{list-style:none;padding:0;margin:0;}
.apx-list-activities li,.apx-list-badges li{padding:4px 0;color:#A5B4D6;font-size:13px;}
.apx-stat-value{font-size:18px;font-weight:bold;color:#FF00AA;}
</style>';

echo '<h1>Apex Rewards Admin Dashboard</h1>';
// Add site-like celebration banner just below the heading (hidden by default)
echo '<div id="adminApexCongrats" class="aa-congrats" style="display:none;">';
echo '  <video id="adminApexCongratsVideo" autoplay muted loop playsinline><source src="' . (new moodle_url('/local/ascend_rewards/pix/reward_animation.mp4'))->out(false) . '" type="video/mp4"></video>';
echo '  <div><div style="font-weight:800;font-size:18px;" id="adminApexCongratsText">New badge earned this week!</div><div class="aa-muted">Keep up the momentum  more Ascend Assets are on the way.</div></div>';
echo '</div>';

// Define available tabs
$tabs = [
    'metrics' => 'Key Metrics',
    'users' => 'User Badges',
    'award' => 'Award Badges',
    'gifts' => 'Gift Rewards',
    'cleanup' => 'Cleanup Tools',
    'debug' => 'Debug Tools',
    'audit' => 'Audit Trail',
];

// Get current tab from URL parameter, default to 'metrics'
$tab = optional_param('tab', 'metrics', PARAM_ALPHA);

echo '<nav class="nav-tabs" style="display: flex; gap: 15px; padding: 20px 0; border-bottom: 2px solid #ddd; flex-wrap: wrap;">';
foreach ($tabs as $key => $label) {
    $active_class = ($tab === $key) ? 'active' : '';
    $style = ($tab === $key) ? 'style="color: #FF9500; border-bottom: 3px solid #FF9500; padding-bottom: 5px; font-weight: bold;"' : 'style="color: #666; padding-bottom: 5px; text-decoration: none;"';
    echo "<a href='?tab=$key' class='nav-tab $active_class' $style>$label</a>";
}
echo '</nav>';

// Badge definitions for consistent naming
$badge_definitions = [
    'Getting Started'   => 6,
    'Halfway Hero'      => 5,
    'Master Navigator'  => 8,
    'Feedback Follower' => 13,
    'Steady Improver'   => 15,
    'Tenacious Tiger'   => 14,
    'Glory Guide'       => 16,
];
// Map badge IDs to names for lookup
$badge_names = [];
foreach ($badge_definitions as $name => $id) {
    $badge_names[$id] = $name;
}

// Store POST messages to display after rendering header/tabs
$post_message = '';
$post_message_type = '';
$handled_post = false;

// Handle POST requests for award/revoke/gift from any tab
$ispost = data_submitted();
if ($ispost) {
    global $DB;
    require_sesskey();
    $action = optional_param('action', '', PARAM_ALPHANUMEXT);



    // Extract userid and amount for gift actions
    $userid_str = optional_param('userid', '', PARAM_TEXT);
    $amount = optional_param('amount', 0, PARAM_INT);

    // Parse userid from "Name (ID: X)" format
    $userid = 0;
    if (!empty($userid_str)) {
        if (preg_match('/\(ID: (\d+)\)$/', $userid_str, $matches)) {
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
        $post_message = 'Invalid user or amount.';
        $post_message_type = 'error';
    } else if ($action === 'gift_coins' || $action === 'gift_tokens') {
            // Get user's full name for display
            $user = $DB->get_record('user', ['id' => $userid], 'id,firstname,lastname,firstnamephonetic,lastnamephonetic,middlename,alternatename');
            $username = $user ? htmlspecialchars(fullname($user)) : "User #$userid";

        if ($action === 'gift_coins') {
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
                    $post_message = 'Failed to save gift coins to database.';
                    $post_message_type = 'error';
                } else {
                    // Log the gift with awarding user
                    $DB->insert_record('local_ascend_rewards_badgerlog', (object)[
                        'userid' => $userid,
                        'badgeid' => 0,
                        'status' => 'admin_gift_coins',
                        'message' => "Awarded {$amount} coins",
                        'timecreated' => time(),
                        'awarded_by' => $USER->id,
                    ], false);
                    $post_message = ' <strong>Gift Awarded!</strong><br>Gifted <strong>' . $amount . ' coin' . ($amount > 1 ? 's' : '') . '</strong> to <strong>' . $username . '</strong>';
                    $post_message_type = 'success';
                }
            } catch (Exception $e) {
                $post_message = 'Error gifting coins: ' . $e->getMessage();
                $post_message_type = 'error';
            }
        } else if ($action === 'gift_tokens') {
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
                    'message' => "Awarded {$amount} tokens",
                    'timecreated' => time(),
                    'awarded_by' => $USER->id,
                ], false);
                $post_message = ' <strong>Gift Awarded!</strong><br>Gifted <strong>' . $amount . ' token' . ($amount > 1 ? 's' : '') . '</strong> to <strong>' . $username . '</strong>';
                $post_message_type = 'success';
            } catch (Exception $e) {
                $post_message = 'Error gifting tokens: ' . $e->getMessage();
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
            if (preg_match('/\(ID: (\d+)\)$/', $userid_str, $matches)) {
                $userid = (int)$matches[1];
            } else if (is_numeric($userid_str)) {
                $userid = (int)$userid_str;
            }
        }

        $courseid = 0;
        if (!empty($courseid_str)) {
            if (preg_match('/\(ID: (\d+)\)$/', $courseid_str, $matches)) {
                $courseid = (int)$matches[1];
            } else if (is_numeric($courseid_str)) {
                $courseid = (int)$courseid_str;
            }
        }

        $badgeid = 0;
        if (!empty($badgeid_str)) {
            if (preg_match('/\(ID: (\d+)\)$/', $badgeid_str, $matches)) {
                $badgeid = (int)$matches[1];
            } else if (is_numeric($badgeid_str)) {
                $badgeid = (int)$badgeid_str;
            }
        }

        if (!$userid || !$courseid || !$badgeid) {
            $post_message = 'Invalid selection. Please select from the dropdown.';
            $post_message_type = 'error';
        } else if ($action === 'award') {
            // Validate course and badge before attempting award
            if ($courseid <= 1) {
                $post_message = 'Please select a valid course (must not be the site home).';
                $post_message_type = 'error';
            } else {
                // Get user's full name for display
                $user = $DB->get_record('user', ['id' => $userid], 'id,firstname,lastname,firstnamephonetic,lastnamephonetic,middlename,alternatename');
                $username = $user ? htmlspecialchars(fullname($user)) : "User #$userid";
                $badgename = isset($badge_names[$badgeid]) ? $badge_names[$badgeid] : "Badge #$badgeid";

                // Ensure this badge actually awards coins (only active demo badges return > 0)
                $coins_for_badge = \local_ascend_rewards\badge_awarder::coins_for_badge($badgeid);
                if ($coins_for_badge <= 0) {
                    $post_message = 'This badge is inactive and cannot be awarded in the demo set.';
                    $post_message_type = 'error';
                } else {
                    // Prevent duplicate awards for non-repeatable badges
                    $already_awarded = $DB->record_exists('local_ascend_rewards_coins', [
                        'userid' => $userid,
                        'badgeid' => $badgeid,
                        'courseid' => $courseid,
                    ]);

                    if ($already_awarded) {
                        $post_message = 'Badge already awarded for this user/course.';
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
                            $post_message = '<strong>Badge Awarded!</strong><br>Awarded <strong>' . $badgename . '</strong> to <strong>' . $username . '</strong>';
                            $post_message_type = 'success';
                        } else {
                            $post_message = 'Award attempt completed but no database record was created. Please verify the badge is active, the course ID is greater than 1, and check plugin logs.';
                            $post_message_type = 'error';
                        }
                    }
                }
            }
        } else if ($action === 'revoke') {
            // Get user's full name for display
            $user = $DB->get_record('user', ['id' => $userid], 'id,firstname,lastname,firstnamephonetic,lastnamephonetic,middlename,alternatename');
            $username = $user ? htmlspecialchars(fullname($user)) : "User #$userid";
            $badgename = isset($badge_names[$badgeid]) ? $badge_names[$badgeid] : "Badge #$badgeid";

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
                'message' => "Badge manually revoked by admin",
                'timecreated' => time(),
            ], false);
            $post_message = ' <strong>Badge Revoked!</strong><br>Revoked <strong>' . $badgename . '</strong> from <strong>' . $username . '</strong>';
            $post_message_type = 'success';
        }
    }
}

// Fallback: ensure the admin sees something if POST produced no message
if ($ispost && $handled_post && $post_message === '') {
    $post_message = 'Award request processed but no status message was returned. Please re-check the input and database records.';
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

if (!empty($post_message)) {
    if ($post_message_type === 'success') {
        echo '<div class="alert alert-success" style="background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 15px; border-radius: 4px; margin: 20px 0;">';
        echo $post_message;
        echo '</div>';
    } else {
        echo '<div class="alert alert-danger" style="background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 15px; border-radius: 4px; margin: 20px 0;">';
        echo $post_message;
        echo '</div>';
    }
}

switch ($tab) {
    case 'metrics':
        echo '<h2 style="margin-bottom:30px;">Key Metrics</h2>';
        // Live metrics
        $total_badges = $DB->count_records('local_ascend_rewards_coins');
        $users_with_badges = $DB->count_records_sql("SELECT COUNT(DISTINCT c.userid) FROM {local_ascend_rewards_coins} c JOIN {user} u ON u.id = c.userid WHERE u.suspended = 0 AND u.deleted = 0");
        $top_badge = $DB->get_records_sql("SELECT c.badgeid, COUNT(*) as cnt FROM {local_ascend_rewards_coins} c JOIN {user} u ON u.id = c.userid WHERE u.suspended = 0 AND u.deleted = 0 GROUP BY c.badgeid ORDER BY cnt DESC LIMIT 1");
        $top_badge_id = $top_badge ? reset($top_badge)->badgeid : null;
        $top_badge_count = $top_badge ? reset($top_badge)->cnt : 0;
        $badge_icons = [
            4 => '', 6 => '', 5 => '', 8 => '',
            9 => '', 11 => '', 10 => '', 12 => '',
            13 => '', 15 => '', 14 => '', 16 => '',
            19 => '', 17 => '', 7 => '', 20 => '',
        ];
        $top_badge_name = $top_badge_id && isset($badge_names[$top_badge_id]) ? $badge_names[$top_badge_id] : ($top_badge_id ?: 'N/A');
        $top_badge_icon = $top_badge_id && isset($badge_icons[$top_badge_id]) ? $badge_icons[$top_badge_id] : '';
        // Modern card UI
        echo '<style>
        .apex-metrics-row { display: flex; gap: 32px; margin-bottom: 32px; }
        .apex-metric-card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.07);
            padding: 32px 36px 24px 36px;
            min-width: 260px;
            text-align: center;
            flex: 1;
            border: 1px solid #e3e3e3;
            transition: box-shadow 0.2s;
        }
        .apex-metric-card:hover { box-shadow: 0 4px 16px rgba(0,0,0,0.13); }
        .apex-metric-icon { font-size: 2.5rem; margin-bottom: 10px; display: block; }
        .apex-metric-label { color: #667eea; font-size: 1.1rem; margin-bottom: 6px; letter-spacing: 0.5px; }
        .apex-metric-value { font-size: 2.2rem; font-weight: bold; color: #222; }
        .apex-metric-badge { font-size: 1.2rem; color: #444; margin-top: 8px; }
        </style>';
        echo '<div class="apex-metrics-row">';
        echo '<div class="apex-metric-card">'
            . '<span class="apex-metric-icon"></span>'
            . '<div class="apex-metric-label">Total Badges Awarded</div>'
            . '<div class="apex-metric-value">' . $total_badges . '</div>'
        . '</div>';
        echo '<div class="apex-metric-card">'
            . '<span class="apex-metric-icon"></span>'
            . '<div class="apex-metric-label">Users with Badges</div>'
            . '<div class="apex-metric-value">' . $users_with_badges . '</div>'
        . '</div>';
        echo '<div class="apex-metric-card">'
            . '<span class="apex-metric-icon">' . $top_badge_icon . '</span>'
            . '<div class="apex-metric-label">Most Awarded Badge</div>'
            . '<div class="apex-metric-value">' . htmlspecialchars($top_badge_name) . '</div>'
            . '<div class="apex-metric-badge">' . $top_badge_count . ' awards</div>'
        . '</div>';
        echo '</div>';
        // --- Badges awarded by type ---
        echo '<h3 style="margin-top:40px;">Badges Awarded by Type</h3>';
        $badge_counts = $DB->get_records_sql("SELECT badgeid, COUNT(*) as cnt FROM {local_ascend_rewards_coins} WHERE badgeid > 0 GROUP BY badgeid ORDER BY cnt DESC");
        echo '<table style="margin-bottom:32px;width:100%;max-width:700px;border-collapse:collapse;">';
        echo '<tr style="background:#f5f7fa;"><th style="padding:8px 12px;">Badge</th><th style="padding:8px 12px;">Awards</th></tr>';
        foreach ($badge_counts as $row) {
            $bid = $row->badgeid;
            $bname = isset($badge_names[$bid]) ? $badge_names[$bid] : 'Badge #' . $bid;
            $bicon = isset($badge_icons[$bid]) ? $badge_icons[$bid] : '';
            echo '<tr><td style="padding:8px 12px;">' . $bicon . ' ' . htmlspecialchars($bname ?: '') . '</td><td style="padding:8px 12px;">' . $row->cnt . '</td></tr>';
        }
        echo '</table>';

        // --- User leaderboard ---
        echo '<h3>User Leaderboard (by XP)</h3>';
        $leaders = $DB->get_records_sql("SELECT x.userid, x.xp FROM {local_ascend_rewards_xp} x JOIN {user} u ON u.id = x.userid WHERE x.courseid = 0 AND x.xp > 0 AND u.suspended = 0 AND u.deleted = 0 ORDER BY x.xp DESC LIMIT 10");
        echo '<table style="margin-bottom:32px;width:100%;max-width:700px;border-collapse:collapse;">';
        echo '<tr style="background:#f5f7fa;"><th style="padding:8px 12px;">Rank</th><th style="padding:8px 12px;">User</th><th style="padding:8px 12px;">Total XP</th></tr>';
        $rank = 1;
        foreach ($leaders as $row) {
            $user = $DB->get_record('user', ['id' => $row->userid], 'id,firstname,lastname,firstnamephonetic,lastnamephonetic,middlename,alternatename,username', IGNORE_MISSING);
            $uname = $user ? fullname($user) . " (ID: $user->id)" : "User #$row->userid";
            echo '<tr><td style="padding:8px 12px;">' . $rank . '</td><td style="padding:8px 12px;">' . htmlspecialchars($uname ?: '') . '</td><td style="padding:8px 12px;">' . $row->xp . '</td></tr>';
            $rank++;
        }
        echo '</table>';

        // --- Badge distribution chart (with images and names) ---
        echo '<h3>Badge Distribution</h3>';
        $badge_img_map = [
            6 => $OUTPUT->image_url('getting_started', 'local_ascend_rewards')->out(false),
            5 => $OUTPUT->image_url('halfway_hero', 'local_ascend_rewards')->out(false),
            8 => $OUTPUT->image_url('master_navigator', 'local_ascend_rewards')->out(false),
            13 => $OUTPUT->image_url('feedback_follower', 'local_ascend_rewards')->out(false),
            15 => $OUTPUT->image_url('steady_improver', 'local_ascend_rewards')->out(false),
            14 => $OUTPUT->image_url('tenacious_tiger', 'local_ascend_rewards')->out(false),
            16 => $OUTPUT->image_url('glory_guide', 'local_ascend_rewards')->out(false),
        ];
        $blank_img = '/local/ascend_rewards/pix/Blank_Badge_Image.png';
        $max_awards = 0;
        foreach ($badge_counts as $row) {
            if ($row->cnt > $max_awards) {
                $max_awards = $row->cnt;
            }
        }
        echo '<div style="display:flex;gap:8px;align-items:end;margin-bottom:32px;">';
        foreach ($badge_counts as $row) {
            $bid = $row->badgeid;
            $bname = isset($badge_names[$bid]) ? $badge_names[$bid] : $bid;
            $img = isset($badge_img_map[$bid]) ? $badge_img_map[$bid] : $blank_img;
            $height = $max_awards ? (60 + 120 * ($row->cnt / $max_awards)) : 60;
            echo '<div style="text-align:center;flex:1;">';
            echo '<div style="background:#667eea;border-radius:8px 8px 0 0;width:32px;margin:0 auto;height:' . round($height) . 'px;"></div>';
            echo '<img src="' . $img . '" alt="' . htmlspecialchars($bname ?: '') . '" style="width:72px;height:72px;margin:8px auto 0 auto;display:block;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,0.10);background:#fff;object-fit:contain;" onerror="this.onerror=null;this.src=\'' . $blank_img . '\';">';
            echo '<div style="font-size:0.98rem;color:#222;margin-top:4px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:80px;margin:0 auto;">' . htmlspecialchars($bname ?: '') . '</div>';
            echo '<div style="font-size:0.95rem;color:#444;">' . $row->cnt . '</div>';
            echo '</div>';
        }
        echo '</div>';

        // Test modal button
        echo '<div style="margin-top:40px; padding:20px; background:#f8f9fa; border-radius:8px; border:1px solid #dee2e6;">';
        echo '<h3>Test Badge Popup Animation</h3>';
        echo '<p>Select a badge to preview its award modal:</p>';

        echo '<form method="post" id="testModalForm">';
        echo '<input type="hidden" name="sesskey" value="' . sesskey() . '">';
        echo '<div style="display:flex; gap:15px; align-items:flex-end; margin-bottom:15px; flex-wrap:wrap;">';
        echo '<div>';
        echo '<label style="display:block; margin-bottom:5px; font-weight:600;">Badge:</label>';
        echo '<select name="test_badgeid" id="testBadgeSelect" style="padding:8px; border:1px solid #ddd; border-radius:4px; min-width:250px;">';
        foreach ($badge_names as $bid => $bname) {
            echo '<option value="' . $bid . '">' . htmlspecialchars($bname) . ' (ID: ' . $bid . ')</option>';
        }
        echo '</select>';
        echo '</div>';
        echo '<button type="button" onclick="triggerTestModal()" class="btn btn-primary" style="height:38px;"> Show Modal</button>';
        echo '</div>';
        echo '</form>';
        echo '</div>';

        // Add the real badge modal HTML if not already present
        echo '<audio id="levelUpSound" preload="auto"><source src="' . (new moodle_url('/local/ascend_rewards/pix/level_up.mp3'))->out(false) . '" type="audio/mpeg"></audio>';
        echo '<div id="apxModalBackdrop" class="apx-modal-backdrop"></div>';
        echo '<div id="apxModal" class="apx-modal" role="dialog" aria-modal="true" aria-labelledby="apxModalTitle">';
        echo '    <button class="apx-modal-close" id="apxModalClose" aria-label="Close">';
        echo '        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>';
        echo '    </button>';
        echo '    <div class="apx-modal-header" id="apxModalTitle"></div>';
        echo '    <div class="apx-modal-body">';
        echo '        <div class="apx-visual-container">';
        echo '            <div class="apx-badge-image-placeholder"></div>';
        echo '            <video id="apxRewardVideo" class="apx-badge-video" playsinline loop>';
        echo '                <source id="apxRewardVideoSource" src="" type="video/mp4">';
        echo '            </video>';
        echo '            <button id="apxVideoFullscreen" class="apx-video-fullscreen-btn" title="Fullscreen"></button>';
        echo '        </div>';
        echo '        <div class="apx-badge-title-block">';
        echo '            <h2 id="apxBName" class="apx-badge-name">Badge Name Placeholder</h2>';
        echo '            <div class="apx-detail-category"><span class="apx-label">Category:</span> <span id="apxBCategory">General</span></div>';
        echo '        </div>';
        echo '        <div class="apx-modal-xp-coins">';
        echo '            <div class="apx-modal-stat">';
        echo '                <svg width="32" height="32" viewBox="0 0 80 80">';
        echo '                    <defs><linearGradient id="xpIconGrad" x1="0%" y1="0%" x2="100%" y2="100%"><stop offset="0%" stop-color="#00D4FF" /><stop offset="50%" stop-color="#FF00AA" /><stop offset="100%" stop-color="#FF9500" /></linearGradient></defs>';
        echo '                    <circle cx="40" cy="40" r="36" fill="url(#xpIconGrad)" />';
        echo '                    <text x="40" y="50" text-anchor="middle" fill="#01142E" font-size="32" font-weight="800">X</text>';
        echo '                </svg>';
        echo '                <span id="apxBXP" class="apx-stat-value">+0 XP</span>';
        echo '                <span class="apx-stat-label">Experience Points</span>';
        echo '            </div>';
        echo '            <div class="apx-modal-stat">';
        echo '                <img src="' . s($OUTPUT->image_url('medal_gold', 'local_ascend_rewards')->out(false)) . '" alt="Coins" class="apx-coin-icon" onerror="this.style.display=\'none\';">';
        echo '                <span id="apxBCoins" class="apx-stat-value">+0 Ascend Assets</span>';
        echo '                <span class="apx-stat-label">Assets Earned</span>';
        echo '            </div>';
        echo '        </div>';
        echo '        <hr class="apx-divider"/>';
        echo '        <div class="apx-secondary-info-grid">';
        echo '            <div class="apx-detail-item"><span class="apx-label">Course:</span> <span id="apxBCourse"></span></div>';
        echo '            <div class="apx-detail-item"><span class="apx-label">Achieved On:</span> <span id="apxBWhen"></span></div>';
        echo '        </div>';
        echo '        <div class="apx-detail-why-block">';
        echo '            <h3 class="apx-why-title">Why You Earned This Badge:</h3>';
        echo '            <p id="apxBWhy" class="apx-why-text"></p>';
        echo '        </div>';
        echo '        <hr class="apx-divider"/>';
        echo '        <div id="apxBActivities" class="apx-collapsible-section" style="display:none;">';
        echo '            <strong class="apx-section-title cyan-accent">Qualifying Activities:</strong>';
        echo '            <ul id="apxBActivitiesList" class="apx-list-activities"></ul>';
        echo '        </div>';
        echo '        <div id="apxBBadges" class="apx-collapsible-section" style="display:none;">';
        echo '            <strong class="apx-section-title magenta-accent">Contributing Badges:</strong>';
        echo '            <ul id="apxBBadgesList" class="apx-list-badges"></ul>';
        echo '        </div>';
        echo '        <div id="apxBRank" class="apx-rank-info" style="display:none;"></div>';
        echo '    </div>';
        echo '</div>';

        // Add the real badge modal JavaScript
        $modal_js = <<<EOT
<script>
(function(){
  function q(sel){return document.querySelector(sel);}

    const modal = q("#apxModal");
    const backdrop = q("#apxModalBackdrop");
    const closeBtn = q("#apxModalClose");
    const congratsBanner = q('#adminApexCongrats');
    const congratsVideo = q('#adminApexCongratsVideo');
    const congratsText = q('#adminApexCongratsText');
  const video = q("#apxRewardVideo");
  const videoSource = q("#apxRewardVideoSource");
  const fullscreenBtn = q("#apxVideoFullscreen");

  console.log("Modal elements found:", {modal, backdrop, closeBtn, video, videoSource, fullscreenBtn});

  // Map badge IDs to their video files
  const badgeVideos = {
    6: "Getting Started/getting_started_2.mp4",
    4: "On a Roll/on_a_roll_2.mp4",
    5: "Halfway Hero/halfway_hero_1.mp4",
    8: "Master Navigator/master_navigator_3.mp4",
    9: "Early Bird/early_bird_1.mp4",
    11: "Sharp Shooter/sharp_shooter_1.mp4",
    10: "Deadline Burner/deadline_burner_1.mp4",
    12: "Time Tamer/time_tamer_4.mp4",
    13: "Feedback Follower/feedback_follower_1.mp4",
    15: "Steady Improver/steady_improver_1.mp4",
    14: "Tenacious Tiger/tenacious_tiger_1.mp4",
    16: "Glory Guide/glory_guide_3.mp4",
    19: "High Flyer/high_flyer_3.mp4",
    17: "Assessment Ace/activity_ace_3.mp4",
    7: "Mission Complete/mission_complete_1.mp4",
    20: "Learning Legend/learning_legend_5.mp4"
  };

  // Badge categories
  const badgeCategories = {
    6: "Progress", 4: "Progress", 5: "Progress", 8: "Progress",
    9: "Timeliness", 11: "Timeliness", 10: "Timeliness", 12: "Timeliness",
    13: "Quality & Growth", 15: "Quality & Growth", 14: "Quality & Growth", 16: "Quality & Growth",
    19: "Quality & Growth", 17: "Mastery", 7: "Mastery", 20: "Mastery"
  };

  // Badge descriptions
  const badgeDescriptions = {
    6: "First activity completed",
    4: "3 consecutive activities completed",
    5: "50% of course activities completed",
    8: "Earn 2+ badges in Progress category",
    9: "Complete activity 48hrs before deadline",
    11: "3 consecutive activities before deadline",
    10: "All activities before deadline",
    12: "Earn 2+ badges in Timeliness category",
    13: "Improved grade in an activity",
    15: "Pass after initial fail",
    14: "Improved grade in 3 activities",
    16: "Earn 2+ badges in Quality & Growth category",
    19: "Pass 2 consecutive activities",
    17: "Pass all activities first attempt",
    7: "All course activities completed",
    20: "Earn 2+ badges in Mastery category"
  };

  function openModal(badgeData){
    console.log("openModal called with:", badgeData);
    const d = badgeData;
    q("#apxBName").textContent = d.badge || "";
    q("#apxBCategory").textContent = d.category || "";
    q("#apxBCourse").textContent = d.course || "";
    q("#apxBWhen").textContent = d.when || "";
    q("#apxBWhy").textContent = d.why || "";

    // Extract coins value from the text (e.g., "+10 Ascend Assets" -> 10)
    const coinsText = d.coins || "+0 Ascend Assets";
    const coins = parseInt(coinsText.match(/\d+/)) || 0;

    // Calculate badge XP (coins / 2)
    const badgeXp = Math.floor(coins / 2);

    q("#apxBXP").textContent = "+" + badgeXp + " XP";
    q("#apxBCoins").textContent = coinsText;

        // Hide activities/badges for preview; we'll fetch if applicable
        q("#apxBActivities").style.display = "none";
        q("#apxBBadges").style.display = "none";
        // Fetch and display activities or contributing badges, similar to site behavior
        const courseid = parseInt(d.courseid) || 0;
        const badgeId = parseInt(d.badgeid) || 0;
        const metaBadges = [8, 12, 16, 20];
        const isMeta = metaBadges.includes(badgeId);

        // For meta badges, always hide qualifying activities section
        if (isMeta) {
            q('#apxBActivities').style.display = 'none';
        }

        if (courseid > 0 && !isMeta) {
            fetch(M.cfg.wwwroot + '/local/ascend_rewards/get_activities.php?courseid=' + courseid + '&badgeid=' + badgeId)
                .then(response => response.json())
                .then(data => {
                    if (data.activities && data.activities.length > 0) {
                        const knownBadgeNames = ['Getting Started', 'On a Roll', 'Halfway Hero', 'Early Bird', 'Sharp Shooter', 'Deadline Burner', 'Feedback Follower', 'Steady Improver', 'Tenacious Tiger', 'High Flyer', 'Activity Ace', 'Mission Complete'];
                        // Check if activities are badge names (for meta badges) or contain badge names with "Award #X:" prefix
                        const areBadges = data.activities.some(item => {
                            // Direct match (e.g., "Early Bird")
                            if (knownBadgeNames.includes(item)) return true;
                            // Match with Award prefix (e.g., "Award #1: Early Bird")
                            return knownBadgeNames.some(badgeName => item.includes(badgeName));
                        });
                        if (areBadges) {
                            const badgesDiv = q('#apxBBadges');
                            const badgesList = q('#apxBBadgesList');
                            badgesList.innerHTML = '';
                            data.activities.forEach(function(badge) {
                                const li = document.createElement('li');
                                li.textContent = ' ' + badge;
                                li.style.color = '#FFD700';
                                badgesList.appendChild(li);
                            });
                            badgesDiv.style.display = 'block';
                            q('#apxBActivities').style.display = 'none';
                        } else {
                            const activitiesDiv = q('#apxBActivities');
                            const activitiesList = q('#apxBActivitiesList');
                            activitiesList.innerHTML = '';
                            data.activities.forEach(function(activity, index) {
                                if (index < 15) {
                                    const li = document.createElement('li');
                                    li.textContent = ' ' + activity;
                                    li.style.color = '#A5B4D6';
                                    activitiesList.appendChild(li);
                                }
                            });
                            if (data.activities.length > 15) {
                                const li = document.createElement('li');
                                li.textContent = '...and ' + (data.activities.length - 15) + ' more';
                                li.style.color = '#FFD700';
                                activitiesList.appendChild(li);
                            }
                            activitiesDiv.style.display = 'block';
                            q('#apxBBadges').style.display = 'none';
                        }
                    } else {
                        q('#apxBActivities').style.display = 'none';
                        q('#apxBBadges').style.display = 'none';
                    }
                })
                .catch(() => {
                    q('#apxBActivities').style.display = 'none';
                    q('#apxBBadges').style.display = 'none';
                });
        } else {
            q('#apxBActivities').style.display = 'none';
            if (isMeta) {
                q('#apxBBadges').style.display = 'none';
            }
        }

    // Load the correct video for this badge
    const videoFile = badgeVideos[d.badgeid] || "reward_animation_2.mp4";
    const videoUrl = M.cfg.wwwroot + "/local/ascend_rewards/pix/" + videoFile;

    if(video && videoSource){
      videoSource.src = videoUrl;
      video.load();
      video.style.display="block";
      video.currentTime=0;
      video.play().catch(()=>{});
      if(fullscreenBtn) fullscreenBtn.style.display="block";
    }
        // Show the celebration banner (site-style) in admin preview
        if(congratsBanner){
            congratsText.textContent = 'New badge earned: ' + (d.badge || 'Badge');
            congratsBanner.style.display = 'flex';
        }
        if(congratsVideo){
            try{ congratsVideo.currentTime = 0; congratsVideo.play(); }catch(e){}
        }

        modal.style.display="block";
        backdrop.style.display="block";
    document.body.style.overflow="hidden";
  }

  function closeModal(){
    modal.style.display="none";
    backdrop.style.display="none";
    document.body.style.overflow="";
    if(video){
      video.pause();
      video.style.display="none";
    }
        if(congratsBanner){
            congratsBanner.style.display = 'none';
        }
        if(congratsVideo){
            congratsVideo.pause();
            try{congratsVideo.currentTime = 0;}catch(e){}
        }
  }

  // Event listeners
  if(closeBtn) closeBtn.addEventListener("click", closeModal);
  if(backdrop) backdrop.addEventListener("click", closeModal);
  if(fullscreenBtn) fullscreenBtn.addEventListener("click", function(){
    if(video && video.requestFullscreen) {
      video.requestFullscreen();
    }
  });

  // Make openModal available globally for the trigger function
  window.openBadgeModal = openModal;
  console.log("window.openBadgeModal assigned:", typeof window.openBadgeModal);

    window.triggerTestModal = function() {
        // Support both the preview select (badgeModalPreviewSelect) and the test select (testBadgeSelect)
        const badgeSelect = document.getElementById("badgeModalPreviewSelect") || document.getElementById("testBadgeSelect");
    const badgeId = badgeSelect.value;
    if (!badgeId) {
      alert("Please select a badge first.");
      return;
    }
    const badgeName = badgeSelect.options[badgeSelect.selectedIndex].text.split(" (ID: ")[0];
    const badgeData = {
      badge: badgeName,
      category: badgeCategories[badgeId] || "General",
      course: "Sample Course",
      courseid: 1,
      badgeid: badgeId,
      when: new Date().toLocaleDateString(),
      why: badgeDescriptions[badgeId] || "Badge modal preview",
      coins: "+100 Ascend Assets"
    };
    window.openBadgeModal(badgeData);
  };
})();
</script>
EOT;
        // Output the JS
        echo $modal_js;
    case 'users':
        // Get search parameters
        $search_type = optional_param('search_type', 'user', PARAM_ALPHA);
        $userid_str = optional_param('userid', '', PARAM_TEXT);
        $badgeid_search = optional_param('badgeid_search', '', PARAM_TEXT);

        // Parse user ID
        $userid = 0;
        if ($userid_str && preg_match('/\(ID: (\d+)\)$/', $userid_str, $matches)) {
            $userid = (int)$matches[1];
        }

        // Parse badge ID
        $badgeid = 0;
        if ($badgeid_search && preg_match('/\(ID: (\d+)\)$/', $badgeid_search, $matches)) {
            $badgeid = (int)$matches[1];
        }

        echo '<div class="card">';

        // Tab buttons to switch between search types
        echo '<div style="display:flex; gap:10px; margin-bottom:20px; border-bottom:2px solid #ddd; padding-bottom:10px;">';
        echo '<button type="button" class="btn ' . ($search_type === 'user' ? 'btn-primary' : 'btn-secondary') . '" onclick="switchSearchTab(\'user\')" style="padding:8px 16px; border:none; cursor:pointer; border-radius:4px; background-color:' . ($search_type === 'user' ? '#FF9500' : '#ccc') . '; color:' . ($search_type === 'user' ? '#fff' : '#333') . ';">Search by User</button>';
        echo '<button type="button" class="btn ' . ($search_type === 'badge' ? 'btn-primary' : 'btn-secondary') . '" onclick="switchSearchTab(\'badge\')" style="padding:8px 16px; border:none; cursor:pointer; border-radius:4px; background-color:' . ($search_type === 'badge' ? '#FF9500' : '#ccc') . '; color:' . ($search_type === 'badge' ? '#fff' : '#333') . ';">Search by Badge</button>';
        echo '</div>';

        // User search form
        if ($search_type === 'user') {
            echo '<form method="get" style="display:flex; align-items:end; gap:10px; margin-bottom:20px;">';
            echo '<input type="hidden" name="tab" value="users">';
            echo '<input type="hidden" name="search_type" value="user">';
            echo '<div class="form-group" style="margin:0;">';
            echo '<label class="form-label">User:</label>';
            echo '<input type="text" name="userid" class="form-input" list="users_list" value="' . htmlspecialchars($userid_str) . '" placeholder="Start typing user name..." style="width:300px;">';
            echo '<datalist id="users_list">';
            $users = $DB->get_records_sql("SELECT DISTINCT u.id, u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic, u.middlename, u.alternatename, u.username FROM {user} u JOIN {local_ascend_rewards_coins} c ON c.userid = u.id WHERE u.suspended = 0 AND u.deleted = 0 ORDER BY u.lastname, u.firstname");
            foreach ($users as $u) {
                $label = htmlspecialchars(fullname($u)) . ' (ID: ' . $u->id . ')';
                echo '<option value="' . $label . '">';
            }
            echo '</datalist>';
            echo '</div>';
            echo '<button type="submit" class="btn btn-primary">View</button>';
            echo '</form>';

            if ($userid) {
                $badges = $DB->get_records_sql(
                    "SELECT * FROM {local_ascend_rewards_coins} WHERE userid = ? AND badgeid > 0 ORDER BY timecreated DESC",
                    [$userid]
                );
                if ($badges) {
                    $user = $DB->get_record('user', ['id' => $userid], 'firstname,lastname,firstnamephonetic,lastnamephonetic,middlename,alternatename', IGNORE_MISSING);
                    $uname = $user ? fullname($user) : 'User #' . $userid;
                    echo '<h3>Badges for ' . htmlspecialchars($uname) . '</h3>';
                    echo '<table class="table" style="width:100%; border-collapse:collapse;">';
                    echo '<tr style="background-color:#f5f5f5;"><th style="padding:10px; text-align:left; border-bottom:2px solid #ddd;">Badge Name</th><th style="padding:10px; text-align:left; border-bottom:2px solid #ddd;">Course Name</th><th style="padding:10px; text-align:left; border-bottom:2px solid #ddd;">Coins</th><th style="padding:10px; text-align:left; border-bottom:2px solid #ddd;">Date Awarded</th></tr>';
                    foreach ($badges as $badge) {
                        $bname = isset($badge_names[$badge->badgeid]) ? $badge_names[$badge->badgeid] : 'Badge #' . $badge->badgeid;
                        $course = $DB->get_record('course', ['id' => $badge->courseid], 'fullname', IGNORE_MISSING);
                        $cname = $course ? $course->fullname : 'Unknown';
                        echo '<tr style="border-bottom:1px solid #eee;">';
                        echo '<td style="padding:10px;">' . htmlspecialchars($bname ?: '') . '</td>';
                        echo '<td style="padding:10px;">' . htmlspecialchars($cname ?: '') . '</td>';
                        echo '<td style="padding:10px;">' . $badge->coins . '</td>';
                        echo '<td style="padding:10px;">' . userdate($badge->timecreated) . '</td>';
                        echo '</tr>';
                    }
                    echo '</table>';
                } else {
                    echo '<p>No badges found for this user.</p>';
                }
            }
        } else {
            // Badge search form
            echo '<form method="get" style="display:flex; align-items:end; gap:10px; margin-bottom:20px;">';
            echo '<input type="hidden" name="tab" value="users">';
            echo '<input type="hidden" name="search_type" value="badge">';
            echo '<div class="form-group" style="margin:0;">';
            echo '<label class="form-label">Badge:</label>';
            echo '<input type="text" name="badgeid_search" class="form-input" list="badges_list" value="' . htmlspecialchars($badgeid_search) . '" placeholder="Start typing badge name..." style="width:300px;">';
            echo '<datalist id="badges_list">';
            foreach ($badge_names as $bid => $bname) {
                $label = htmlspecialchars($bname) . ' (ID: ' . $bid . ')';
                echo '<option value="' . $label . '">';
            }
            echo '</datalist>';
            echo '</div>';
            echo '<button type="submit" class="btn btn-primary">View</button>';
            echo '</form>';

            if ($badgeid) {
                $badge_record = $DB->get_record('badge', ['id' => $badgeid], 'name', IGNORE_MISSING);
                $badge_name = $badge_record ? $badge_record->name : (isset($badge_names[$badgeid]) ? $badge_names[$badgeid] : 'Unknown Badge');

                $badge_recipients = $DB->get_records_sql(
                    "SELECT DISTINCT c.userid, c.coins, c.timecreated, c.courseid, u.firstname, u.lastname, u.email
                     FROM {local_ascend_rewards_coins} c
                     JOIN {user} u ON u.id = c.userid
                     WHERE c.badgeid = ? AND u.deleted = 0 AND u.suspended = 0
                     ORDER BY c.timecreated DESC",
                    [$badgeid]
                );

                if ($badge_recipients) {
                    echo '<h3>Recipients of "' . htmlspecialchars($badge_name) . '" (' . count($badge_recipients) . ' user' . (count($badge_recipients) !== 1 ? 's' : '') . ')</h3>';
                    echo '<table class="table" style="width:100%; border-collapse:collapse;">';
                    echo '<tr style="background-color:#f5f5f5;"><th style="padding:10px; text-align:left; border-bottom:2px solid #ddd;">User</th><th style="padding:10px; text-align:left; border-bottom:2px solid #ddd;">Email</th><th style="padding:10px; text-align:left; border-bottom:2px solid #ddd;">Course</th><th style="padding:10px; text-align:left; border-bottom:2px solid #ddd;">Coins</th><th style="padding:10px; text-align:left; border-bottom:2px solid #ddd;">Date Awarded</th></tr>';
                    foreach ($badge_recipients as $recipient) {
                        $full_name = htmlspecialchars(fullname($recipient));
                        $email = htmlspecialchars($recipient->email);
                        $course = $DB->get_record('course', ['id' => $recipient->courseid], 'fullname', IGNORE_MISSING);
                        $course_name = $course ? htmlspecialchars($course->fullname) : 'Unknown Course';
                        echo '<tr style="border-bottom:1px solid #eee;">';
                        echo '<td style="padding:10px;">' . $full_name . '</td>';
                        echo '<td style="padding:10px;">' . $email . '</td>';
                        echo '<td style="padding:10px;">' . $course_name . '</td>';
                        echo '<td style="padding:10px;">' . $recipient->coins . '</td>';
                        echo '<td style="padding:10px;">' . userdate($recipient->timecreated) . '</td>';
                        echo '</tr>';
                    }
                    echo '</table>';
                } else {
                    echo '<p>No recipients found for this badge.</p>';
                }
            }
        }

        echo '</div>';

        // Add JavaScript for tab switching
        echo '<script>
        function switchSearchTab(tab) {
            const url = new URL(window.location);
            url.searchParams.set("tab", "users");
            url.searchParams.set("search_type", tab);
            url.searchParams.delete("userid");
            url.searchParams.delete("badgeid_search");
            window.location = url.toString();
        }
        </script>';
        break;
    case 'award':
        echo '<!-- ===== MARKER: AWARD TAB LOADED FROM UPDATED FILE - ' . date('Y-m-d H:i:s') . ' ===== -->';
        echo '<h2>Award/Revoke Badge</h2>';
        // Populate options
        global $DB;
        $users = $DB->get_records_sql("SELECT DISTINCT u.id, u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic, u.middlename, u.alternatename, u.username FROM {user} u JOIN {local_ascend_rewards_coins} c ON c.userid = u.id WHERE u.suspended = 0 AND u.deleted = 0 ORDER BY u.lastname, u.firstname");
        $courses = $DB->get_records('course', ['visible' => 1], 'fullname', 'id,fullname');
        echo '<div class="card">';
        echo '<form method="post" onsubmit="return validateAwardForm()">';
        // Keep the user on the Award tab after submit
        echo '<input type="hidden" name="tab" value="award">';
        echo '<input type="hidden" name="sesskey" value="' . sesskey() . '">';
        echo '<div class="form-group">';
        echo '<label class="form-label">User:</label>';
        echo '<input type="text" name="userid" class="form-input" list="users" required placeholder="Start typing user name...">';
        echo '<datalist id="users">';
        foreach ($users as $u) {
            $label = htmlspecialchars(fullname($u)) . ' (ID: ' . $u->id . ')';
            echo '<option value="' . $label . '">';
        }
        echo '</datalist>';
        echo '</div>';
        echo '<div class="form-group">';
        echo '<label class="form-label">Course:</label>';
        echo '<input type="text" id="courseid_input" name="courseid" class="form-input" list="courses" required placeholder="Start typing course name...">';
        echo '<datalist id="courses">';
        foreach ($courses as $c) {
            $label = htmlspecialchars($c->fullname) . ' (ID: ' . $c->id . ')';
            echo '<option value="' . $label . '">';
        }
        echo '</datalist>';
        echo '</div>';
        echo '<div class="form-group">';
        echo '<label class="form-label">Badge:</label>';
        echo '<input type="text" id="badgeid_input" name="badgeid" class="form-input" list="badges" required placeholder="Start typing badge name...">';
        echo '<datalist id="badges">';
        foreach ($badge_names as $bid => $bname) {
            $label = htmlspecialchars($bname) . ' (ID: ' . $bid . ')';
            echo '<option value="' . $label . '">';
        }
        echo '</datalist>';
        echo '</div>';
        echo '<button type="submit" name="action" value="award" class="btn btn-primary">Award</button> ';
        echo '<button type="submit" name="action" value="revoke" class="btn btn-secondary">Revoke</button>';
        echo '</form>';
        echo '<script>
function validateAwardForm() {
    var courseid = document.getElementById("courseid_input").value;
    var badgeid = document.getElementById("badgeid_input").value;

    if (!courseid || !courseid.includes("(ID:")) {
        alert("Please select a valid course from the dropdown");
        return false;
    }
    if (!badgeid || !badgeid.includes("(ID:")) {
        alert("Please select a valid badge from the dropdown");
        return false;
    }
    return true;
}
</script>';
        echo '</div>';
        break;
    case 'gifts':
        echo '<h2>Gift Rewards</h2>';
        echo '<p><strong>This feature is only available in the PRO version.</strong></p>';
        break;

        // Get all users for the dropdown
        global $DB;
        $all_users = $DB->get_records_sql("SELECT id, firstname, lastname, firstnamephonetic, lastnamephonetic, middlename, alternatename, username FROM {user} WHERE suspended = 0 AND deleted = 0 ORDER BY lastname, firstname");

        echo '<div class="card" style="margin-bottom:30px;">';
        echo '<h3> Gift Coins</h3>';
        echo '<form method="post" style="padding: 20px; background: #f9f9f9; border-radius: 8px;">';
        echo '<input type="hidden" name="sesskey" value="' . sesskey() . '">';
        echo '<input type="hidden" name="action" value="gift_coins">';
        echo '<div class="form-group">';
        echo '<label class="form-label">Select User:</label>';
        echo '<input type="text" name="userid" class="form-input" list="gift_users" required placeholder="Type user name and select from list..." style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">';
        echo '<datalist id="gift_users">';
        foreach ($all_users as $u) {
            $label = htmlspecialchars(fullname($u)) . ' (ID: ' . $u->id . ')';
            echo '<option value="' . $label . '">';
        }
        echo '</datalist>';
        echo '</div>';
        echo '<div class="form-group">';
        echo '<label class="form-label">Coin Amount:</label>';
        echo '<input type="number" name="amount" class="form-input" min="1" max="10000" required placeholder="Enter amount..." style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">';
        echo '</div>';
        echo '<button type="submit" class="btn btn-primary" style="background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-weight: bold;"> Gift Coins</button>';
        echo '</form>';
        echo '</div>';

        echo '<div class="card">';
        echo '<h3> Gift Tokens</h3>';
        echo '<form method="post" style="padding: 20px; background: #f9f9f9; border-radius: 8px;">';
        echo '<input type="hidden" name="sesskey" value="' . sesskey() . '">';
        echo '<input type="hidden" name="action" value="gift_tokens">';
        echo '<div class="form-group">';
        echo '<label class="form-label">Select User:</label>';
        echo '<input type="text" name="userid" class="form-input" list="gift_users_tokens" required placeholder="Type user name and select from list..." style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">';
        echo '<datalist id="gift_users_tokens">';
        foreach ($all_users as $u) {
            $label = htmlspecialchars(fullname($u)) . ' (ID: ' . $u->id . ')';
            echo '<option value="' . $label . '">';
        }
        echo '</datalist>';
        echo '</div>';
        echo '<div class="form-group">';
        echo '<label class="form-label">Token Amount:</label>';
        echo '<input type="number" name="amount" class="form-input" min="1" max="100" required placeholder="Enter amount..." style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">';
        echo '</div>';
        echo '<button type="submit" class="btn btn-primary" style="background: #28a745; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-weight: bold;"> Gift Tokens</button>';
        echo '</form>';
        echo '</div>';

        // Award Audit Log - Show recent gifts with who gave them
        echo '<div class="card" style="margin-top: 40px;">';
        echo '<h3> Award Audit Log</h3>';
        echo '<p style="color: #666; font-size: 14px;">Recent gifts and awards with tracking of who gave them.</p>';

        $audit_logs = $DB->get_records_sql("
            SELECT bl.id, bl.userid, bl.status, bl.message, bl.timecreated, bl.awarded_by, u.firstname, u.lastname, u.id as awardee_id, aw.firstname as awardby_firstname, aw.lastname as awardby_lastname
            FROM {local_ascend_rewards_badgerlog} bl
            LEFT JOIN {user} u ON u.id = bl.userid
            LEFT JOIN {user} aw ON aw.id = bl.awarded_by
            WHERE bl.status IN ('admin_gift_coins', 'admin_gift_tokens')
            ORDER BY bl.timecreated DESC
            LIMIT 50
        ");

        if (empty($audit_logs)) {
            echo '<p style="color: #999; font-style: italic;">No gift awards yet.</p>';
        } else {
            echo '<table style="width: 100%; border-collapse: collapse; margin-top: 10px;">';
            echo '<thead style="background: #f0f0f0; border-bottom: 2px solid #ccc;">';
            echo '<tr>';
            echo '<th style="padding: 10px; text-align: left; border: 1px solid #ddd;"><strong>When</strong></th>';
            echo '<th style="padding: 10px; text-align: left; border: 1px solid #ddd;"><strong>Recipient</strong></th>';
            echo '<th style="padding: 10px; text-align: left; border: 1px solid #ddd;"><strong>Award Type</strong></th>';
            echo '<th style="padding: 10px; text-align: left; border: 1px solid #ddd;"><strong>Awarded By</strong></th>';
            echo '</tr>';
            echo '</thead>';
            echo '<tbody>';

            foreach ($audit_logs as $log) {
                $recipient = ($log->firstname && $log->lastname) ? htmlspecialchars($log->firstname . ' ' . $log->lastname) : "User #{$log->userid}";
                $awardby = ($log->awardby_firstname && $log->awardby_lastname) ? htmlspecialchars($log->awardby_firstname . ' ' . $log->awardby_lastname) : ($log->awarded_by ? "User #{$log->awarded_by}" : 'System');
                $type = str_replace('admin_gift_', '', $log->status);
                $message = htmlspecialchars($log->message);
                $time = date('Y-m-d H:i:s', $log->timecreated);

                echo '<tr style="border-bottom: 1px solid #eee;">';
                echo '<td style="padding: 10px; border: 1px solid #ddd; font-size: 12px;">' . $time . '</td>';
                echo '<td style="padding: 10px; border: 1px solid #ddd;"><strong>' . $recipient . '</strong></td>';
                echo '<td style="padding: 10px; border: 1px solid #ddd;"><span style="background: ' . ($type === 'coins' ? '#FFD700' : '#00D4FF') . '; color: #000; padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: bold;">' . ucfirst($type) . '</span> - ' . $message . '</td>';
                echo '<td style="padding: 10px; border: 1px solid #ddd;"><strong>' . $awardby . '</strong></td>';
                echo '</tr>';
            }

            echo '</tbody>';
            echo '</table>';
        }
        echo '</div>';
        break;
    case 'cleanup':
        echo '<h2>Cleanup Tools</h2>';
        echo '<div class="card">';
        if ($ispost && $action !== '') {
            $handled_post = true;
            $action = required_param('action', PARAM_ALPHANUMEXT);
            if ($action === 'cleanup_orphaned') {
                // Run cleanup orphaned badges
                global $DB;
                $orphaned = $DB->get_records_sql("
                    SELECT c.id
                    FROM {local_ascend_rewards_coins} c
                    LEFT JOIN {course} crs ON crs.id = c.courseid
                    WHERE c.courseid > 0 AND crs.id IS NULL
                ");
                $count = count($orphaned);
                if ($count > 0) {
                    foreach ($orphaned as $rec) {
                        $DB->delete_records('local_ascend_rewards_coins', ['id' => $rec->id]);
                    }
                    echo '<div class="alert alert-success">Cleaned up ' . $count . ' orphaned badge records.</div>';
                } else {
                    echo '<div class="alert alert-success">No orphaned badges found.</div>';
                }
            } else if ($action === 'cleanup_deleted_users') {
                // Run cleanup deleted users
                global $DB;
                $deleted_users = $DB->get_records_sql("
                    SELECT c.id
                    FROM {local_ascend_rewards_coins} c
                    LEFT JOIN {user} u ON u.id = c.userid
                    WHERE u.id IS NULL OR u.deleted = 1
                ");
                $count = count($deleted_users);
                if ($count > 0) {
                    foreach ($deleted_users as $rec) {
                        $DB->delete_records('local_ascend_rewards_coins', ['id' => $rec->id]);
                    }
                    echo '<div class="alert alert-success">Cleaned up ' . $count . ' badge records for deleted users.</div>';
                } else {
                    echo '<div class="alert alert-success">No badge records found for deleted users.</div>';
                }
            }
        }
        echo '<form method="post">';
        echo '<input type="hidden" name="sesskey" value="' . sesskey() . '">';
        echo '<p><strong>Cleanup Orphaned Badges:</strong> This tool removes badge award records that are linked to courses which no longer exist in the system. Orphaned badges can occur when courses are deleted after badges have been awarded. Running this cleanup ensures the database remains clean and prevents issues with reports or statistics.</p>';
        echo '<p><strong>Cleanup Deleted Users:</strong> This tool removes all badge award records for users who have been deleted from the system. Deleted users should not have any plugin data remaining.</p>';
        echo '<p><strong>Note:</strong> Suspended users are automatically excluded from all plugin functionality (leaderboards, debug info, XP rewards, etc.) and do not appear in any results.</p>';
        echo '<button type="submit" name="action" value="cleanup_orphaned" class="btn btn-primary">Cleanup Orphaned Badges</button>';
        echo '<button type="submit" name="action" value="cleanup_deleted_users" class="btn btn-secondary" style="margin-left:10px;">Cleanup Deleted Users</button>';
        echo '</form>';
        echo '</div>';
        break;
    case 'debug':
        echo '<h2>Debug Tools</h2>';
        $userid_str = optional_param('userid', '', PARAM_TEXT);
        $courseid_str = optional_param('courseid', '', PARAM_TEXT);
        $badgeid_str = optional_param('badgeid', '', PARAM_TEXT);

        // Parse IDs
        $userid = 0;
        if ($userid_str && preg_match('/\(ID: (\d+)\)$/', $userid_str, $matches)) {
            $userid = (int)$matches[1];
        }
        $courseid = 0;
        if ($courseid_str && preg_match('/\(ID: (\d+)\)$/', $courseid_str, $matches)) {
            $courseid = (int)$matches[1];
        }
        $badgeid = 0;
        if ($badgeid_str && preg_match('/\(ID: (\d+)\)$/', $badgeid_str, $matches)) {
            $badgeid = (int)$matches[1];
        }

        // Populate options
        global $DB;
        $users = $DB->get_records_sql("SELECT DISTINCT u.id, u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic, u.middlename, u.alternatename, u.username FROM {user} u JOIN {local_ascend_rewards_coins} c ON c.userid = u.id WHERE u.suspended = 0 AND u.deleted = 0 ORDER BY u.lastname, u.firstname");
        $courses = $DB->get_records('course', ['visible' => 1], 'fullname', 'id,fullname');

        echo '<div class="card">';
        echo '<form method="get" style="display:flex; flex-wrap:wrap; gap:10px; align-items:end; margin-bottom:20px;">';
        echo '<input type="hidden" name="tab" value="debug">';
        echo '<div class="form-group" style="margin:0;">';
        echo '<label class="form-label">User:</label>';
        echo '<input type="text" name="userid" class="form-input" list="debug_users" value="' . htmlspecialchars($userid_str) . '" placeholder="Start typing user name..." style="width:250px;">';
        echo '<datalist id="debug_users">';
        foreach ($users as $u) {
            $label = htmlspecialchars(fullname($u)) . ' (ID: ' . $u->id . ')';
            echo '<option value="' . $label . '">';
        }
        echo '</datalist>';
        echo '</div>';
        echo '<div class="form-group" style="margin:0;">';
        echo '<label class="form-label">Course:</label>';
        echo '<input type="text" name="courseid" class="form-input" list="debug_courses" value="' . htmlspecialchars($courseid_str) . '" placeholder="Start typing course name..." style="width:250px;">';
        echo '<datalist id="debug_courses">';
        echo '<option value="Global (ID: 0)">';
        foreach ($courses as $c) {
            $label = htmlspecialchars($c->fullname) . ' (ID: ' . $c->id . ')';
            echo '<option value="' . $label . '">';
        }
        echo '</datalist>';
        echo '</div>';
        echo '<div class="form-group" style="margin:0;">';
        echo '<label class="form-label">Badge:</label>';
        echo '<input type="text" name="badgeid" class="form-input" list="debug_badges" value="' . htmlspecialchars($badgeid_str) . '" placeholder="Start typing badge name..." style="width:250px;">';
        echo '<datalist id="debug_badges">';
        foreach ($badge_names as $bid => $bname) {
            $label = htmlspecialchars($bname) . ' (ID: ' . $bid . ')';
            echo '<option value="' . $label . '">';
        }
        echo '</datalist>';
        echo '</div>';
        echo '<button type="submit" class="btn btn-primary">Debug</button>';
        echo '</form>';

        if ($userid && $courseid && $badgeid) {
            // Badge info
            $badge_info = [
                4 => ['name' => 'On a Roll', 'desc' => '2 consecutive activities completed', 'method' => 'on_a_roll'],
                6 => ['name' => 'Getting Started', 'desc' => 'First activity completed', 'method' => 'getting_started'],
                5 => ['name' => 'Halfway Hero', 'desc' => '50% of course activities completed', 'method' => 'halfway_hero'],
                8 => ['name' => 'Master Navigator', 'desc' => 'Earn 2+ badges in Progress category', 'method' => 'meta'],
                9 => ['name' => 'Early Bird', 'desc' => 'Complete activity 24hrs before deadline', 'method' => 'early_bird'],
                11 => ['name' => 'Sharp Shooter', 'desc' => '2 consecutive activities before deadline', 'method' => 'sharp_shooter'],
                10 => ['name' => 'Deadline Burner', 'desc' => 'All activities before deadline', 'method' => 'deadline_burner'],
                12 => ['name' => 'Time Tamer', 'desc' => 'Earn 2+ badges in Timeliness category', 'method' => 'meta'],
                13 => ['name' => 'Feedback Follower', 'desc' => 'Improved grade in an activity', 'method' => 'feedback_follower'],
                15 => ['name' => 'Steady Improver', 'desc' => 'Pass after initial fail', 'method' => 'steady_improver'],
                14 => ['name' => 'Tenacious Tiger', 'desc' => 'Improved grade in 2 activities', 'method' => 'tenacious_tiger'],
                16 => ['name' => 'Glory Guide', 'desc' => 'Earn 2+ badges in Quality & Growth category', 'method' => 'meta'],
                19 => ['name' => 'High Flyer', 'desc' => 'Pass 2 consecutive activities', 'method' => 'high_flyer'],
                17 => ['name' => 'Activity Ace', 'desc' => 'Pass all activities first attempt', 'method' => 'activity_ace'],
                7 => ['name' => 'Mission Complete', 'desc' => 'All course activities completed', 'method' => 'mission_complete'],
                20 => ['name' => 'Learning Legend', 'desc' => 'Earn 2+ badges in Mastery category', 'method' => 'meta'],
            ];

            $info = $badge_info[$badgeid] ?? null;
            if (!$info) {
                echo '<p>Invalid badge ID</p>';
            } else {
                // Get user and course
                $user = $DB->get_record('user', ['id' => $userid], 'firstname,lastname,firstnamephonetic,lastnamephonetic,middlename,alternatename', IGNORE_MISSING);

                if ($courseid == 0) {
                    $course_display = 'All Courses (Global)';
                } else {
                    $course = $DB->get_record('course', ['id' => $courseid], 'fullname', IGNORE_MISSING);
                    $course_display = htmlspecialchars($course->fullname) . ' (ID: ' . $courseid . ')';
                }

                echo '<h3>Badge Deep Dive: ' . htmlspecialchars($info['name']) . '</h3>';
                echo '<p><strong>User:</strong> ' . htmlspecialchars(fullname($user)) . ' (ID: ' . $userid . ') | <strong>Course:</strong> ' . $course_display . '</p>';

                // Check if already awarded
                if ($courseid == 0) {
                    // Global: get all awards for this badge across all courses
                    $all_awards = $DB->get_records('local_ascend_rewards_coins', [
                        'userid' => $userid,
                        'badgeid' => $badgeid,
                    ], 'timecreated ASC');
                } else {
                    // Specific course
                    $all_awards = $DB->get_records('local_ascend_rewards_coins', [
                        'userid' => $userid,
                        'badgeid' => $badgeid,
                        'courseid' => $courseid,
                    ], 'timecreated ASC');
                }

                $award_count = count($all_awards);

                if ($award_count > 0) {
                    echo '<div class="alert alert-success">';
                    echo '<h4> Badge Awarded ' . $award_count . ' Time(s)</h4>';

                    // Show all awards
                    foreach ($all_awards as $idx => $award) {
                        $award_num = $idx + 1;
                        echo '<p><strong>Award #' . $award_num . ':</strong> ' . $award->coins . ' coins on ' . userdate($award->timecreated) . '</p>';
                    }
                    echo '</div>';
                } else {
                    echo '<div class="alert alert-error">';
                    echo '<h4> Badge NOT Awarded</h4>';
                    echo '<p>This badge has not been awarded yet.</p>';
                    echo '</div>';
                }

                // Get completions
                if ($courseid == 0) {
                    // Global: get completions across all courses
                    $completions = $DB->get_records_sql("
                        SELECT cm.id as cmid, cm.course, cm.module, cm.instance, m.name as modname, cmc.completionstate, cmc.timemodified,
                               cm.completionexpected, gi.grademax, gi.gradepass, gg.finalgrade,
                               gg.timemodified as grade_time, c.fullname as coursename
                        FROM {course_modules} cm
                        INNER JOIN {modules} m ON m.id = cm.module
                        INNER JOIN {course} c ON c.id = cm.course
                        LEFT JOIN {course_modules_completion} cmc ON cmc.coursemoduleid = cm.id AND cmc.userid = :userid
                        LEFT JOIN {grade_items} gi ON gi.iteminstance = cm.instance AND gi.itemmodule = m.name AND gi.courseid = cm.course AND gi.itemtype = 'mod'
                        LEFT JOIN {grade_grades} gg ON gg.itemid = gi.id AND gg.userid = :userid2
                        WHERE cm.course > 1
                          AND cm.deletioninprogress = 0
                          AND cm.completion > 0
                        ORDER BY cm.course, cm.id ASC
                    ", ['userid' => $userid, 'userid2' => $userid]);
                } else {
                    // Specific course
                    $completions = $DB->get_records_sql("
                        SELECT cm.id as cmid, cm.module, cm.instance, m.name as modname, cmc.completionstate, cmc.timemodified,
                               cm.completionexpected, gi.grademax, gi.gradepass, gg.finalgrade,
                               gg.timemodified as grade_time
                        FROM {course_modules} cm
                        INNER JOIN {modules} m ON m.id = cm.module
                        LEFT JOIN {course_modules_completion} cmc ON cmc.coursemoduleid = cm.id AND cmc.userid = :userid
                        LEFT JOIN {grade_items} gi ON gi.iteminstance = cm.instance AND gi.itemmodule = m.name AND gi.courseid = cm.course AND gi.itemtype = 'mod'
                        LEFT JOIN {grade_grades} gg ON gg.itemid = gi.id AND gg.userid = :userid2
                        WHERE cm.course = :courseid
                          AND cm.deletioninprogress = 0
                          AND cm.completion > 0
                        ORDER BY cm.id ASC
                    ", ['userid' => $userid, 'userid2' => $userid, 'courseid' => $courseid]);
                }

                $completed_count = 0;
                $total_count = count($completions);
                $consecutive_completions = 0;
                $max_consecutive = 0;
                $before_deadline_count = 0;
                $consecutive_before_deadline = 0;
                $max_consecutive_deadline = 0;
                $early_bird_count = 0;
                $passed_count = 0;
                $consecutive_passes = 0;
                $max_consecutive_passes = 0;
                $first_attempt_passes = 0;

                echo '<h4> STEP 1: Retrieve Course Activity Data</h4>';
                if ($courseid == 0) {
                    echo '<p>Found <strong>' . $total_count . ' activities</strong> with completion tracking across all courses.</p>';
                } else {
                    echo '<p>Found <strong>' . $total_count . ' activities</strong> with completion tracking in this course.</p>';
                }

                if ($completions) {
                    echo '<table class="table">';
                    if ($courseid == 0) {
                        echo '<tr><th>Course</th><th>Activity</th><th>Type</th><th>Completed</th><th>Grade</th><th>Pass Grade</th><th>Passed</th><th>Due Date</th><th>Before Deadline</th><th>Early (24hrs)</th></tr>';
                    } else {
                        echo '<tr><th>Activity</th><th>Type</th><th>Completed</th><th>Grade</th><th>Pass Grade</th><th>Passed</th><th>Due Date</th><th>Before Deadline</th><th>Early (24hrs)</th></tr>';
                    }

                    foreach ($completions as $comp) {
                        $is_completed = $comp->completionstate > 0;
                        $completed_date = $is_completed ? userdate($comp->timemodified) : '-';

                        $grade = isset($comp->finalgrade) ? round($comp->finalgrade, 1) : '-';
                        $grademax = isset($comp->grademax) ? round($comp->grademax, 1) : '-';
                        $pass_grade = isset($comp->gradepass) ? round($comp->gradepass, 1) : '-';

                        $is_passed = false;
                        if (isset($comp->finalgrade) && isset($comp->gradepass) && $comp->gradepass > 0) {
                            $is_passed = $comp->finalgrade >= $comp->gradepass;
                        } else if (isset($comp->finalgrade) && isset($comp->grademax) && $comp->grademax > 0) {
                            $is_passed = $comp->finalgrade >= ($comp->grademax * 0.5);
                        }

                        // Due date
                        $actual_deadline = 0;
                        if ($comp->modname == 'assign') {
                            $assign = $DB->get_record('assign', ['id' => $comp->instance], 'duedate');
                            if ($assign && $assign->duedate > 0) {
                                $actual_deadline = $assign->duedate;
                            }
                        } else if ($comp->modname == 'quiz') {
                            $quiz = $DB->get_record('quiz', ['id' => $comp->instance], 'timeclose');
                            if ($quiz && $quiz->timeclose > 0) {
                                $actual_deadline = $quiz->timeclose;
                            }
                        }

                        $due_date = $actual_deadline > 0 ? userdate($actual_deadline) : 'No deadline';
                        $before_deadline = false;
                        $is_early = false;

                        if ($is_completed && $actual_deadline > 0) {
                            $before_deadline = $comp->timemodified < $actual_deadline;
                            $hours_early = ($actual_deadline - $comp->timemodified) / 3600;
                            $is_early = $hours_early >= 48;
                            if ($is_early) {
                                $early_bird_count++;
                            }
                        }

                        if ($is_completed) {
                            $completed_count++;
                            $consecutive_completions++;
                            $max_consecutive = max($max_consecutive, $consecutive_completions);
                        } else {
                            $consecutive_completions = 0;
                        }

                        if ($actual_deadline > 0) {
                            if ($before_deadline) {
                                $before_deadline_count++;
                                $consecutive_before_deadline++;
                                $max_consecutive_deadline = max($max_consecutive_deadline, $consecutive_before_deadline);
                            } else {
                                $consecutive_before_deadline = 0;
                            }
                        }

                        if ($is_passed) {
                            $passed_count++;
                            $consecutive_passes++;
                            $max_consecutive_passes = max($max_consecutive_passes, $consecutive_passes);
                        } else {
                            $consecutive_passes = 0;
                        }

                        echo '<tr>';
                        if ($courseid == 0) {
                            echo '<td>' . htmlspecialchars($comp->coursename) . '</td>';
                        }
                        echo '<td>' . htmlspecialchars($comp->modname) . '</td>';
                        echo '<td>' . strtoupper($comp->modname) . '</td>';
                        echo '<td>' . ($is_completed ? ' ' . $completed_date : '') . '</td>';
                        echo '<td>' . $grade . ' / ' . $grademax . '</td>';
                        echo '<td>' . $pass_grade . '</td>';
                        echo '<td>' . ($is_passed ? ' Pass' : '') . '</td>';
                        echo '<td>' . $due_date . '</td>';
                        echo '<td>' . ($before_deadline ? ' Yes' : ($actual_deadline > 0 ? ' No' : '-')) . '</td>';
                        echo '<td>' . ($is_early ? ' Yes' : '-') . '</td>';
                        echo '</tr>';
                    }
                    echo '</table>';
                }

                // STEP 2
                echo '<h4> STEP 2: Apply Badge Logic for \'' . htmlspecialchars($info['name']) . '\'</h4>';
                echo '<p><strong>Requirement:</strong> ' . htmlspecialchars($info['desc']) . '</p>';
                echo '<p><strong>Detection Method:</strong> ' . htmlspecialchars($info['method']) . '()</p>';

                $qualifies = false;
                $reason = '';

                switch ($badgeid) {
                    case 4: // On a Roll
                        $qualifies = $max_consecutive >= 2;
                        $reason = "Max consecutive completions: <strong>$max_consecutive</strong> (need 2)";
                        break;
                    case 6: // Getting Started
                        $qualifies = $completed_count >= 1;
                        $reason = "Completed activities: <strong>$completed_count</strong> (need 1)";
                        break;
                    case 5: // Halfway Hero
                        $progress = $total_count > 0 ? ($completed_count / $total_count) * 100 : 0;
                        $qualifies = $progress >= 50;
                        $reason = "Completion: <strong>" . round($progress, 1) . "%</strong> (need 50%)";
                        break;
                    case 9: // Early Bird
                        $qualifies = $early_bird_count >= 1;
                        $reason = "Early completions (24hrs+): <strong>$early_bird_count</strong> (need 1)";
                        break;
                    case 11: // Sharp Shooter
                        $qualifies = $max_consecutive_deadline >= 2;
                        $reason = "Max consecutive before deadline: <strong>$max_consecutive_deadline</strong> (need 2)";
                        break;
                    case 10: // Deadline Burner
                        $activities_with_deadlines = 0;
                        foreach ($completions as $comp) {
                            if ($comp->completionstate > 0) {
                                $has_deadline = false;
                                if ($comp->modname == 'assign') {
                                    $assign = $DB->get_record('assign', ['id' => $comp->instance], 'duedate');
                                    $has_deadline = ($assign && $assign->duedate > 0);
                                } else if ($comp->modname == 'quiz') {
                                    $quiz = $DB->get_record('quiz', ['id' => $comp->instance], 'timeclose');
                                    $has_deadline = ($quiz && $quiz->timeclose > 0);
                                }
                                if ($has_deadline) {
                                    $activities_with_deadlines++;
                                }
                            }
                        }
                        $all_before = ($activities_with_deadlines > 0) && ($before_deadline_count == $activities_with_deadlines);
                        $qualifies = $all_before;
                        $reason = "Before deadline: <strong>$before_deadline_count / $activities_with_deadlines</strong> (need all)";
                        break;
                    case 19: // High Flyer
                        $qualifies = $max_consecutive_passes >= 2;
                        $reason = "Max consecutive passes: <strong>$max_consecutive_passes</strong> (need 2)";
                        break;
                    case 17: // Activity Ace
                        $all_passed_first = ($completed_count > 0) && ($passed_count == $completed_count);
                        $qualifies = $all_passed_first;
                        $reason = "Passed first attempt: <strong>$passed_count / $completed_count</strong> (need all)";
                        break;
                    case 7: // Mission Complete
                        $all_completed = ($completed_count == $total_count);
                        $qualifies = $all_completed;
                        $reason = "Completed: <strong>$completed_count / $total_count</strong> (need all)";
                        break;
                    case 8: // Master Navigator
                        $base_badges = [6, 4, 5];
                        $earned = 0;
                        $earned_names = [];
                        foreach ($base_badges as $bid) {
                            if ($DB->record_exists('local_ascend_rewards_coins', ['userid' => $userid, 'badgeid' => $bid, 'courseid' => $courseid])) {
                                $earned++;
                                $earned_names[] = $badge_info[$bid]['name'];
                            }
                        }
                        $qualifies = $earned >= 2;
                        $reason = "Progress badges earned: <strong>$earned / 3</strong> (need 2)<br>Earned: " . implode(', ', $earned_names);
                        break;
                    case 12: // Time Tamer
                        $base_badges = [9, 11, 10];
                        $earned = 0;
                        $earned_names = [];
                        foreach ($base_badges as $bid) {
                            if ($DB->record_exists('local_ascend_rewards_coins', ['userid' => $userid, 'badgeid' => $bid, 'courseid' => $courseid])) {
                                $earned++;
                                $earned_names[] = $badge_info[$bid]['name'];
                            }
                        }
                        $qualifies = $earned >= 2;
                        $reason = "Timeliness badges earned: <strong>$earned / 3</strong> (need 2)<br>Earned: " . implode(', ', $earned_names);
                        break;
                    case 16: // Glory Guide
                        $base_badges = [13, 15, 14];
                        $earned = 0;
                        $earned_names = [];
                        foreach ($base_badges as $bid) {
                            if ($DB->record_exists('local_ascend_rewards_coins', ['userid' => $userid, 'badgeid' => $bid, 'courseid' => $courseid])) {
                                $earned++;
                                $earned_names[] = $badge_info[$bid]['name'];
                            }
                        }
                        $qualifies = $earned >= 2;
                        $reason = "Quality & Growth badges earned: <strong>$earned / 3</strong> (need 2)<br>Earned: " . implode(', ', $earned_names);
                        break;
                    case 20: // Learning Legend
                        $base_badges = [19, 17, 7];
                        $earned = 0;
                                               $earned_names = [];
                        foreach ($base_badges as $bid) {
                            if ($DB->record_exists('local_ascend_rewards_coins', ['userid' => $userid, 'badgeid' => $bid, 'courseid' => $courseid])) {
                                $earned++;
                                $earned_names[] = $badge_info[$bid]['name'];
                            }
                        }
                        $qualifies = $earned >= 2;
                        $reason = "Mastery badges earned: <strong>$earned / 3</strong> (need 2)<br>Earned: " . implode(', ', $earned_names);
                        break;
                }

                echo '<p>' . $reason . '</p>';

                if ($qualifies) {
                    echo '<div class="alert alert-success">';
                    echo '<h4> QUALIFICATION CHECK: PASS</h4>';
                    echo '<p>User meets all requirements for \'' . htmlspecialchars($info['name']) . '\'</p>';
                    echo '<form method="post" style="display:inline;">';
                    echo '<input type="hidden" name="sesskey" value="' . sesskey() . '">';
                    echo '<input type="hidden" name="userid" value="' . htmlspecialchars($userid_str) . '">';
                    echo '<input type="hidden" name="courseid" value="' . htmlspecialchars($courseid_str) . '">';
                    echo '<input type="hidden" name="badgeid" value="' . htmlspecialchars($badgeid_str) . '">';
                    echo '<input type="hidden" name="action" value="award">';
                    echo '<button type="submit" class="btn btn-primary"> Award This Badge</button>';
                    echo '</form>';
                    echo '</div>';
                } else {
                    echo '<div class="alert alert-error">';
                    echo '<h4> QUALIFICATION CHECK: FAIL</h4>';
                    echo '<p>User does NOT meet requirements for \'' . htmlspecialchars($info['name']) . '\'</p>';
                    echo '</div>';
                }
            }
        } else {
            echo '<p>Please select a user, course, and badge to debug.</p>';
        }
        echo '</div>';
        break;
    case 'audit':
        echo '<h2>Audit Trail</h2>';
        $userid_str = optional_param('userid', '', PARAM_TEXT);
        $badgeid_str = optional_param('badgeid', '', PARAM_TEXT);

        // Parse IDs
        $userid = 0;
        if ($userid_str && preg_match('/\(ID: (\d+)\)$/', $userid_str, $matches)) {
            $userid = (int)$matches[1];
        }
        $badgeid = 0;
        if ($badgeid_str && preg_match('/\(ID: (\d+)\)$/', $badgeid_str, $matches)) {
            $badgeid = (int)$matches[1];
        }

        // Populate options
        global $DB;
        $users = $DB->get_records_sql("SELECT DISTINCT u.id, u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic, u.middlename, u.alternatename, u.username FROM {user} u JOIN {local_ascend_rewards_badgerlog} l ON l.userid = u.id WHERE u.suspended = 0 AND u.deleted = 0 AND l.badgeid > 0 ORDER BY u.lastname, u.firstname");
        $badges = array_keys($badge_names);

        echo '<div class="card">';
        echo '<form method="get" style="display:flex; flex-wrap:wrap; gap:10px; align-items:end; margin-bottom:20px;">';
        echo '<input type="hidden" name="tab" value="audit">';
        echo '<div class="form-group" style="margin:0;">';
        echo '<label class="form-label">User:</label>';
        echo '<input type="text" name="userid" class="form-input" list="audit_users" value="' . htmlspecialchars($userid_str) . '" placeholder="Start typing user name..." style="width:250px;">';
        echo '<datalist id="audit_users">';
        foreach ($users as $u) {
            $label = htmlspecialchars(fullname($u)) . ' (ID: ' . $u->id . ')';
            echo '<option value="' . $label . '">';
        }
        echo '</datalist>';
        echo '</div>';
        echo '<div class="form-group" style="margin:0;">';
        echo '<label class="form-label">Badge:</label>';
        echo '<input type="text" name="badgeid" class="form-input" list="audit_badges" value="' . htmlspecialchars($badgeid_str) . '" placeholder="Start typing badge name..." style="width:250px;">';
        echo '<datalist id="audit_badges">';
        foreach ($badge_names as $bid => $bname) {
            $label = htmlspecialchars($bname) . ' (ID: ' . $bid . ')';
            echo '<option value="' . $label . '">';
        }
        echo '</datalist>';
        echo '</div>';
        echo '<button type="submit" class="btn btn-primary">Search</button>';
        echo '</form>';

        $params = [];
        $where = ['badgeid > 0']; // Only show badge awards, exclude coin transactions
        if ($userid) {
            $where[] = 'userid = :userid';
            $params['userid'] = $userid;
        }
        if ($badgeid) {
            $where[] = 'badgeid = :badgeid';
            $params['badgeid'] = $badgeid;
        }
        $where_clause = implode(' AND ', $where);
        $sql = "SELECT * FROM {local_ascend_rewards_badgerlog}" . ($where_clause ? " WHERE $where_clause" : "") . " ORDER BY timecreated DESC LIMIT 100";
        $records = $DB->get_records_sql($sql, $params);

        if ($records) {
            echo '<h3>Audit Records</h3>';
            echo '<table class="table">';
            echo '<tr><th>Record ID</th><th>User ID</th><th>User Name</th><th>Badge ID</th><th>Badge Name</th><th>Status</th><th>Message</th><th>Time Created</th></tr>';
            foreach ($records as $rec) {
                $user = $DB->get_record('user', ['id' => $rec->userid], 'firstname,lastname,firstnamephonetic,lastnamephonetic,middlename,alternatename', IGNORE_MISSING);
                $uname = $user ? fullname($user) : 'Unknown';
                $bname = isset($badge_names[$rec->badgeid]) ? $badge_names[$rec->badgeid] : 'Unknown';
                echo '<tr>';
                echo '<td>' . $rec->id . '</td>';
                echo '<td>' . $rec->userid . '</td>';
                echo '<td>' . htmlspecialchars($uname ?: '') . '</td>';
                echo '<td>' . $rec->badgeid . '</td>';
                echo '<td>' . htmlspecialchars($bname) . '</td>';
                echo '<td>' . htmlspecialchars($rec->status) . '</td>';
                echo '<td>' . htmlspecialchars($rec->message) . '</td>';
                echo '<td>' . userdate($rec->timecreated) . '</td>';
                echo '</tr>';
            }
            echo '</table>';
        } else {
            echo '<p>No audit records found.</p>';
        }
        echo '</div>';
        break;
}

// Close site-like wrapper
echo '</div></div>';
echo $OUTPUT->footer();
