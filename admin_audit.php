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
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir . '/tablelib.php');

admin_externalpage_setup('local_ascend_rewards_audit');

$context = context_system::instance();
require_capability('moodle/site:config', $context);
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

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('audit_trail', 'local_ascend_rewards'));

// Filter form
echo '<form method="get" action="' . $PAGE->url->out(false) . '" class="apex-audit-filters">';
echo '<div style="margin-bottom: 20px; padding: 15px; background: #f9f9f9; border-radius: 5px;">';
echo '<h3>Filters</h3>';
echo '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">';

// User filter
echo '<div>';
echo '<label for="userid">User ID:</label><br>';
echo '<input type="number" name="userid" id="userid" value="' . s($userid) . '" style="width: 100%;">';
echo '</div>';

// Badge filter
$badge_names = [
    6  => 'Getting Started',
    4  => 'On a Roll',
    5  => 'Halfway Hero',
    8  => 'Master Navigator',
    9  => 'Early Bird',
    11 => 'Sharp Shooter',
    10 => 'Deadline Burner',
    12 => 'Time Tamer',
    13 => 'Feedback Follower',
    15 => 'Steady Improver',
    14 => 'Tenacious Tiger',
    16 => 'Glory Guide',
    19 => 'High Flyer',
    17 => 'Activity Ace',
    7  => 'Mission Complete',
    20 => 'Learning Legend',
];

echo '<div>';
echo '<label for="badgeid">Badge:</label><br>';
echo '<select name="badgeid" id="badgeid" style="width: 100%;">';
echo '<option value="0">All Badges</option>';
foreach ($badge_names as $bid => $bname) {
    $selected = ($badgeid == $bid) ? ' selected' : '';
    echo '<option value="' . $bid . '"' . $selected . '>' . s($bname) . '</option>';
}
echo '</select>';
echo '</div>';

// Status filter
echo '<div>';
echo '<label for="status">Status:</label><br>';
echo '<select name="status" id="status" style="width: 100%;">';
echo '<option value="">All</option>';
echo '<option value="success"' . ($status === 'success' ? ' selected' : '') . '>Success</option>';
echo '<option value="revoked"' . ($status === 'revoked' ? ' selected' : '') . '>Revoked</option>';
echo '<option value="debug"' . ($status === 'debug' ? ' selected' : '') . '>Debug</option>';
echo '<option value="error"' . ($status === 'error' ? ' selected' : '') . '>Error</option>';
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
echo '<label for="courseid">Course:</label><br>';
echo '<select name="courseid" id="courseid" style="width: 100%;">';
echo '<option value="0">All Courses</option>';
foreach ($courses as $course) {
    $selected = ($courseid == $course->id) ? ' selected' : '';
    echo '<option value="' . $course->id . '"' . $selected . '>' . s($course->fullname) . '</option>';
}
echo '</select>';
echo '</div>';

// Date from
echo '<div>';
echo '<label for="datefrom">Date From:</label><br>';
echo '<input type="date" name="datefrom" id="datefrom" value="' . s($datefrom) . '" style="width: 100%;">';
echo '</div>';

// Date to
echo '<div>';
echo '<label for="dateto">Date To:</label><br>';
echo '<input type="date" name="dateto" id="dateto" value="' . s($dateto) . '" style="width: 100%;">';
echo '</div>';

echo '</div>';
echo '<div style="margin-top: 15px;">';
echo '<button type="submit" class="btn btn-primary">Apply Filters</button> ';
echo '<a href="' . $PAGE->url->out(false) . '" class="btn btn-secondary">Clear Filters</a>';
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
    $sql .= " AND l.userid = :userid";
    $params['userid'] = $userid;
}

if ($badgeid > 0) {
    $sql .= " AND l.badgeid = :badgeid";
    $params['badgeid'] = $badgeid;
}

if (!empty($status)) {
    $sql .= " AND l.status = :status";
    $params['status'] = $status;
}

if (!empty($datefrom)) {
    $timestamp = strtotime($datefrom);
    if ($timestamp !== false) {
        $sql .= " AND l.timecreated >= :datefrom";
        $params['datefrom'] = $timestamp;
    }
}

if (!empty($dateto)) {
    $timestamp = strtotime($dateto . ' 23:59:59');
    if ($timestamp !== false) {
        $sql .= " AND l.timecreated <= :dateto";
        $params['dateto'] = $timestamp;
    }
}

$sql .= " ORDER BY l.timecreated DESC";

// Get records
$records = $DB->get_records_sql($sql, $params, 0, 1000); // Limit to 1000 records

