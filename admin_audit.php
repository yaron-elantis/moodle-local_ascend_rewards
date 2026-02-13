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
 * Admin audit trail page for Ascend Rewards.
 *
 * @package   local_ascend_rewards
 * @copyright 2026 Elantis (Pty) LTD
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir . '/tablelib.php');

admin_externalpage_setup('local_ascend_rewards_audit');

$context = context_system::instance();
require_capability('local/ascend_rewards:manage', $context);
// This page renders large inline HTML; suppress layout-sensitive sniffs.
// phpcs:disable moodle.Files.LineLength.MaxExceeded,moodle.Files.LineLength.TooLong
// phpcs:disable moodle.NamingConventions.ValidVariableName.VariableNameUnderscore
// phpcs:disable Generic.WhiteSpace.ScopeIndent.Incorrect,Generic.WhiteSpace.ScopeIndent.IncorrectExact
// phpcs:disable moodle.Commenting.InlineComment.InvalidEndChar,moodle.Commenting.InlineComment.NotCapital
// phpcs:disable moodle.Commenting.MissingDocblock.File
// phpcs:disable moodle.WhiteSpace.WhiteSpaceInStrings.EndLine

$PAGE->set_url(new moodle_url('/local/ascend_rewards/admin_audit.php'));
$PAGE->set_title(get_string('audit_trail', 'local_ascend_rewards'));
$PAGE->set_heading(get_string('audit_trail', 'local_ascend_rewards'));

// Filters
$userid = optional_param('userid', 0, PARAM_INT);
$badgeid = optional_param('badgeid', 0, PARAM_INT);
$status = optional_param('status', '', PARAM_ALPHA);
$courseid = optional_param('courseid', 0, PARAM_INT);
$datefrom = optional_param('datefrom', '', PARAM_RAW);
$dateto = optional_param('dateto', '', PARAM_RAW);

$badge_names = [
    6  => get_string('badge_name_getting_started', 'local_ascend_rewards'),
    4  => get_string('badge_name_on_a_roll', 'local_ascend_rewards'),
    5  => get_string('badge_name_halfway_hero', 'local_ascend_rewards'),
    8  => get_string('badge_name_master_navigator', 'local_ascend_rewards'),
    9  => get_string('badge_name_early_bird', 'local_ascend_rewards'),
    11 => get_string('badge_name_sharp_shooter', 'local_ascend_rewards'),
    10 => get_string('badge_name_deadline_burner', 'local_ascend_rewards'),
    12 => get_string('badge_name_time_tamer', 'local_ascend_rewards'),
    13 => get_string('badge_name_feedback_follower', 'local_ascend_rewards'),
    15 => get_string('badge_name_steady_improver', 'local_ascend_rewards'),
    14 => get_string('badge_name_tenacious_tiger', 'local_ascend_rewards'),
    16 => get_string('badge_name_glory_guide', 'local_ascend_rewards'),
    19 => get_string('badge_name_high_flyer', 'local_ascend_rewards'),
    17 => get_string('badge_name_activity_ace', 'local_ascend_rewards'),
    7  => get_string('badge_name_mission_complete', 'local_ascend_rewards'),
    20 => get_string('badge_name_learning_legend', 'local_ascend_rewards'),
];

$status_labels = [
    'success' => get_string('status_success', 'local_ascend_rewards'),
    'revoked' => get_string('status_revoked', 'local_ascend_rewards'),
    'debug' => get_string('status_debug', 'local_ascend_rewards'),
    'error' => get_string('status_error', 'local_ascend_rewards'),
];

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('audit_trail', 'local_ascend_rewards'));

// Filter form
echo '<form method="get" action="' . $PAGE->url->out(false) . '" class="ascend-audit-filters">';
echo '<div class="aa-audit-filter-panel">';
echo '<h3>' . get_string('filters_heading', 'local_ascend_rewards') . '</h3>';
echo '<div class="aa-audit-grid">';

// User filter
echo '<div>';
echo '<label for="userid">' . get_string('user_id_label', 'local_ascend_rewards') . ':</label><br>';
echo '<input type="number" name="userid" id="userid" value="' . s($userid) . '" class="aa-admin-input-full">';
echo '</div>';

echo '<div>';
echo '<label for="badgeid">' . get_string('badge_label', 'local_ascend_rewards') . ':</label><br>';
echo '<select name="badgeid" id="badgeid" class="aa-admin-input-full">';
echo '<option value="0">' . get_string('all_badges_label', 'local_ascend_rewards') . '</option>';
foreach ($badge_names as $bid => $bname) {
    $selected = ($badgeid == $bid) ? ' selected' : '';
    echo '<option value="' . $bid . '"' . $selected . '>' . s($bname) . '</option>';
}
echo '</select>';
echo '</div>';

