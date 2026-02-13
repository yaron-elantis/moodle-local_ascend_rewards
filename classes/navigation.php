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
 * Navigation handler for Ascend Rewards plugin.
 *
 * Adds plugin links to Moodle navigation.
 *
 * @package   local_ascend_rewards
 * @copyright 2026 Elantis (Pty) LTD
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// phpcs:disable moodle.Files.MoodleInternal.MoodleInternalNotNeeded
defined('MOODLE_INTERNAL') || die();

/**
 * Navigation class for adding Ascend Rewards to navigation menu.
 */
class local_ascend_rewards_navigation {
    /**
     * Add Ascend Rewards to primary navigation.
     *
     * @param \core\event\base $event Navigation event
     */
    public static function extend_primary_navigation(\core\event\base $event) {
        global $PAGE;

        if (!isloggedin() || isguestuser()) {
            return;
        }

        if ($PAGE->pagetype == 'local-ascend_rewards-index') {
            return;
        }

        $navigation = $PAGE->navigation;
        if (!$navigation) {
            return;
        }

        $url = new moodle_url('/local/ascend_rewards/index.php');
        $icon = new pix_icon('t/award', get_string('nav_rewards', 'local_ascend_rewards'), 'core');

        $node = $navigation->add(
            get_string('nav_rewards', 'local_ascend_rewards'),
            $url,
            navigation_node::TYPE_CUSTOM,
            null,
            'ascendrewards',
            $icon
        );
        $node->showinflatnavigation = true;
    }
}