// Display table
echo '<div style="margin-top: 20px;">';
echo '<p><strong>Total Records:</strong> ' . count($records) . ' (showing up to 1000 most recent)</p>';

if (!empty($records)) {
    echo '<div style="overflow-x: auto;">';
    echo '<table class="generaltable" style="width: 100%;">';
    echo '<thead>';
    echo '<tr>';
    echo '<th>ID</th>';
    echo '<th>Date/Time</th>';
    echo '<th>User</th>';
    echo '<th>Badge</th>';
    echo '<th>Status</th>';
    echo '<th>Message</th>';
    echo '</tr>';
    echo '</thead>';
    echo '<tbody>';

    foreach ($records as $record) {
        $status_badge = '';
        $status_color = '';

        switch ($record->status) {
            case 'success':
                $status_badge = '<span style="background: #28a745; color: white; padding: 3px 8px; border-radius: 3px; font-size: 0.85em;">SUCCESS</span>';
                break;
            case 'revoked':
                $status_badge = '<span style="background: #dc3545; color: white; padding: 3px 8px; border-radius: 3px; font-size: 0.85em;">REVOKED</span>';
                break;
            case 'debug':
                $status_badge = '<span style="background: #6c757d; color: white; padding: 3px 8px; border-radius: 3px; font-size: 0.85em;">DEBUG</span>';
                break;
            case 'error':
                $status_badge = '<span style="background: #ffc107; color: black; padding: 3px 8px; border-radius: 3px; font-size: 0.85em;">ERROR</span>';
                break;
            default:
                $status_badge = '<span style="background: #17a2b8; color: white; padding: 3px 8px; border-radius: 3px; font-size: 0.85em;">' . strtoupper(s($record->status)) . '</span>';
        }

        $badge_name = $badge_names[$record->badgeid] ?? 'Badge #' . $record->badgeid;
        $user_name = $record->firstname ? fullname($record) . ' (' . $record->email . ')' : 'User #' . $record->userid;
        $date = userdate($record->timecreated, '%d %b %Y, %H:%M:%S');

        echo '<tr>';
        echo '<td>' . $record->id . '</td>';
        echo '<td>' . $date . '</td>';
        echo '<td><a href="' . new moodle_url('/user/profile.php', ['id' => $record->userid]) . '">' . s($user_name) . '</a></td>';
        echo '<td>' . s($badge_name) . '</td>';
        echo '<td>' . $status_badge . '</td>';
        echo '<td style="max-width: 400px; word-wrap: break-word;">' . s($record->message) . '</td>';
        echo '</tr>';
    }

    echo '</tbody>';
    echo '</table>';
    echo '</div>';
} else {
    echo '<p>No audit records found.</p>';
}

echo '</div>';

// Summary statistics
echo '<div style="margin-top: 30px; padding: 15px; background: #f9f9f9; border-radius: 5px;">';
echo '<h3>Statistics</h3>';

$stats_sql = "SELECT status, COUNT(*) as count
              FROM {local_ascend_rewards_badgerlog}
              WHERE 1=1";
$stats_params = [];

if ($userid > 0) {
    $stats_sql .= " AND userid = :userid";
    $stats_params['userid'] = $userid;
}

if ($badgeid > 0) {
    $stats_sql .= " AND badgeid = :badgeid";
    $stats_params['badgeid'] = $badgeid;
}

if (!empty($datefrom)) {
    $timestamp = strtotime($datefrom);
    if ($timestamp !== false) {
        $stats_sql .= " AND timecreated >= :datefrom";
        $stats_params['datefrom'] = $timestamp;
    }
}

if (!empty($dateto)) {
    $timestamp = strtotime($dateto . ' 23:59:59');
    if ($timestamp !== false) {
        $stats_sql .= " AND timecreated <= :dateto";
        $stats_params['dateto'] = $timestamp;
    }
}

$stats_sql .= " GROUP BY status ORDER BY count DESC";

$stats = $DB->get_records_sql($stats_sql, $stats_params);

if (!empty($stats)) {
    echo '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px;">';
    foreach ($stats as $stat) {
        echo '<div style="padding: 10px; background: white; border-radius: 5px; text-align: center;">';
        echo '<div style="font-size: 2em; font-weight: bold;">' . $stat->count . '</div>';
        echo '<div style="color: #666;">' . ucfirst(s($stat->status)) . '</div>';
        echo '</div>';
    }
    echo '</div>';
}

echo '</div>';

echo $OUTPUT->footer();