// Status filter
echo '<div>';
echo '<label for="status">' . get_string('status_label', 'local_ascend_rewards') . ':</label><br>';
echo '<select name="status" id="status" class="aa-admin-input-full">';
echo '<option value="">' . get_string('all_label', 'local_ascend_rewards') . '</option>';
echo '<option value="success"' . ($status === 'success' ? ' selected' : '') . '>' . $status_labels['success'] . '</option>';
echo '<option value="revoked"' . ($status === 'revoked' ? ' selected' : '') . '>' . $status_labels['revoked'] . '</option>';
echo '<option value="debug"' . ($status === 'debug' ? ' selected' : '') . '>' . $status_labels['debug'] . '</option>';
echo '<option value="error"' . ($status === 'error' ? ' selected' : '') . '>' . $status_labels['error'] . '</option>';
echo '</select>';
echo '</div>';

// Course filter
$courses = $DB->get_records_sql("
    SELECT DISTINCT c.id, c.fullname
    FROM {course} c
    JOIN {local_ascend_rewards_coins} arc ON arc.courseid = c.id
    WHERE c.id > 1
    ORDER BY c.fullname ASC
");

echo '<div>';
echo '<label for="courseid">' . get_string('course_label', 'local_ascend_rewards') . ':</label><br>';
echo '<select name="courseid" id="courseid" class="aa-admin-input-full">';
echo '<option value="0">' . get_string('all_courses_label', 'local_ascend_rewards') . '</option>';
foreach ($courses as $course) {
    $selected = ($courseid == $course->id) ? ' selected' : '';
    echo '<option value="' . $course->id . '"' . $selected . '>' . s($course->fullname) . '</option>';
}
echo '</select>';
echo '</div>';

// Date from
echo '<div>';
echo '<label for="datefrom">' . get_string('date_from_label', 'local_ascend_rewards') . '</label><br>';
echo '<input type="date" name="datefrom" id="datefrom" value="' . s($datefrom) . '" class="aa-admin-input-full">';
echo '</div>';

// Date to
echo '<div>';
echo '<label for="dateto">' . get_string('date_to_label', 'local_ascend_rewards') . '</label><br>';
echo '<input type="date" name="dateto" id="dateto" value="' . s($dateto) . '" class="aa-admin-input-full">';
echo '</div>';

echo '</div>';
echo '<div class="aa-audit-actions">';
echo '<button type="submit" class="btn btn-primary">' . get_string('apply_filters_label', 'local_ascend_rewards') . '</button> ';
echo '<a href="' . $PAGE->url->out(false) . '" class="btn btn-secondary">' . get_string('clear_filters_label', 'local_ascend_rewards') . '</a>';
echo '</div>';
echo '</div>';
echo '</form>';

// Build query - Only show badge awards (badgeid > 0), exclude coin transactions
$sql = "SELECT l.id, l.userid, l.badgeid, l.status, l.message, l.timecreated,
               u.firstname, u.lastname, u.email
          FROM {local_ascend_rewards_badgerlog} l
          LEFT JOIN {user} u ON u.id = l.userid
         WHERE l.badgeid > 0";

$params = [];

if ($userid > 0) {
    $sql .= ' AND l.userid = :userid';
    $params['userid'] = $userid;
}

if ($badgeid > 0) {
    $sql .= ' AND l.badgeid = :badgeid';
    $params['badgeid'] = $badgeid;
}

if (!empty($status)) {
    $sql .= ' AND l.status = :status';
    $params['status'] = $status;
}

if (!empty($datefrom)) {
    $timestamp = strtotime($datefrom);
    if ($timestamp !== false) {
        $sql .= ' AND l.timecreated >= :datefrom';
        $params['datefrom'] = $timestamp;
    }
}

if (!empty($dateto)) {
    $timestamp = strtotime($dateto . ' 23:59:59');
    if ($timestamp !== false) {
        $sql .= ' AND l.timecreated <= :dateto';
        $params['dateto'] = $timestamp;
    }
}

$sql .= ' ORDER BY l.timecreated DESC';

// Get records
$records = $DB->get_records_sql($sql, $params, 0, 1000); // Limit to 1000 records

// Display table
echo '<div class="aa-audit-table-wrap">';
echo '<p><strong>' . get_string('total_records_label', 'local_ascend_rewards') . '</strong> ' . count($records) . ' ' . get_string('audit_showing_limit', 'local_ascend_rewards', 1000) . '</p>';

if (!empty($records)) {
    echo '<div class="aa-table-scroll">';
    echo '<table class="generaltable aa-admin-table">';
    echo '<thead>';
    echo '<tr>';
    echo '<th>' . get_string('id_label', 'local_ascend_rewards') . '</th>';
    echo '<th>' . get_string('date_time_label', 'local_ascend_rewards') . '</th>';
    echo '<th>' . get_string('user_label', 'local_ascend_rewards') . '</th>';
    echo '<th>' . get_string('badge_label', 'local_ascend_rewards') . '</th>';
    echo '<th>' . get_string('status_label', 'local_ascend_rewards') . '</th>';
    echo '<th>' . get_string('message_label', 'local_ascend_rewards') . '</th>';
    echo '</tr>';
    echo '</thead>';
    echo '<tbody>';

    foreach ($records as $record) {
        $status_badge = '';
        $status_color = '';

        switch ($record->status) {
            case 'success':
                $status_badge = '<span class="aa-status-badge aa-status-success">' . core_text::strtoupper($status_labels['success']) . '</span>';
                break;
            case 'revoked':
                $status_badge = '<span class="aa-status-badge aa-status-revoked">' . core_text::strtoupper($status_labels['revoked']) . '</span>';
                break;
            case 'debug':
                $status_badge = '<span class="aa-status-badge aa-status-debug">' . core_text::strtoupper($status_labels['debug']) . '</span>';
                break;
            case 'error':
                $status_badge = '<span class="aa-status-badge aa-status-error">' . core_text::strtoupper($status_labels['error']) . '</span>';
                break;
            default:
                $status_badge = '<span class="aa-status-badge aa-status-other">' . strtoupper(s($record->status)) . '</span>';
        }

        $badge_name = $badge_names[$record->badgeid] ?? get_string('badge_number_label', 'local_ascend_rewards', $record->badgeid);
        $user_name = $record->firstname ? fullname($record) . ' (' . $record->email . ')' : get_string('user_number_label', 'local_ascend_rewards', $record->userid);
        $date = userdate($record->timecreated, '%d %b %Y, %H:%M:%S');

        echo '<tr>';
        echo '<td>' . $record->id . '</td>';
        echo '<td>' . $date . '</td>';
        echo '<td><a href="' . new moodle_url('/user/profile.php', ['id' => $record->userid]) . '">' . s($user_name) . '</a></td>';
        echo '<td>' . s($badge_name) . '</td>';
        echo '<td>' . $status_badge . '</td>';
        echo '<td class="aa-audit-message">' . s($record->message) . '</td>';
        echo '</tr>';
    }

    echo '</tbody>';
    echo '</table>';
    echo '</div>';
} else {
    echo '<p>' . get_string('no_audit_records_label', 'local_ascend_rewards') . '</p>';
}

echo '</div>';

// Summary statistics
echo '<div class="aa-audit-summary">';
echo '<h3>' . get_string('statistics_heading', 'local_ascend_rewards') . '</h3>';

$stats_sql = "SELECT status, COUNT(*) as count
              FROM {local_ascend_rewards_badgerlog}
              WHERE 1=1";
$stats_params = [];

if ($userid > 0) {
    $stats_sql .= ' AND userid = :userid';
    $stats_params['userid'] = $userid;
}

if ($badgeid > 0) {
    $stats_sql .= ' AND badgeid = :badgeid';
    $stats_params['badgeid'] = $badgeid;
}

if (!empty($datefrom)) {
    $timestamp = strtotime($datefrom);
    if ($timestamp !== false) {
        $stats_sql .= ' AND timecreated >= :datefrom';
        $stats_params['datefrom'] = $timestamp;
    }
}

if (!empty($dateto)) {
    $timestamp = strtotime($dateto . ' 23:59:59');
    if ($timestamp !== false) {
        $stats_sql .= ' AND timecreated <= :dateto';
        $stats_params['dateto'] = $timestamp;
    }
}

$stats_sql .= ' GROUP BY status ORDER BY count DESC';

$stats = $DB->get_records_sql($stats_sql, $stats_params);

if (!empty($stats)) {
    echo '<div class="aa-audit-summary-grid">';
    foreach ($stats as $stat) {
        echo '<div class="aa-audit-summary-card">';
        echo '<div class="aa-audit-summary-value">' . $stat->count . '</div>';
        $status_label = $status_labels[$stat->status] ?? s($stat->status);
        echo '<div class="aa-audit-summary-label">' . $status_label . '</div>';
        echo '</div>';
    }
    echo '</div>';
}

echo '</div>';

echo $OUTPUT->footer();
