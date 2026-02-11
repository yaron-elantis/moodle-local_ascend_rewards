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
 * Admin badge mapping page for Ascend Rewards.
 *
 * @package   local_ascend_rewards
 * @copyright 2026 Elantis (Pty) LTD
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_ascend_rewards;

use context_system;
use html_table;
use html_writer;
use moodle_url;

require_once(__DIR__ . '/../../config.php');
require_login();
// Preserve existing behavior without broad naming and reflow changes.
// phpcs:disable moodle.NamingConventions.ValidVariableName.VariableNameUnderscore
// phpcs:disable moodle.Commenting.InlineComment.InvalidEndChar,moodle.Commenting.InlineComment.NotCapital
// phpcs:disable moodle.Files.LineLength.MaxExceeded,moodle.Files.LineLength.TooLong
// phpcs:disable moodle.WhiteSpace.WhiteSpaceInStrings.EndLine,moodle.Commenting.MissingDocblock.Function
// phpcs:disable moodle.Commenting.MissingDocblock.Constant,moodle.Files.MoodleInternal.MoodleInternalGlobalState
$context = context_system::instance();
require_capability('local/ascend_rewards:manage', $context);
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/ascend_rewards/badges.php'));
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('managebadges', 'local_ascend_rewards'));
$PAGE->set_heading(get_string('managebadges', 'local_ascend_rewards'));
global $DB, $OUTPUT;
$action = optional_param('action', '', PARAM_ALPHA);
$courseid = optional_param('courseid', 0, PARAM_INT);
if ($action === 'seed' && confirm_sesskey()) {
    foreach (\local_ascend_rewards\badges::codes() as $code) {
        \local_ascend_rewards\badges::ensure_badge_created($code, $courseid);
    }
    redirect(new moodle_url('/local/ascend_rewards/badges.php', ['courseid' => $courseid]), get_string('badgescreated', 'local_ascend_rewards'), 2);
}
echo $OUTPUT->header();
echo html_writer::tag('h3', get_string('managebadges', 'local_ascend_rewards'));
$seed_url = new moodle_url('/local/ascend_rewards/badges.php', [
    'action' => 'seed',
    'courseid' => $courseid,
    'sesskey' => sesskey(),
]);
echo $OUTPUT->single_button(
    $seed_url,
    get_string('createbadges', 'local_ascend_rewards'),
    'post'
);
$defs = \local_ascend_rewards\badges::definitions();
$table = new html_table();
$table->head = [
    get_string('badge_code_label', 'local_ascend_rewards'),
    get_string('badge_name_label', 'local_ascend_rewards'),
    get_string('badge_idnumber_label', 'local_ascend_rewards'),
    get_string('badge_current_id_label', 'local_ascend_rewards'),
    get_string('badge_map_label', 'local_ascend_rewards'),
];
$table->data = [];
foreach ($defs as $code => $def) {
    $currentid = \local_ascend_rewards\badges::get_badgeid_by_code($code, $courseid, false);
    $select = html_writer::select(
        \local_ascend_rewards\util::badge_options($courseid),
        'map_' . $code,
        ($currentid ?: 0),
        ['0' => get_string('badge_not_mapped_label', 'local_ascend_rewards')],
        ['form' => 'ascend-map-form']
    );
    $table->data[] = new html_table_row([
        html_writer::tag('code', s($code)),
        s($def['name']),
        s($code),
        $currentid ? (int)$currentid : html_writer::span('-', 'dimmed_text'),
        $select,
    ]);
}
echo html_writer::table($table);
echo html_writer::start_tag('form', ['method' => 'post', 'id' => 'ascend-map-form', 'action' => new moodle_url('/local/ascend_rewards/badges.php')]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'courseid', 'value' => $courseid]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'save']);
echo html_writer::empty_tag('input', ['type' => 'submit', 'class' => 'btn btn-primary', 'value' => get_string('savemappings', 'local_ascend_rewards')]);
echo html_writer::end_tag('form');
if ($action === 'save' && confirm_sesskey()) {
    foreach (\local_ascend_rewards\badges::codes() as $code) {
        $badgeid = optional_param('map_' . $code, 0, PARAM_INT);
        if ($badgeid > 0) {
            $rec = $DB->get_record('badge', ['id' => $badgeid], '*', MUST_EXIST);
            if ($rec->idnumber !== $code) {
                $rec->idnumber = $code;
                $rec->timemodified = time();
                $DB->update_record('badge', $rec);
            }
            $existing = $DB->get_record('local_ascend_rewards_badges', ['code' => $code, 'courseid' => $courseid], '*', IGNORE_MISSING);
            $row = (object)['code' => $code, 'courseid' => $courseid, 'badgeid' => $badgeid, 'enabled' => 1, 'timemodified' => time()];
            if ($existing) {
                $row->id = $existing->id;
                $DB->update_record('local_ascend_rewards_badges', $row);
            } else {
                $row->timecreated = time();
                $DB->insert_record('local_ascend_rewards_badges', $row);
            }
        }
    }
    redirect(new moodle_url('/local/ascend_rewards/badges.php', ['courseid' => $courseid]), get_string('mappingsaved', 'local_ascend_rewards'), 2);
}
echo $OUTPUT->footer();

// phpcs:disable moodle.Files.MoodleInternal.MoodleInternalNotNeeded
defined('MOODLE_INTERNAL') || die();

/**
 * Badge helper utilities for the admin mapping page.
 *
 * @package local_ascend_rewards
 */
class util { public static function badge_options(int $courseid = 0): array {
        global $DB;
        $out = [];
        $rs = $DB->get_records('badge', ['courseid' => $courseid], 'name ASC', 'id,name,idnumber');
        foreach ($rs as $b) {
            $label = $b->name . ' [#' . $b->id . ']' . ($b->idnumber ? (' (' . $b->idnumber . ')') : '');
            $out[$b->id] = $label;
        }
        return $out;
}
}
